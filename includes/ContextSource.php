<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Controller;
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

	/** @var Controller */
	private $controller;

	public function __construct() {
		$this->setLogger( new Logger );
		$this->setConfig( Config::getInstance() );
		$this->setController( new Controller );
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
	 * Shorthand to $this->getConfig()->get
	 *
	 * @param string $optname
	 * @return mixed
	 */
	protected function getOpt( string $optname ) {
		return $this->getConfig()->get( $optname );
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
	 * @return Controller
	 */
	protected function getController() : Controller {
		return $this->controller;
	}

	/**
	 * @param Controller $controller
	 */
	protected function setController( Controller $controller ) {
		$this->controller = $controller;
	}

	/**
	 * Get a message
	 *
	 * @param string $key
	 * @return Message
	 */
	protected function msg( string $key ) : Message {
		return new Message( $key );
	}
}
