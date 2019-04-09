<?php declare( strict_types=1 );

namespace BotRiconferme\Page;

use BotRiconferme\Logger;
use BotRiconferme\WikiController;

/**
 * Represents a single on-wiki page
 */
class Page {
	/** @var string */
	protected $title;
	/** @var WikiController */
	protected $controller;
	/** @var string|null */
	protected $content;

	/**
	 * @param string $title
	 * @param string $domain The site where the page lives, if different from default
	 */
	public function __construct( string $title, string $domain = DEFAULT_URL ) {
		$this->title = $title;
		$this->controller = new WikiController( $domain );
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
			$this->content = $this->controller->getPageContent( $this->title, $section );
		}
		return $this->content;
	}

	/**
	 * Edit this page and update content
	 *
	 * @param array $params
	 */
	public function edit( array $params ) {
		$params = [
			'title' => $this->getTitle()
		] + $params;

		$this->controller->editPage( $params );
		if ( isset( $params['text'] ) ) {
			$this->content = $params['text'];
		} elseif ( isset( $params['appendtext'] ) ) {
			$this->content .= $params['appendtext'];
		} else {
			// Clear the cache anyway
			( new Logger )->warning( 'Resetting content cache. Params: ' . var_export( $params, true ) );
			$this->content = null;
		}
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
	 * regex doesn't match.
	 *
	 * @param string $regex
	 * @return string[]
	 * @throws \Exception
	 */
	public function getMatch( string $regex ) : array {
		$ret = [];
		if ( preg_match( $regex, $this->getContent(), $ret ) === 0 ) {
			throw new \Exception( 'The content does not match the given regex' );
		}
		return $ret;
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
