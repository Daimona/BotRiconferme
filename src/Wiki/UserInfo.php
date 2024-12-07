<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Value object containing the data about a User that is stored in the list page.
 * @phan-type RawList = array{sysop:string,checkuser?:string,bureaucrat?:string,override?:string,override-perm?:string,aliases?:list<string>}
 */
readonly class UserInfo {
	private const GROUP_KEYS = [ 'sysop', 'bureaucrat', 'checkuser' ];

	/**
	 * @phan-param RawList $info
	 */
	public function __construct(
		private string $name,
		private array $info
	) {
	}

	public function getName(): string {
		return $this->name;
	}

	/**
	 * @return array
	 * @phan-return RawList
	 */
	public function getInfoArray(): array {
		return $this->info;
	}

	/**
	 * @return string[]
	 */
	public function getGroupNames(): array {
		return array_keys( $this->getGroupsWithDates() );
	}

	/**
	 * @return array<string,string>
	 */
	public function getGroupsWithDates(): array {
		// @phan-suppress-next-line PhanPartialTypeMismatchReturn Not fully inferring the type.
		return array_intersect_key( $this->info, array_fill_keys( self::GROUP_KEYS, 1 ) );
	}

	/**
	 * @return string[]
	 */
	public function getAliases(): array {
		return $this->info['aliases'] ?? [];
	}

	public function getOverride(): ?string {
		return $this->info['override'] ?? null;
	}

	public function getPermanentOverride(): ?string {
		return $this->info['override-perm'] ?? null;
	}

	public function withAddedAlias( string $alias ): self {
		$newInfo = $this->info;
		$newInfo['aliases'] = array_values( array_unique( array_merge( $newInfo['aliases'] ?? [], [ $alias ] ) ) );
		return new self( $this->name, $newInfo );
	}

	public function withoutOverride(): self {
		$newInfo = $this->info;
		unset( $newInfo['override'] );
		return new self( $this->name, $newInfo );
	}

	public function equals( self $other ): bool {
		if (
			$this->name !== $other->name ||
			array_diff_key( $this->info, $other->info ) ||
			count( $this->info ) !== count( $other->info )
		) {
			return false;
		}
		foreach ( $this->info as $key => $value ) {
			$otherValue = $other->info[$key];
			if ( is_array( $value ) && is_array( $otherValue ) ) {
				sort( $value );
				sort( $otherValue );
			}
			if ( $value !== $otherValue ) {
				return false;
			}
		}
		return true;
	}
}
