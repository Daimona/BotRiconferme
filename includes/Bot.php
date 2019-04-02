<?php

namespace BotRiconferme;

class Bot {
	/** @var Logger */
	private $logger;

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
		if ( $res->isOK() ) {
			$this->logger->info( 'Execution completed successfully.' );
		} else {
			$this->logger->warning( "Execution failed.\n$res" );
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
		if ( $res->isOK() ) {
			$this->logger->info( "Execution of $task completed successfully." );
		} else {
			$this->logger->warning( "Execution of $task failed.\n$res" );
		}
	}
}
