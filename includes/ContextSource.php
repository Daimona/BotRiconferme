<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\User;
use BotRiconferme\Wiki\Wiki;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class with a few utility methods available to get a logger, the config and a wiki
 */
abstract class ContextSource implements LoggerAwareInterface {
	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $config;

	/** @var Wiki */
	private $wiki;

	/** @var MessageProvider */
	private $messageProvider;

	/**
	 * @param LoggerInterface $logger
	 * @param Wiki $wiki
	 * @param MessageProvider $mp
	 */
	public function __construct( LoggerInterface $logger, Wiki $wiki, MessageProvider $mp ) {
		$this->setLogger( $logger );
		$this->setConfig( Config::getInstance() );
		$this->setWiki( $wiki );
		$this->setMessageProvider( $mp );
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
	public function setLogger( LoggerInterface $logger ) : void {
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
	protected function setConfig( Config $cfg ) : void {
		$this->config = $cfg;
	}

	/**
	 * @return Wiki
	 */
	protected function getWiki() : Wiki {
		return $this->wiki;
	}

	/**
	 * @param Wiki $wiki
	 */
	protected function setWiki( Wiki $wiki ) : void {
		$this->wiki = $wiki;
	}

	/**
	 * @return MessageProvider
	 */
	protected function getMessageProvider() : MessageProvider {
		return $this->messageProvider;
	}

	/**
	 * @param MessageProvider $mp
	 */
	protected function setMessageProvider( MessageProvider $mp ) : void {
		$this->messageProvider = $mp;
	}

	/**
	 * Get a message
	 *
	 * @param string $key
	 * @return Message
	 */
	protected function msg( string $key ) : Message {
		return $this->messageProvider->getMessage( $key );
	}

	/**
	 * Shorthand to get a page using the local wiki
	 *
	 * @param string $title
	 * @return Page
	 */
	protected function getPage( string $title ) : Page {
		return new Page( $title, $this->getWiki() );
	}

	/**
	 * Shorthand to get a user using the local wiki
	 *
	 * @param string $name
	 * @return User
	 */
	protected function getUser( string $name ) : User {
		return new User( $name, $this->getWiki() );
	}
}
