<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Logger\IFlushingAwareLogger;
use BotRiconferme\Logger\MultiLogger;
use BotRiconferme\Logger\SimpleLogger;
use BotRiconferme\Logger\WikiLogger;
use BotRiconferme\Request\RequestFactory;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\LoginInfo;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Wiki;
use BotRiconferme\Wiki\WikiGroup;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Higher-level class. It only wraps tasks executions, and contains generic data
 */
class Bot {
	/** @var IFlushingAwareLogger */
	private $mainLogger;
	/** @var WikiGroup */
	private $wikiGroup;
	/** @var MessageProvider */
	private $messageProvider;
	/** @var CLI */
	private $cli;

	/**
	 * @param CLI $cli
	 */
	public function __construct( CLI $cli ) {
		$this->cli = $cli;
		$this->initialize();
	}

	/**
	 * Initialize all members.
	 */
	private function initialize(): void {
		$simpleLogger = new SimpleLogger();
		$this->createWikiGroup( $simpleLogger );
		$this->messageProvider = new MessageProvider(
			$this->wikiGroup->getMainWiki(),
			$this->cli->getSetOpt( 'msg-title' )
		);
		$this->initConfig();
		$this->createMainLogger( $simpleLogger );
	}

	/**
	 * Main entry point
	 */
	public function run(): void {
		$taskOpt = $this->cli->getTaskOpt();
		$type = current( array_keys( $taskOpt ) );
		try {
			if ( $type === 'tasks' ) {
				$this->runInternal( TaskManager::MODE_TASK, explode( ',', $taskOpt['tasks'] ) );
			} elseif ( $type === 'subtasks' ) {
				$this->runInternal( TaskManager::MODE_SUBTASK, explode( ',', $taskOpt['subtasks'] ) );
			} else {
				$this->runInternal();
			}
		} catch ( Throwable $e ) {
			$this->mainLogger->error( $e->__toString() );
		} finally {
			$this->mainLogger->flush();
		}
	}

	/**
	 * @param LoggerInterface $baseLogger
	 */
	private function createWikiGroup( LoggerInterface $baseLogger ): void {
		// FIXME Hardcoded
		$url = $this->cli->getURL() ?? 'https://it.wikipedia.org/w/api.php';
		$localUserIdentifier = '@itwiki';
		$centralPagePrefix = 'meta:';
		$centralURL = 'https://meta.wikimedia.org/w/api.php';
		$privateURL = 'https://sysop-it.wikipedia.org/w/api.php';
		$privatePagePrefix = 'private:';

		$loginInfo = new LoginInfo(
			$this->cli->getSetOpt( 'username' ),
			$this->cli->getSetOpt( 'password' )
		);

		$rf = new RequestFactory( $baseLogger, $url );
		$wiki = new Wiki( $loginInfo, $baseLogger, $rf );

		$centralRF = new RequestFactory( $baseLogger, $centralURL );
		$centralWiki = new Wiki( $loginInfo, $baseLogger, $centralRF );
		$centralWiki->setLocalUserIdentifier( $localUserIdentifier );
		$centralWiki->setPagePrefix( $centralPagePrefix );

		$privateLI = new LoginInfo(
			$this->cli->getSetOpt( 'username' ),
			$this->cli->getSetOpt( 'private-password' )
		);
		$privateRF = new RequestFactory( $baseLogger, $privateURL );
		$privateWiki = new Wiki( $privateLI, $baseLogger, $privateRF );
		$privateWiki->setPagePrefix( $privatePagePrefix );

		$this->wikiGroup = new WikiGroup( $wiki, $centralWiki, $privateWiki );
	}

	/**
	 * FIXME SO MUCH DEPENDENCY HELL
	 *
	 * @param IFlushingAwareLogger $baseLogger
	 */
	private function createMainLogger( IFlushingAwareLogger $baseLogger ): void {
		$mainWiki = $this->wikiGroup->getMainWiki();
		$mp = $this->messageProvider;
		$errTitle = $this->cli->getOpt( 'error-title' );
		if ( $errTitle !== null ) {
			// Use a different Wiki with higher min level.
			$wikiLoggerLogger = new SimpleLogger( LogLevel::ERROR );
			$wikiLoggerWiki = new Wiki(
				$mainWiki->getLoginInfo(),
				$wikiLoggerLogger,
				$mainWiki->getRequestFactory()
			);
			$errPage = new Page( $errTitle, $wikiLoggerWiki );
			$wikiLogger = new WikiLogger(
				$errPage,
				$mp->getMessage( 'error-page-summary' )->text(),
				LogLevel::ERROR
			);
			$this->mainLogger = new MultiLogger( $baseLogger, $wikiLogger );
		} else {
			$this->mainLogger = $baseLogger;
		}
	}

	/**
	 * Create the Config
	 */
	private function initConfig(): void {
		$wiki = $this->wikiGroup->getMainWiki();
		try {
			$confValues = json_decode( $wiki->getPageContent( $this->cli->getSetOpt( 'config-title' ) ), true );
		} catch ( MissingPageException $_ ) {
			exit( 'Please create a config page.' );
		}

		Config::init( $confValues );
	}

	/**
	 * Internal call to TaskManager
	 *
	 * @param string $mode
	 * @param string[] $taskNames
	 */
	private function runInternal(
		string $mode = TaskManager::MODE_COMPLETE,
		array $taskNames = []
	): void {
		$activity = $mode === TaskManager::MODE_COMPLETE
			? 'full process'
			: ( $mode === TaskManager::MODE_TASK ? 'tasks' : 'subtasks' ) . ': ' . implode( ', ', $taskNames );
		$this->mainLogger->info( "Running $activity" );
		$pbl = PageBotList::get(
			$this->wikiGroup->getMainWiki(),
			$this->cli->getSetOpt( 'list-title' )
		);
		$manager = new TaskManager(
			$this->mainLogger,
			$this->wikiGroup,
			$this->messageProvider,
			$pbl
		);
		$res = $manager->run( $mode, $taskNames );
		$base = "Execution of $activity";
		if ( $res->isOK() ) {
			$msg = $res->getStatus() === TaskResult::STATUS_NOTHING ?
				': nothing to do' :
				' completed successfully';
			$this->mainLogger->info( $base . $msg );
		} else {
			$this->mainLogger->error( "$base failed.\n$res" );
		}
	}
}
