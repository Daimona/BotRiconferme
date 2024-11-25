<?php declare( strict_types=1 );

namespace BotRiconferme\Exception;

/**
 * Error thrown when trying to read a non-existent page, or write it when nocreate is specified
 */
class MissingPageException extends APIRequestException {
	/**
	 * @param string|null $title If available
	 */
	public function __construct( ?string $title = null ) {
		if ( $title ) {
			parent::__construct( "The specified page doesn't exist: $title" );
		} else {
			parent::__construct();
		}
	}
}
