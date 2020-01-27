<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

/**
 * Value object containing the data about a User that is stored in the list page
 */
class UserInfo {
	/** @var string */
	private $name;
	/** @var array */
	private $info;

	private const GROUP_KEYS = [ 'sysop', 'bureaucrat', 'checkuser' ];

	/**
	 * @param string $name
	 * @param array $info
	 */
	public function __construct( string $name, array $info ) {
		$this->name = $name;
		$this->info = $info;
	}

	/**
	 * @return string
	 */
	public function getName() : string {
		return $this->name;
	}

	/**
	 * @return array
	 */
	public function getInfo() : array {
		return $this->info;
	}

	/**
	 * @return array
	 */
	public function extractGroups() : array {
		return array_keys( $this->extractGroupsWithDates() );
	}

	/**
	 * @return array
	 */
	public function extractGroupsWithDates() : array {
		return array_intersect_key( $this->getInfo(), array_fill_keys( self::GROUP_KEYS, 1 ) );
	}

	/**
	 * @return array
	 */
	public function getAliases() : array {
		return $this->getInfo()['aliases'] ?? [];
	}
}
