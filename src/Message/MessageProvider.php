<?php declare( strict_types = 1 );

namespace BotRiconferme\Message;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Message\Exception\MessageNotFoundException;
use BotRiconferme\Message\Exception\MessagesPageDoesNotExistException;
use BotRiconferme\Request\Exception\MissingPageException;
use BotRiconferme\Wiki\Wiki;

class MessageProvider {
	/** @var string[]|null */
	private static ?array $messages = null;

	private Wiki $wiki;
	private string $msgTitle;

	/**
	 * @param Wiki $wiki
	 * @param string $msgTitle
	 */
	public function __construct( Wiki $wiki, string $msgTitle ) {
		$this->wiki = $wiki;
		$this->msgTitle = $msgTitle;
	}

	/**
	 * @throws MessagesPageDoesNotExistException
	 */
	private function grabWikiMessages(): void {
		if ( self::$messages !== null ) {
			return;
		}
		try {
			$cont = $this->wiki->getPageContent( $this->msgTitle );
			$wikiMessages = json_decode( $cont, true, 512, JSON_THROW_ON_ERROR );
			if ( !is_array( $wikiMessages ) ) {
				throw new ConfigException( "Invalid messages page" );
			}
			self::$messages = $wikiMessages;
		} catch ( MissingPageException ) {
			throw new MessagesPageDoesNotExistException( 'Please create a messages page.' );
		}
	}

	/**
	 * @param string $key
	 * @return Message
	 * @throws MessageNotFoundException
	 */
	public function getMessage( string $key ): Message {
		$this->grabWikiMessages();
		if ( !isset( self::$messages[ $key ] ) ) {
			throw new MessageNotFoundException( "Message '$key' does not exist." );
		}
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		return new Message( self::$messages[$key] );
	}
}
