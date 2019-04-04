<?php declare( strict_types=1 );

namespace BotRiconferme\Exception;

class MissingPageException extends APIRequestException {
	/**
	 * @param string|null $title
	 */
	public function __construct( string $title = null ) {
		if ( $title ) {
			parent::__construct( "The specified page doesn't exist: $title" );
		} else {
			parent::__construct();
		}
	}
}
