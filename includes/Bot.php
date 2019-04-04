<?php declare( strict_types=1 );

namespace BotRiconferme;

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
	 * Run a single task, e.g. for debugging purposes
	 *
	 * @param string $task
	 */
	public function runSingle( string $task ) {
		$this->logger->info( "Starting single task $task." );
		$manager = new TaskManager;
		$res = $manager->run( TaskManager::MODE_SINGLE, $task );
		$line = '---------------------------------------------------';
		if ( $res->isOK() ) {
			$this->logger->info( "Execution of $task completed successfully.\n$line\n\n" );
		} else {
			$this->logger->error( "Execution of $task failed.\n$res\n$line\n\n" );
		}
	}
}
