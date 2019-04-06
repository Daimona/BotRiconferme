<?php declare( strict_types=1 );

namespace BotRiconferme;

/**
 * Represents a single on-wiki page
 */
class Page {
	/** @var string */
	protected $title;
	/** @var WikiController */
	protected $controller;
	/** @var string */
	protected $content;

	/**
	 * @param string $title
	 */
	public function __construct( string $title ) {
		$this->title = $title;
		$this->controller = new WikiController();
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
	 * For easier logging etc.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getTitle();
	}
}
