<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Wiki\UserInfo;
use BotRiconferme\Wiki\Wiki;
use DateTime;

/**
 * Singleton class representing the JSON list of admins
 */
class PageBotList extends Page {
	/** @var UserInfo[]|null */
	private ?array $adminsList;

	/**
	 * Use self::get() instead
	 * @param string $listTitle
	 * @param Wiki $wiki
	 */
	private function __construct( string $listTitle, Wiki $wiki ) {
		parent::__construct( $listTitle, $wiki );
	}

	/**
	 * Instance getter
	 *
	 * @param Wiki $wiki
	 * @param string $listTitle
	 * @return self
	 */
	public static function get( Wiki $wiki, string $listTitle ): self {
		static $instance = null;
		if ( $instance === null ) {
			$instance = new self( $listTitle, $wiki );
		}
		return $instance;
	}

	/**
	 * @param UserInfo $ui
	 * @return int|null
	 */
	public function getOverrideTimestamp( UserInfo $ui ): ?int {
		$info = $ui->getInfo();
		if ( !array_intersect_key( $info, [ 'override-perm' => true, 'override' => true ] ) ) {
			return null;
		}

		// A one-time override takes precedence
		if ( array_key_exists( 'override', $info ) ) {
			$date = $info['override'];
		} else {
			$date = $info['override-prem'] . '/' . date( 'Y' );
		}
		return DateTime::createFromFormat( 'd/m/Y', $date )->getTimestamp();
	}

	/**
	 * Get the next valid timestamp for the given user
	 *
	 * @param string $user
	 * @return int
	 * @suppress PhanPluginComparisonObjectOrdering DateTime objects can be compared (phan issue #2907)
	 */
	public function getNextTimestamp( string $user ): int {
		$userInfo = $this->getUserInfo( $user )->getInfo();
		$now = new DateTime();
		if ( isset( $userInfo['override-perm'] ) ) {
			$date = DateTime::createFromFormat(
				'd/m/Y',
				$userInfo['override-perm'] . '/' . date( 'Y' )
			);
		} else {
			$date = null;
			if ( isset( $userInfo['override'] ) ) {
				$date = DateTime::createFromFormat( 'd/m/Y', $userInfo['override'] );
			}
			if ( !$date || $date <= $now ) {
				$ts = self::getValidFlagTimestamp( $userInfo );
				$date = ( new DateTime )->setTimestamp( $ts );
				$date->modify( '+1 year' );
			}
		}
		// @phan-suppress-next-line PhanPossiblyInfiniteLoop
		while ( $date <= $now ) {
			$date->modify( '+1 year' );
		}
		return $date->getTimestamp();
	}

	/**
	 * Get the valid timestamp for the given groups
	 *
	 * @param string[] $groups
	 * @return int
	 */
	public static function getValidFlagTimestamp( array $groups ): int {
		$checkuser = isset( $groups['checkuser'] ) ?
			DateTime::createFromFormat( 'd/m/Y', $groups['checkuser'] )->getTimestamp() :
			0;
		$bureaucrat = isset( $groups['bureaucrat'] ) ?
			DateTime::createFromFormat( 'd/m/Y', $groups['bureaucrat'] )->getTimestamp() :
			0;

		$timestamp = max( $bureaucrat, $checkuser );
		if ( $timestamp === 0 ) {
			$timestamp = DateTime::createFromFormat( 'd/m/Y', $groups['sysop'] )->getTimestamp();
		}
		return $timestamp;
	}

	/**
	 * An override is considered expired if:
	 * - The override date has passed (that's the point of having an override), AND
	 * - The "normal" date has passed (otherwise we'd use two different dates for the same year)
	 * For decreased risk, we add an additional delay of 3 days.
	 *
	 * @param string[] $groups
	 * @return bool
	 */
	public static function isOverrideExpired( array $groups ): bool {
		if ( !isset( $groups['override'] ) ) {
			return false;
		}

		$flagTS = self::getValidFlagTimestamp( $groups );
		$usualTS = strtotime( date( 'Y' ) . '-' . date( 'm-d', $flagTS ) );
		$overrideTS = DateTime::createFromFormat( 'd/m/Y', $groups['override'] )->getTimestamp();
		$delay = 60 * 60 * 24 * 3;

		return time() > $usualTS + $delay && time() > $overrideTS + $delay;
	}

	/**
	 * Get the actual list of admins
	 *
	 * @return UserInfo[]
	 */
	public function getAdminsList(): array {
		if ( $this->adminsList === null ) {
			$this->adminsList = [];
			foreach ( $this->getDecodedContent() as $user => $info ) {
				$this->adminsList[ $user ] = new UserInfo( $user, $info );
			}
		}
		return $this->adminsList;
	}

	/**
	 * @param string $user
	 * @return UserInfo
	 */
	public function getUserInfo( string $user ): UserInfo {
		return $this->getAdminsList()[$user];
	}

	/**
	 * Get the JSON-decoded content of the list
	 *
	 * @return array[]
	 * @phan-return array<string,array{sysop:string,checkuser?:string,bureaucrat?:string,override?:string,override-perm?:string,aliases?:list<string>}>
	 */
	public function getDecodedContent(): array {
		return json_decode( $this->getContent(), true );
	}
}
