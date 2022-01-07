<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Message\Message;
use BotRiconferme\Request\RequestFactory;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\User;
use BotRiconferme\Wiki\Wiki;
use BotRiconferme\Wiki\WikiGroup;
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
		$this->setLogger( $logger );
		$this->setConfig( Config::getInstance() );
		$this->setWikiGroup( $wikiGroup );
		$this->setMessageProvider( $mp );
		$this->pageBotList = $pbl;
	}

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * @inheritDoc
	 */
	public function setLogger( LoggerInterface $logger ): void {
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
	protected function getConfig(): Config {
		return $this->config;
	}

	/**
	 * @param Config $cfg
	 */
	protected function setConfig( Config $cfg ): void {
		$this->config = $cfg;
	}

	/**
	 * Shorthand
	 * @return Wiki
	 */
	protected function getWiki(): Wiki {
		return $this->getWikiGroup()->getMainWiki();
	}

	/**
	 * @return WikiGroup
	 */
	protected function getWikiGroup(): WikiGroup {
		return $this->wikiGroup;
	}

	/**
	 * @param WikiGroup $wikiGroup
	 */
	protected function setWikiGroup( WikiGroup $wikiGroup ): void {
		$this->wikiGroup = $wikiGroup;
	}

	/**
	 * @return MessageProvider
	 */
	protected function getMessageProvider(): MessageProvider {
		return $this->messageProvider;
	}

	/**
	 * @param MessageProvider $mp
	 */
	protected function setMessageProvider( MessageProvider $mp ): void {
		$this->messageProvider = $mp;
	}

	/**
	 * Get a message
	 *
	 * @param string $key
	 * @return Message
	 */
	protected function msg( string $key ): Message {
		return $this->messageProvider->getMessage( $key );
	}

	/**
	 * @return PageBotList
	 */
	public function getBotList(): PageBotList {
		return $this->pageBotList;
	}

	/**
	 * @return RequestFactory
	 */
	public function getRequestFactory(): RequestFactory {
		return $this->getWiki()->getRequestFactory();
	}

	/**
	 * Shorthand to get a page using the local wiki
	 *
	 * @param string $title
	 * @return Page
	 */
	protected function getPage( string $title ): Page {
		return new Page( $title, $this->getWiki() );
	}

	/**
	 * Shorthand to get a user using the local wiki
	 *
	 * @param string $name
	 * @return User
	 */
	protected function getUser( string $name ): User {
		$ui = $this->getBotList()->getUserInfo( $name );
		return new User( $ui, $this->getWiki() );
	}
}
