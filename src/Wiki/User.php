<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Config;
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
	 * Get the canonical base page (WP:A/Riconferma annuale/XYZ), always using the current username, even if
	 * a different alias exists.
	 */
	public function getCanonicalBasePage(): Page {
		$prefix = Config::getInstance()->get( 'main-page-title' );
		return new Page( "$prefix/$this", $this->wiki );
	}

	/**
	 * Get the base page for this user. The returned page may not exist.
	 */
	public function getBasePage(): Page {
		$basePage = $this->getCanonicalBasePage();
		if ( !$basePage->exists() ) {
			// Try aliases in case the user was renamed.
			$prefix = Config::getInstance()->get( 'main-page-title' );
			foreach ( $this->getAliases() as $alias ) {
				$altTitle = "$prefix/$alias";
				$altPage = new Page( $altTitle, $this->wiki );
				if ( $altPage->exists() ) {
					$basePage = $altPage;
					break;
				}
			}
		}
		// Even if no page exists, return the canonical version with the current username
		return $basePage;
	}

	public function __toString(): string {
		return $this->name;
	}
}
