<?php

namespace BotRiconferme\Exceptions;

class MissingPageException extends \Exception {
	/**
	 * @param string $msg
	 */
	public function __construct( string $msg ) {
		parent::__construct( "The specified page doesn't exist: $msg" );
	}
}
