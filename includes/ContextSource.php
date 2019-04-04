<?php declare( strict_types=1 );

namespace BotRiconferme;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class with a few utility methods available to get a logger, the config and a wiki controller
 */
abstract class ContextSource implements LoggerAwareInterface {
	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	/** @var WikiController */
	private $controller;

	public function __construct() {
		$this->setLogger( new Logger );
		$this->setConfig( Config::getInstance() );
		$this->setController( new WikiController );
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

	/**
	 * @return WikiController
	 */
	protected function getController() : WikiController {
		return $this->controller;
	}

	/**
	 * @param WikiController $controller
	 */
	protected function setController( WikiController $controller ) {
		$this->controller = $controller;
	}
}
