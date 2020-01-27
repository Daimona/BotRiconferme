<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Config;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageBotList;

/**
 * Class representing a single user. NOTE: this can only represent users stored in the JSON list
 */
class User extends Element {
	/** @var string */
	private $name;
	/** @var string[]|null Info contained in the JSON page */
	private $info;
	/** @var Wiki */
	private $wiki;

	/**
	 * @param string $name
	 * @param Wiki $wiki
	 */
	public function __construct( string $name, Wiki $wiki ) {
		$this->wiki = $wiki;
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
	 * Like getGroups(), but includes flag dates.
	 *
	 * @return string[] [ group => date ]
	 */
	public function getGroupsWithDates() : array {
		return array_intersect_key( $this->getUserInfo(), array_fill_keys( $this->getGroups(), 1 ) );
	}

	/**
	 * Get some info about this user, including flag dates.
	 *
	 * @return string[]
	 */
	public function getUserInfo() : array {
		if ( $this->info === null ) {
			$usersList = PageBotList::get( $this->wiki )->getAdminsList();
			$this->info = $usersList[ $this->name ]->getUserInfo();
		}
		return $this->info;
	}

	/**
	 * @param array|null $info
	 */
	public function setInfo( ?array $info ) : void {
		$this->info = $info;
	}

	/**
	 * Whether the user is in the given group
	 *
	 * @param string $groupName
	 * @return bool
	 */
	public function inGroup( string $groupName ) : bool {
		return in_array( $groupName, $this->getGroups(), true );
	}

	/**
	 * Returns a regex for matching the name of this user
	 *
	 * @inheritDoc
	 */
	public function getRegex( string $delimiter = '/' ) : string {
		$bits = $this->getAliases();
		$bits[] = $this->name;
		$regexify = static function ( $el ) use ( $delimiter ) {
			return str_replace( ' ', '[ _]', preg_quote( $el, $delimiter ) );
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
	 * @return Page
	 */
	public function getTalkPage() : Page {
		return new Page( "User talk:{$this->name}", $this->wiki );
	}

	/**
	 * Get the default base page, e.g. WP:A/Riconferma annuale/XXX
	 * @return Page
	 */
	public function getBasePage() : Page {
		$prefix = Config::getInstance()->get( 'main-page-title' );
		return new Page( "$prefix/$this", $this->wiki );
	}

	/**
	 * Get an *existing* base page for this user. If no existing page is found, this will throw.
	 * Don't use this method if the page is allowed not to exist.
	 *
	 * @throws MissingPageException
	 * @return Page
	 */
	public function getExistingBasePage() : Page {
		$basePage = $this->getBasePage();
		if ( !$basePage->exists() ) {
			$basePage = null;
			$prefix = Config::getInstance()->get( 'main-page-title' );
			foreach ( $this->getAliases() as $alias ) {
				$altTitle = "$prefix/$alias";
				$altPage = new Page( $altTitle, $this->wiki );
				if ( $altPage->exists() ) {
					$basePage = $altPage;
					break;
				}
			}
			if ( $basePage === null ) {
				// We've tried hard enough.
				throw new MissingPageException( "Couldn't find base page for $this" );
			}
		}
		return $basePage;
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->name;
	}
}
