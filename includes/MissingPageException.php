<?php

class MissingPageException extends Exception {
	public function __construct( $msg ) {
		parent::__construct( "The specified page doesn't exist: $msg" );
	}
}
