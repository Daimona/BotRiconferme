<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Config;
use BotRiconferme\Wiki\Controller;

/**
 * Singleton class representing the JSON list of admins
 */
class PageBotList extends Page {
	/**
	 * @private Use self::get()
	 * @param Controller $controller
	 */
	public function __construct( Controller $controller ) {
		parent::__construct( Config::getInstance()->get( 'list-title' ), $controller );
	}

	/**
	 * Instance getter
	 *
	 * @param Controller $controller
	 * @return self
	 */
	public static function get( Controller $controller ) : self {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self( $controller );
		}
		return $instance;
	}

	/**
	 * @param string[] $groups
	 * @return int|null
	 */
	public static function getOverrideTimestamp( array $groups ) : ?int {
		if ( !array_intersect_key( $groups, [ 'override-perm' => true, 'override' => true ] ) ) {
			return null;
		}

		// A one-time override takes precedence
		if ( array_key_exists( 'override', $groups ) ) {
			$date = $groups['override'];
		} else {
			$date = $groups['override-prem'] . '/' . date( 'Y' );
		}
		return \DateTime::createFromFormat( 'd/m/Y', $date )->getTimestamp();
	}

	/**
	 * Get the valid timestamp for the given groups
	 *
	 * @param array $groups
	 * @return int
	 */
	public static function getValidFlagTimestamp( array $groups ): int {
		$checkuser = isset( $groups['checkuser'] ) ?
			\DateTime::createFromFormat( 'd/m/Y', $groups['checkuser'] )->getTimestamp() :
			0;
		$bureaucrat = isset( $groups['bureaucrat'] ) ?
			\DateTime::createFromFormat( 'd/m/Y', $groups['bureaucrat'] )->getTimestamp() :
			0;

		$timestamp = max( $bureaucrat, $checkuser );
		if ( $timestamp === 0 ) {
			$timestamp = \DateTime::createFromFormat( 'd/m/Y', $groups['sysop'] )->getTimestamp();
		}
		return $timestamp;
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
