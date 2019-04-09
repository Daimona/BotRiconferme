<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Wiki\Page\PageBotList;

/**
 * Class representing a single user.
 */
class User {
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
	 * @return string[]
	 */
	public function getGroups() : array {
		if ( $this->groups === null ) {
			$usersList = PageBotList::get()->getAdminsList();
			$this->groups = array_keys( $usersList[ $this->name ] );
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
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
}
