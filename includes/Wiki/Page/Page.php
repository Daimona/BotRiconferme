<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Exception\MissingMatchException;
use BotRiconferme\Utils\IRegexAble;
use BotRiconferme\Wiki\Wiki;

/**
 * Represents a single on-wiki page
 */
class Page implements IRegexAble {
	/** @var string */
	protected $title;
	/** @var string|null */
	protected $content;
	/** @var Wiki */
	protected $wiki;

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
	public function getTitle() : string {
		return $this->title;
	}

	/**
	 * Get the content of this page
	 *
	 * @param int|null $section A section number to retrieve the content of that section
	 * @return string
	 */
	public function getContent( int $section = null ) : string {
		if ( $this->content === null ) {
			$this->content = $this->wiki->getPageContent( $this->title, $section );
		}
		return $this->content;
	}

	/**
	 * Edit this page and update content
	 *
	 * @param array $params
	 * @throws \LogicException
	 */
	public function edit( array $params ) : void {
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
			throw new \LogicException(
				'Unrecognized text param for edit. Params: ' . var_export( $params, true )
			);
		}
	}

	/**
	 * Whether this page exists
	 *
	 * @return bool
	 */
	public function exists() : bool {
		$res = $this->wiki->getRequestFactory()->newFromParams( [
			'action' => 'query',
			'titles' => $this->getTitle()
		] )->execute();
		$pages = $res->query->pages;
		return !isset( reset( $pages )->missing );
	}

	/**
	 * Check whether the page content is matched by the given regex
	 *
	 * @param string $regex
	 * @return bool
	 */
	public function matches( string $regex ) : bool {
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
	public function getMatch( string $regex ) : array {
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
	public function getRegex( string $delimiter = '/' ) : string {
		return str_replace( ' ', '[ _]', preg_quote( $this->title, $delimiter ) );
	}

	/**
	 * For easier logging etc.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getTitle();
	}
}
