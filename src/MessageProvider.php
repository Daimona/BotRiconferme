<?php declare( strict_types = 1 );

namespace BotRiconferme;

use BotRiconferme\Exception\MessageNotFoundException;
use BotRiconferme\Exception\MessagesPageDoesNotExistException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Message\Message;
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
			self::$messages = json_decode( $cont, true, 512, JSON_THROW_ON_ERROR );
		} catch ( MissingPageException $_ ) {
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
