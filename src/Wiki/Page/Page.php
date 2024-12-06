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
	protected string $title;
	protected ?string $content = null;
	/** @var array<int,string> */
	protected array $sectionContents = [];
	protected Wiki $wiki;

	/**
	 * @param string $title
	 * @param Wiki $wiki For the site where the page lives
	 */
	public function __construct( string $title, Wiki $wiki ) {
		$this->wiki = $wiki;
		$this->title = $title;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * Get the content of this page
	 *
	 * @return string
	 */
	public function getContent(): string {
		if ( $this->content === null ) {
			$this->content = $this->wiki->getPageContent( $this->title );
		}
		return $this->content;
	}

	/**
	 * Get the content of the given section of this page
	 *
	 * @param int $section
	 * @return string
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
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
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
	 *
	 * @return bool
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
	 *
	 * @param string $regex
	 * @return bool
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
	 *
	 * @return string
	 */
	public function __toString(): string {
		return $this->getTitle();
	}
}
