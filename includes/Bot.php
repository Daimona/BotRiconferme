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

/**
 * Higher-level class. It only wraps tasks executions, and contains generic data
 */
class Bot {
	/** @var LoggerInterface */
	private $mainLogger;
	/** @var WikiGroup */
	private $wikiGroup;
	/** @var MessageProvider */
	private $messageProvider;
	/** @var PageBotList */
	private $pageBotList;
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
	private function initialize() : void {
		$simpleLogger = new SimpleLogger();
		$this->wikiGroup = $this->createWikiGroup( $simpleLogger );
		$this->messageProvider = new MessageProvider(
			$this->wikiGroup->getMainWiki(),
			$this->cli->getOpt( 'msg-title' )
		);
		$this->initConfig();
		$this->mainLogger = $this->createMainLogger( $simpleLogger );
		$this->pageBotList = PageBotList::get(
			$this->wikiGroup->getMainWiki(),
			$this->cli->getOpt( 'list-title' )
		);
	}

	/**
	 * Main entry point
	 */
	public function run() : void {
		$taskOpt = $this->cli->getTaskOpt();
		$type = current( array_keys( $taskOpt ) );
		try {
			if ( $type === 'task' ) {
				$this->runInternal( TaskManager::MODE_TASK, $taskOpt['task'] );
			} elseif ( $type === 'subtask' ) {
				$this->runInternal( TaskManager::MODE_SUBTASK, $taskOpt['subtask'] );
			} else {
				$this->runInternal();
			}
		} catch ( \Throwable $e ) {
			$this->mainLogger->error( $e->__toString() );
		} finally {
			$this->mainLogger->flush();
		}
	}

	/**
	 * @param LoggerInterface $baseLogger
	 * @return WikiGroup
	 */
	private function createWikiGroup( LoggerInterface $baseLogger ) : WikiGroup {
		// FIXME Hardcoded
		$url = $this->cli->getURL() ?? 'https://it.wikipedia.org/w/api.php';
		$localUserIdentifier = '@itwiki';
		$centralURL = 'https://meta.wikimedia.org/w/api.php';

		$loginInfo = new LoginInfo(
			$this->cli->getOpt( 'username' ),
			$this->cli->getOpt( 'password' )
		);

		$rf = new RequestFactory( $url );
		$wiki = new Wiki( $loginInfo, $baseLogger, $rf );
		$centralRF = new RequestFactory( $centralURL );
		$centralWiki = new Wiki( $loginInfo, $baseLogger, $centralRF );
		$centralWiki->setLocalUserIdentifier( $localUserIdentifier );
		return new WikiGroup( $wiki, $centralWiki );
	}

	/**
	 * FIXME SO MUCH DEPENDENCY HELL
	 *
	 * @param IFlushingAwareLogger $baseLogger
	 * @return IFlushingAwareLogger
	 */
	private function createMainLogger( IFlushingAwareLogger $baseLogger ) : IFlushingAwareLogger {
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
			$mainLogger = new MultiLogger( $baseLogger, $wikiLogger );
		} else {
			$mainLogger = $baseLogger;
		}
		return $mainLogger;
	}

	/**
	 * Create the Config
	 */
	private function initConfig() : void {
		$wiki = $this->wikiGroup->getMainWiki();
		try {
			$confValues = json_decode( $wiki->getPageContent( $this->cli->getOpt( 'config-title' ) ), true );
		} catch ( MissingPageException $_ ) {
			exit( 'Please create a config page.' );
		}

		Config::init( $confValues );
	}

	/**
	 * Internal call to TaskManager
	 *
	 * @param string $mode
	 * @param string|null $name
	 */
	private function runInternal(
		string $mode = TaskManager::MODE_COMPLETE,
		string $name = null
	) : void {
		$activity = $mode === TaskManager::MODE_COMPLETE ? TaskManager::MODE_COMPLETE : "$mode $name";
		$this->mainLogger->info( "Running $activity" );
		$manager = new TaskManager(
			$this->mainLogger,
			$this->wikiGroup,
			$this->messageProvider,
			$this->pageBotList
		);
		$res = $manager->run( $mode, $name );
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
