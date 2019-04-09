<?php declare( strict_types=1 );

namespace BotRiconferme\Page;

use BotRiconferme\Config;

/**
 * Singleton class representing the JSON list of admins
 */
class PageBotList extends Page {
	/**
	 * @private Use self::get()
	 */
	public function __construct() {
		parent::__construct( Config::getInstance()->get( 'list-title' ) );
	}

	/**
	 * Instance getter
	 *
	 * @return self
	 */
	public static function get() : self {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self;
		}
		return $instance;
	}

	/**
	 * Get the actual list of admins
	 *
	 * @return array[]
	 */
	public function getAdminsList() : array {
		return json_decode( $this->getContent(), true );
	}
}