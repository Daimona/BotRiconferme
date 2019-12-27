<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Wiki\Page\PageBotList;

/**
 * Class representing a single user. NOTE this can only represent users stored in the JSON list
 */
class User extends Element {
	/** @var string */
	private $name;
	/** @var string[]|null */
	private $groups;

	/**
	 * @param string $name
	 * @param Wiki $wiki
	 */
	public function __construct( string $name, Wiki $wiki ) {
		parent::__construct( $wiki );
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() : string {
		return $this->name;
	}

	/**
	 * Get a list of groups this user belongs to
	 *
	 * @return string[]
	 */
	public function getGroups() : array {
		return array_diff( array_keys( $this->getUserInfo() ), PageBotList::NON_GROUP_KEYS );
	}

	/**
	 * Get some info about this user, including flag dates.
	 *
	 * @return string[]
	 */
	public function getUserInfo() : array {
		if ( $this->groups === null ) {
			$usersList = PageBotList::get( $this->wiki )->getAdminsList();
			$this->groups = $usersList[ $this->name ];
		}
		return $this->groups;
	}

	/**
	 * Whether the user is in the given group
	 *
	 * @param string $groupName
	 * @return bool
	 */
	public function inGroup( string $groupName ) : bool {
		return in_array( $groupName, $this->getGroups() );
	}

	/**
	 * Returns a regex for matching the name of this user
	 *
	 * @inheritDoc
	 */
	public function getRegex() : string {
		$bits = $this->getAliases();
		$bits[] = $this->name;
		$regexify = function ( $el ) {
			return str_replace( ' ', '[ _]', preg_quote( $el ) );
		};
		return '(?:' . implode( '|', array_map( $regexify, $bits ) ) . ')';
	}

	/**
	 * Get a list of aliases for this user.
	 *
	 * @return string[]
	 */
	public function getAliases() : array {
		return $this->getUserInfo()['aliases'] ?? [];
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
}
