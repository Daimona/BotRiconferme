<?php declare(strict_types=1);

namespace BotRiconferme\Exception;

class ProtectedPageException extends APIRequestException {
	/**
	 * @param string|null $title
	 */
	public function __construct( string $title = null ) {
		if ( $title ) {
			parent::__construct( "The specified page is protected: $title" );
		} else {
			parent::__construct();
		}
	}
}
