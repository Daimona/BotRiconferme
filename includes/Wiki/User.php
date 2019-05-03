<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Wiki\Page\PageBotList;

/**
 * Class representing a single user. NOTE this can only represent users stored in the JSON list
 */
class User extends Element {
	/** @var string */
	private $name;
	/** @var string[] */
	private $groups;

	/**
	 * @param string $name
	 */
	public function __construct( string $name ) {
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
		return array_keys( $this->getGroupsWithDates() );
	}

	/**
	 * Get a list of groups this user belongs to with flag dates,
	 * same format as the JSON list.
	 *
	 * @return array[]
	 */
	public function getGroupsWithDates() : array {
		if ( $this->groups === null ) {
			$usersList = PageBotList::get()->getAdminsList();
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
		return str_replace( ' ', '[ _]', preg_quote( $this->name ) );
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
}
