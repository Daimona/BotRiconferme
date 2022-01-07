<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Config;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Utils\IRegexable;
use BotRiconferme\Wiki\Page\Page;
use Stringable;

/**
 * Class representing a single user. NOTE: this can only represent users stored in the JSON list
 */
class User implements IRegexable, Stringable {
	/** @var string */
	private $name;
	/** @var Wiki */
	private $wiki;
	/** @var UserInfo */
	private $ui;

	/**
	 * @param UserInfo $ui
	 * @param Wiki $wiki
	 */
	public function __construct( UserInfo $ui, Wiki $wiki ) {
		$this->wiki = $wiki;
		$this->name = $ui->getName();
		$this->ui = $ui;
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get a list of groups this user belongs to
	 *
	 * @return string[]
	 */
	public function getGroups(): array {
		return $this->ui->extractGroups();
	}

	/**
	 * Like getGroups(), but includes flag dates.
	 *
	 * @return string[] [ group => date ]
	 */
	public function getGroupsWithDates(): array {
		return $this->ui->extractGroupsWithDates();
	}

	/**
	 * Whether the user is in the given group
	 *
	 * @param string $groupName
	 * @return bool
	 */
	public function inGroup( string $groupName ): bool {
		return in_array( $groupName, $this->getGroups(), true );
	}

	/**
	 * Returns a regex for matching the name of this user
	 *
	 * @inheritDoc
	 */
	public function getRegex( string $delimiter = '/' ): string {
		$bits = $this->getAliases();
		$bits[] = $this->name;
		$regexify = static function ( string $el ) use ( $delimiter ): string {
			return str_replace( ' ', '[ _]', preg_quote( $el, $delimiter ) );
		};
		return '(?:' . implode( '|', array_map( $regexify, $bits ) ) . ')';
	}

	/**
	 * Get a list of aliases for this user.
	 *
	 * @return string[]
	 */
	public function getAliases(): array {
		return $this->ui->getAliases();
	}

	/**
	 * @return Page
	 */
	public function getTalkPage(): Page {
		return new Page( "User talk:{$this->name}", $this->wiki );
	}

	/**
	 * Get the default base page, e.g. WP:A/Riconferma annuale/XXX
	 * @return Page
	 */
	public function getBasePage(): Page {
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
	public function getExistingBasePage(): Page {
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
	public function __toString(): string {
		return $this->name;
	}
}
