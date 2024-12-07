<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Config;
use BotRiconferme\Request\Exception\MissingPageException;
use BotRiconferme\Utils\IRegexable;
use BotRiconferme\Wiki\Page\Page;
use Stringable;

/**
 * Class representing a single user. NOTE: this can only represent users stored in the JSON list
 */
readonly class User implements IRegexable, Stringable {
	private string $name;

	public function __construct(
		private UserInfo $userInfo,
		private Wiki $wiki
	) {
		$this->name = $userInfo->getName();
	}

	public function getName(): string {
		return $this->name;
	}

	/**
	 * Get a list of groups this user belongs to
	 *
	 * @return string[]
	 */
	public function getGroups(): array {
		return $this->userInfo->getGroupNames();
	}

	/**
	 * Like getGroups(), but includes flag dates.
	 *
	 * @return string[] [ group => date ]
	 */
	public function getGroupsWithDates(): array {
		return $this->userInfo->getGroupsWithDates();
	}

	/**
	 * Whether the user is in the given group
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
		$regexify = static fn ( string $el ): string => str_replace( ' ', '[ _]', preg_quote( $el, $delimiter ) );
		return '(?:' . implode( '|', array_map( $regexify, $bits ) ) . ')';
	}

	/**
	 * Get a list of aliases for this user.
	 *
	 * @return string[]
	 */
	public function getAliases(): array {
		return $this->userInfo->getAliases();
	}

	public function getTalkPage(): Page {
		return new Page( "User talk:{$this->name}", $this->wiki );
	}

	/**
	 * Get the default base page, e.g. WP:A/Riconferma annuale/XYZ
	 */
	public function getBasePage(): Page {
		$prefix = Config::getInstance()->get( 'main-page-title' );
		return new Page( "$prefix/$this", $this->wiki );
	}

	/**
	 * Get an *existing* base page for this user. If no existing page is found, this will throw.
	 * Don't use this method if the page is allowed not to exist.
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

	public function __toString(): string {
		return $this->name;
	}
}
