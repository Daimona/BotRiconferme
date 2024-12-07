<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Utils\IRegexable;
use BotRiconferme\Wiki\Page\Exception\MissingMatchException;
use BotRiconferme\Wiki\Wiki;
use InvalidArgumentException;

/**
 * Represents a single on-wiki page
 */
class Page implements IRegexable {
	protected ?string $content = null;
	/** @var array<int,string> */
	protected array $sectionContents = [];

	public function __construct(
		protected string $title,
		protected Wiki $wiki
	) {
	}

	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the content of this page
	 */
	public function getContent(): string {
		if ( $this->content === null ) {
			$this->content = $this->wiki->getPageContent( $this->title );
		}
		return $this->content;
	}

	/**
	 * Get the content of the given section of this page
	 */
	public function getSectionContent( int $section ): string {
		if ( !isset( $this->sectionContents[$section] ) ) {
			$this->sectionContents[$section] = $this->wiki->getPageSectionContent( $this->title, $section );
		}
		return $this->sectionContents[$section];
	}

	/**
	 * Edit this page and update content
	 *
	 * @param array<int|string|bool> $params
	 */
	public function edit( array $params ): void {
		$params = [
			'title' => $this->getTitle()
		] + $params;

		$this->wiki->editPage( $params );
		if ( isset( $params['text'] ) ) {
			$this->content = $params['text'];
		} elseif ( isset( $params['appendtext'] ) ) {
			$this->content .= $params['appendtext'];
		} elseif ( isset( $params['prependtext'] ) ) {
			$this->content = $params['prependtext'] . $this->content;
		} else {
			throw new InvalidArgumentException(
				'Unrecognized text param for edit. Params: ' . var_export( $params, true )
			);
		}
	}

	/**
	 * Whether this page exists
	 */
	public function exists(): bool {
		$pages = $this->wiki->getRequestFactory()->createStandaloneRequest( [
			'action' => 'query',
			'titles' => $this->getTitle()
		] )->executeAsQuery();
		return !isset( $pages->current()->missing );
	}

	/**
	 * Check whether the page content is matched by the given regex
	 */
	public function matches( string $regex ): bool {
		return (bool)preg_match( $regex, $this->getContent() );
	}

	/**
	 * Get the matches from a preg_match on the page content, and throws if the
	 * regex doesn't match. Check $this->matches() first.
	 *
	 * @param string $regex
	 * @return string[]
	 * @throws MissingMatchException
	 */
	public function getMatch( string $regex ): array {
		$ret = [];
		if ( preg_match( $regex, $this->getContent(), $ret ) === 0 ) {
			throw new MissingMatchException( "The content of $this does not match the given regex $regex" );
		}
		return $ret;
	}

	/**
	 * Returns a regex for matching the title of this page
	 *
	 * @inheritDoc
	 */
	public function getRegex( string $delimiter = '/' ): string {
		return str_replace( ' ', '[ _]', preg_quote( $this->title, $delimiter ) );
	}

	/**
	 * For easier logging etc.
	 */
	public function __toString(): string {
		return $this->getTitle();
	}
}
