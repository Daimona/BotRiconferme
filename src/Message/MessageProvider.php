<?php declare( strict_types = 1 );

namespace BotRiconferme\Message;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Message\Exception\InvalidMessagePageException;
use BotRiconferme\Message\Exception\MessageNotFoundException;
use BotRiconferme\Message\Exception\MessagesPageDoesNotExistException;
use BotRiconferme\Request\Exception\MissingPageException;
use BotRiconferme\Wiki\Wiki;
use JsonException;

class MessageProvider {
	/** @var string[]|null */
	private static ?array $messages = null;

	public function __construct(
		private readonly Wiki $wiki,
		private readonly string $msgTitle
	) {
	}

	private function grabWikiMessages(): void {
		if ( self::$messages !== null ) {
			return;
		}
		try {
			$cont = $this->wiki->getPageContent( $this->msgTitle );
			$wikiMessages = json_decode( $cont, true, 512, JSON_THROW_ON_ERROR );
		} catch ( MissingPageException ) {
			throw new MessagesPageDoesNotExistException( 'Please create a messages page.' );
		} catch ( JsonException ) {
			throw new InvalidMessagePageException( 'Invalid messages page.' );
		}
		if ( !is_array( $wikiMessages ) ) {
			throw new ConfigException( "Invalid messages page" );
		}
		self::$messages = $wikiMessages;
	}

	public function getMessage( string $key ): Message {
		$this->grabWikiMessages();
		$messageText = self::$messages[$key] ?? null;
		if ( !$messageText ) {
			throw new MessageNotFoundException( "Message '$key' does not exist." );
		}
		return new Message( $messageText );
	}
}
