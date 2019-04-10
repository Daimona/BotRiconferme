<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

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
	 * @param string[] $groups
	 * @return int|null
	 */
	public static function getOverrideTimestamp( array $groups ) : ?int {
		if ( array_intersect_key( $groups, [ 'override-perm' => true, 'override' => true ] ) ) {
			// A one-time override takes precedence
			$date = $groups[ 'override' ] ?? $groups[ 'override-perm' ];
			return \DateTime::createFromFormat( 'd/m/Y', $date )->getTimestamp();
		}
		return null;
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
