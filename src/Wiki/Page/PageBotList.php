<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Clock;
use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Wiki\UserInfo;
use BotRiconferme\Wiki\Wiki;
use DateTime;

/**
 * Singleton class representing the JSON list of admins
 * @todo Refactor: everything that deals with dates etc. (as opposed to the page) should go elsewhere.
 */
class PageBotList extends Page {
	private static ?self $instance = null;

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
		if ( self::$instance === null ) {
			self::$instance = new self( $listTitle, $wiki );
		}
		// @phan-suppress-next-line PhanPartialTypeMismatchReturn Type not inferred properly.
		return self::$instance;
	}

	/** @suppress PhanUnreferencedPublicMethod */
	public static function clearInstance(): void {
		self::$instance = null;
	}

	/**
	 * @param UserInfo $ui
	 * @return int|null
	 */
	public static function getOverrideTimestamp( UserInfo $ui ): ?int {
		[ 'override' => $override, 'override-perm' => $permanentOverride ] = self::getOverrideTimestamps( $ui );

		// A one-time override takes precedence, unless it's expired
		if ( $override !== null && !self::isOverrideExpired( $ui ) ) {
			return $override;
		}

		return $permanentOverride;
	}

	/**
	 * @param UserInfo $ui
	 * @return array<int|null>
	 * @phan-return array{override:?int,override-perm:?int}
	 */
	private static function getOverrideTimestamps( UserInfo $ui ): array {
		$ret = [ 'override' => null, 'override-perm' => null ];

		$override = $ui->getOverride();
		if ( $override !== null ) {
			$dateTime = DateTime::createFromFormat( '!d/m/Y', $override );
			if ( !$dateTime ) {
				throw new ConfigException( "Invalid override date `$override`." );
			}
			$ret['override'] = $dateTime->getTimestamp();
		}

		$permanentOverride = $ui->getPermanentOverride();
		if ( $permanentOverride !== null ) {
			$date = $permanentOverride . '/' . Clock::getDate( 'Y' );
			$dateTime = DateTime::createFromFormat( '!d/m/Y', $date );
			if ( !$dateTime ) {
				throw new ConfigException( "Invalid override-perm date `$date`." );
			}
			$ret['override-perm'] = $dateTime->getTimestamp();
		}

		return $ret;
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
		$now = Clock::dateTimeNow();

		[ 'override' => $override, 'override-perm' => $permanentOverride ] = self::getOverrideTimestamps( $userInfo );
		// Check that the override isn't expired, or that it's already been used this year.
		$hasExpiredOverride = $override !== null && self::isOverrideExpired( $userInfo );
		$usedOverrideThisYear = $override !== null && $override < $now->getTimestamp() &&
			date( 'Y', $override ) === $now->format( 'Y' );

		if ( $override && !$hasExpiredOverride && !$usedOverrideThisYear ) {
			$nextTS = $override;
		} else {
			$baseTS = $permanentOverride ?: self::getValidFlagTimestamp( $userInfo );
			$date = ( new DateTime )->setTimestamp( $baseTS );
			// Next date must be at least this year...
			while ( (int)$date->format( 'Y' ) < (int)$now->format( 'Y' ) ) {
				$date->modify( '+1 year' );
			}
			$dateIsPast = $date->format( 'Ymd' ) === $now->format( 'Ymd' ) || $date < $now;
			if ( $usedOverrideThisYear || $dateIsPast ) {
				// ... Or next year, if we've already had an override for this year, or if the date has passed
				// (including if it's today).
				$date->modify( '+1 year' );
			}
			$nextTS = $date->getTimestamp();
		}

		return $nextTS;
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
			$checkuserDate = DateTime::createFromFormat( '!d/m/Y', $groups['checkuser'] );
			if ( !$checkuserDate ) {
				throw new ConfigException( "Invalid checkuser date `{$groups['checkuser']}`." );
			}
			$checkuser = $checkuserDate->getTimestamp();
		}
		$bureaucrat = 0;
		if ( isset( $groups['bureaucrat'] ) ) {
			$bureaucratDate = DateTime::createFromFormat( '!d/m/Y', $groups['bureaucrat'] );
			if ( !$bureaucratDate ) {
				throw new ConfigException( "Invalid bureaucrat date `{$groups['bureaucrat']}`." );
			}
			$bureaucrat = $bureaucratDate->getTimestamp();
		}

		$timestamp = max( $bureaucrat, $checkuser );
		if ( $timestamp === 0 ) {
			$sysopDate = DateTime::createFromFormat( '!d/m/Y', $groups['sysop'] );
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
	 * - The "normal" date on the same year as the override date has passed (otherwise we'd use two different dates for
	 *   that year)
	 * A date is considered to have passed if at least 3 full days have passed, to reduce risk.
	 * @todo Worth considering a different approach? As it stands, override can only be used for dates in the same year.
	 * Unsure how this works exactly for days near the year's start/end.
	 *
	 * @param UserInfo $userInfo
	 * @return bool
	 */
	public static function isOverrideExpired( UserInfo $userInfo ): bool {
		[ 'override' => $override, 'override-perm' => $permanentOverride ] = self::getOverrideTimestamps( $userInfo );
		if ( $override === null ) {
			return false;
		}

		$usualTS = $permanentOverride ?? self::getValidFlagTimestamp( $userInfo );
		$usualTSOnOverrideYear = strtotime(
			Clock::getDate( 'Y', $override ) . '-' . Clock::getDate( 'm-d', $usualTS )
		);

		$delay = 60 * 60 * 24 * 3;
		$now = Clock::now();

		return $now > $usualTSOnOverrideYear + $delay && $now > $override + $delay;
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
	private function getDecodedContent(): array {
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
