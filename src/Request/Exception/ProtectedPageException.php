<?php declare( strict_types=1 );

namespace BotRiconferme\Request\Exception;

/**
 * Error thrown when trying to write a protected page
 */
class ProtectedPageException extends EditException {
	/**
	 * @param string|null $title If available
	 */
	public function __construct( ?string $title = null ) {
		if ( $title ) {
			parent::__construct( "The specified page is protected: $title" );
		} else {
			parent::__construct();
		}
	}
}
