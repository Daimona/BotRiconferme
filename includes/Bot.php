<?php declare( strict_types=1 );

namespace BotRiconferme;

/**
 * Higher-level class. It only wraps tasks executions, and contains generic data
 */
class Bot {
	/** @var Logger */
	private $logger;

	const VERSION = 1.0;

	public function __construct() {
		$this->logger = new Logger;
	}

	/**
	 * Entry point for the whole process
	 */
	public function run() {
		$this->logger->info( 'Starting full process.' );
		$manager = new TaskManager;
		$res = $manager->run( TaskManager::MODE_COMPLETE );
		$line = '---------------------------------------------------';
		if ( $res->isOK() ) {
			$this->logger->info( "Execution completed successfully.\n$line\n\n" );
		} else {
			$this->logger->error( "Execution failed.\n$res\n$line\n\n" );
		}
	}

	/**
	 * Run a single task
	 *
	 * @param string $task
	 */
	public function runTask( string $task ) {
		$this->logger->info( "Starting single task $task." );
		$manager = new TaskManager;
		$res = $manager->run( TaskManager::MODE_TASK, $task );
		$line = '---------------------------------------------------';
		if ( $res->isOK() ) {
			$this->logger->info( "Execution of task $task completed successfully.\n$line\n\n" );
		} else {
			$this->logger->error( "Execution of task $task failed.\n$res\n$line\n\n" );
		}
	}

	/**
	 * Run a single subtask, e.g. for debugging purposes
	 *
	 * @param string $subtask
	 */
	public function runSubtask( string $subtask ) {
		$this->logger->info( "Starting single subtask $subtask." );
		$manager = new TaskManager;
		$res = $manager->run( TaskManager::MODE_SUBTASK, $subtask );
		$line = '---------------------------------------------------';
		if ( $res->isOK() ) {
			$this->logger->info( "Execution of subtask $subtask completed successfully.\n$line\n\n" );
		} else {
			$this->logger->error( "Execution of subtask $subtask failed.\n$res\n$line\n\n" );
		}
	}
}
