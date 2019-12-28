<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Config;
use BotRiconferme\Wiki\User;
use BotRiconferme\Wiki\Wiki;

/**
 * Singleton class representing the JSON list of admins
 */
class PageBotList extends Page {
	public const NON_GROUP_KEYS = [ 'override', 'override-perm', 'aliases' ];

	/** @var User[]|null */
	private $adminsList;

	/**
	 * @private Use self::get()
	 * @param Wiki $wiki
	 */
	public function __construct( Wiki $wiki ) {
		parent::__construct( Config::getInstance()->get( 'list-title' ), $wiki );
	}

	/**
	 * Instance getter
	 *
	 * @param Wiki $wiki
	 * @return self
	 */
	public static function get( Wiki $wiki ) : self {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self( $wiki );
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
	 * @return User[]
	 */
	public function getAdminsList() : array {
		if ( $this->adminsList === null ) {
			$this->adminsList = [];
			foreach ( $this->getDecodedContent() as $user => $info ) {
				$userObj = new User( $user, $this->wiki );
				$userObj->setInfo( $info );
				$this->adminsList[ $user ] = $userObj;
			}
		}
		return $this->adminsList;
	}

	/**
	 * Get the JSON-decoded content of the list
	 *
	 * @return array[]
	 */
	public function getDecodedContent() : array {
		return json_decode( $this->getContent(), true );
	}
}
