<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\WikiGroup;
use Psr\Log\LoggerInterface;

/**
 * Higher-level class. It only wraps tasks executions, and contains generic data
 */
class Bot {
	/** @var LoggerInterface */
	private $logger;
	/** @var WikiGroup */
	private $wikiGroup;
	/** @var MessageProvider */
	private $messageProvider;
	/** @var PageBotList */
	private $pageBotList;

	/**
	 * @param LoggerInterface $logger
	 * @param WikiGroup $wikiGroup
	 * @param MessageProvider $mp
	 * @param PageBotList $pbl
	 */
	public function __construct(
		LoggerInterface $logger,
		WikiGroup $wikiGroup,
		MessageProvider $mp,
		PageBotList $pbl
	) {
		$this->logger = $logger;
		$this->wikiGroup = $wikiGroup;
		$this->messageProvider = $mp;
		$this->pageBotList = $pbl;
	}

	/**
	 * Internal call to TaskManager
	 *
	 * @param string $mode
	 * @param string|null $name
	 */
	private function run( string $mode = TaskManager::MODE_COMPLETE, string $name = null ) : void {
		$activity = $mode === TaskManager::MODE_COMPLETE ? TaskManager::MODE_COMPLETE : "$mode $name";
		$this->logger->info( "Running $activity" );
		$manager = new TaskManager(
			$this->logger,
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
			$this->logger->info( $base . $msg );
		} else {
			$this->logger->error( "$base failed.\n$res" );
		}
	}

	/**
	 * Entry point for the whole process
	 */
	public function runAll() : void {
		$this->run();
	}

	/**
	 * Run a single task
	 *
	 * @param string $task
	 */
	public function runTask( string $task ) : void {
		$this->run( TaskManager::MODE_TASK, $task );
	}

	/**
	 * Run a single subtask, e.g. for debugging purposes
	 *
	 * @param string $subtask
	 */
	public function runSubtask( string $subtask ) : void {
		$this->run( TaskManager::MODE_SUBTASK, $subtask );
	}
}
