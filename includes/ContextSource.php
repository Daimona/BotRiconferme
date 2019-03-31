<?php

namespace BotRiconferme;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class ContextSource implements LoggerAwareInterface {
	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	public function __construct() {
		$this->setLogger( new Logger );
		$this->setConfig( Config::getInstance() );
	}

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger() : LoggerInterface {
		return $this->logger;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @return Config
	 */
	protected function getConfig() : Config {
		return $this->config;
	}

	/**
	 * @param Config $cfg
	 */
	protected function setConfig( Config $cfg ) {
		$this->config = $cfg;
	}
}
