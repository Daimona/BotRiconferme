<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Wiki;
use Psr\Log\LoggerInterface;

/**
 * Higher-level class. It only wraps tasks executions, and contains generic data
 */
class Bot {
	/** @var LoggerInterface */
	private $logger;
	/** @var Wiki */
	private $wiki;

	public const VERSION = '1.0';

	/**
	 * @param LoggerInterface $logger
	 * @param Wiki $wiki
	 */
	public function __construct( LoggerInterface $logger, Wiki $wiki ) {
		$this->logger = $logger;
		$this->wiki = $wiki;
	}

	/**
	 * Internal call to TaskManager
	 *
	 * @param string $mode
	 * @param string|null $name
	 */
	private function run( string $mode = TaskManager::MODE_COMPLETE, string $name = null ) {
		$activity = $mode === TaskManager::MODE_COMPLETE ? TaskManager::MODE_COMPLETE : "$mode $name";
		$this->logger->info( "Running $activity" );
		$manager = new TaskManager( $this->logger, $this->wiki );
		$res = $manager->run( $mode, $name );
		$line = str_repeat( '-', 80 );
		$base = "Execution of $activity";
		if ( $res->isOK() ) {
			$msg = $res->getStatus() === TaskResult::STATUS_NOTHING ?
				': nothing to do' :
				' completed successfully';
			$this->logger->info( $base . $msg . ".\n$line\n\n" );
		} else {
			$this->logger->error( "$base failed.\n$res\n$line\n\n" );
		}
	}

	/**
	 * Entry point for the whole process
	 */
	public function runAll() {
		$this->run();
	}

	/**
	 * Run a single task
	 *
	 * @param string $task
	 */
	public function runTask( string $task ) {
		$this->run( TaskManager::MODE_TASK, $task );
	}

	/**
	 * Run a single subtask, e.g. for debugging purposes
	 *
	 * @param string $subtask
	 */
	public function runSubtask( string $subtask ) {
		$this->run( TaskManager::MODE_SUBTASK, $subtask );
	}
}
