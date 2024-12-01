<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Wiki\UserInfo;
use BotRiconferme\Wiki\Wiki;
use DateTime;

/**
 * Singleton class representing the JSON list of admins
 */
class PageBotList extends Page {
	/** @var UserInfo[]|null */
	private ?array $adminsList = null;

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
		// A one-time override takes precedence, unless it's expired
		$override = $ui->getOverride();
		if ( $override !== null ) {
			$dateTime = DateTime::createFromFormat( 'd/m/Y', $override );
			if ( !$dateTime ) {
				throw new ConfigException( "Invalid override date `$override`." );
			}
			$timestamp = $dateTime->getTimestamp();
			// Make sure it's not an expired override.
			if ( $timestamp > time() ) {
				return $timestamp;
			}
		}

		$permanentOverride = $ui->getPermanentOverride();
		if ( $permanentOverride === null ) {
			return null;
		}

		$date = $permanentOverride . '/' . date( 'Y' );
		$dateTime = DateTime::createFromFormat( 'd/m/Y', $date );
		if ( !$dateTime ) {
			throw new ConfigException( "Invalid override-perm date `$date`." );
		}
		return $dateTime->getTimestamp();
	}

	/**
	 * Get the next valid timestamp for the given user
	 *
	 * @param string $user
	 * @return int
	 * @suppress PhanPluginComparisonObjectOrdering DateTime objects can be compared (phan issue #2907)
	 */
	public function getNextTimestamp( string $user ): int {
		$userInfo = $this->getUserInfo( $user );
		$now = new DateTime();

		$ts = $this->getOverrideTimestamp( $userInfo ) ?? self::getValidFlagTimestamp( $userInfo );
		$date = ( new DateTime )->setTimestamp( $ts );

		// @phan-suppress-next-line PhanPossiblyInfiniteLoop
		while ( $date <= $now ) {
			$date->modify( '+1 year' );
		}
		return $date->getTimestamp();
	}

	/**
	 * Get the valid timestamp for the given groups
	 *
	 * @param UserInfo $userInfo
	 * @return int
	 */
	public static function getValidFlagTimestamp( UserInfo $userInfo ): int {
		$groups = $userInfo->getGroupsWithDates();

		$checkuser = 0;
		if ( isset( $groups['checkuser'] ) ) {
			$checkuserDate = DateTime::createFromFormat( 'd/m/Y', $groups['checkuser'] );
			if ( !$checkuserDate ) {
				throw new ConfigException( "Invalid checkuser date `{$groups['checkuser']}`." );
			}
			$checkuser = $checkuserDate->getTimestamp();
		}
		$bureaucrat = 0;
		if ( isset( $groups['bureaucrat'] ) ) {
			$bureaucratDate = DateTime::createFromFormat( 'd/m/Y', $groups['bureaucrat'] );
			if ( !$bureaucratDate ) {
				throw new ConfigException( "Invalid bureaucrat date `{$groups['bureaucrat']}`." );
			}
			$bureaucrat = $bureaucratDate->getTimestamp();
		}

		$timestamp = max( $bureaucrat, $checkuser );
		if ( $timestamp === 0 ) {
			$sysopDate = DateTime::createFromFormat( 'd/m/Y', $groups['sysop'] );
			if ( !$sysopDate ) {
				throw new ConfigException( "Invalid sysop date `{$groups['sysop']}`." );
			}
			$timestamp = $sysopDate->getTimestamp();
		}
		return $timestamp;
	}

	/**
	 * An override is considered expired if:
	 * - The override date has passed (that's the point of having an override), AND
	 * - The "normal" date has passed (otherwise we'd use two different dates for the same year)
	 * For decreased risk, we add an additional delay of 3 days.
	 *
	 * @param UserInfo $userInfo
	 * @return bool
	 */
	public static function isOverrideExpired( UserInfo $userInfo ): bool {
		$override = $userInfo->getOverride();
		if ( $override === null ) {
			return false;
		}

		$flagTS = self::getValidFlagTimestamp( $userInfo );
		$usualTS = strtotime( date( 'Y' ) . '-' . date( 'm-d', $flagTS ) );
		$overrideDate = DateTime::createFromFormat( 'd/m/Y', $override );
		if ( !$overrideDate ) {
			throw new ConfigException( "Invalid override date `$override`." );
		}
		$overrideTS = $overrideDate->getTimestamp();
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
		$stringKeys = [ 'sysop', 'checkuser', 'bureaucrat', 'override', 'override-perm' ];
		$allowedKeys = [ ...$stringKeys, 'aliases' ];
		$decoded = json_decode( $this->getContent(), true, 512, JSON_THROW_ON_ERROR );
		if ( !is_array( $decoded ) ) {
			throw new ConfigException( "Admin list is not a list..." );
		}
		foreach ( $decoded as $user => $data ) {
			if ( !is_string( $user ) ) {
				throw new ConfigException( "Invalid key `$user` in the admin list." );
			}
			$extraneousKeys = array_diff( array_keys( $data ), $allowedKeys );
			if ( $extraneousKeys ) {
				throw new ConfigException( "Extraneous keys for user `$user`: " . implode( ', ', $extraneousKeys ) );
			}
			if ( !isset( $data['sysop'] ) ) {
				throw new ConfigException( "Missing sysop date for user $user." );
			}
			foreach ( $stringKeys as $stringKey ) {
				if ( isset( $data[$stringKey] ) && !is_string( $data[$stringKey] ) ) {
					throw new ConfigException(
						"Invalid value `{$data[$stringKey]}` for key `$stringKey` and user $user."
					);
				}
			}
			if ( isset( $data['aliases'] ) ) {
				$aliases = $data['aliases'];
				if ( !is_array( $aliases ) || !array_is_list( $aliases ) ) {
					throw new ConfigException( "Invalid aliases format for $user." );
				}
				$stringAliases = array_filter( $aliases, 'is_string' );
				if ( $stringAliases !== $aliases ) {
					throw new ConfigException( "Non-string aliases for $user." );
				}
			}
		}

		return $decoded;
	}
}
