<?php

namespace BotRiconferme;

class Bot {
	/** @var Logger */
	private $logger;

	public function __construct() {
		$this->logger = new Logger;
	}

	public function run() {
		$this->logger->info( 'Starting process.' );
		$manager = new TaskManager;
		$res = $manager->run( TaskManager::MODE_COMPLETE );
		if ( $res->isOK() ) {
			$this->logger->info( 'Execution completed successfully.' );
		} else {
			$this->logger->warning( "Execution failed.\n$res"  );
		}
	}
}
