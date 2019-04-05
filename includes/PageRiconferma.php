<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Request\RequestBase;

/**
 * Represents a single riconferma page
 */
class PageRiconferma {
	/** @var string */
	private $title;
	/** @var WikiController */
	private $controller;
	/** @var string */
	private $content;

	/**
	 * @param string $title
	 * @param WikiController $controller
	 */
	public function __construct( string $title, WikiController $controller ) {
		$this->title = $title;
		$this->controller = $controller;
	}

	/**
	 * @return string
	 */
	public function getTitle() : string {
		return $this->title;
	}

	/**
	 * Get the name of the user from the title
	 *
	 * @return string
	 */
	public function getUser() : string {
		return explode( '/', $this->title )[2];
	}

	/**
	 * Returns the progressive number in the title
	 *
	 * @return int
	 */
	public function getNum() : int {
		$bits = explode( '/', $this->getTitle() );
		return intval( end( $bits ) );
	}

	/**
	 * Get the last part of the title as Username/Num
	 *
	 * @return string
	 */
	public function getUserNum() : string {
		return explode( '/', $this->getTitle(), 3 )[2];
	}

	/**
	 * Strip the part with the progressive number
	 *
	 * @return string
	 */
	public function getBaseTitle() : string {
		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal Phan bug
		return substr( $this->getTitle(), 0, strrpos( $this->getTitle(), '/' ) );
	}

	/**
	 * Get the content of this page
	 *
	 * @return string
	 */
	public function getContent() : string {
		if ( $this->content === null ) {
			$this->content = $this->controller->getPageContent( $this->title );
		}
		return $this->content;
	}

	/**
	 * Whether this page has enough opposing votes
	 *
	 * @return bool
	 */
	public function hasOpposition() : bool {
		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $this->title,
			'rvprop' => 'content',
			'rvslots' => 'main',
			'rvsection' => 4
		];
		$res = RequestBase::newFromParams( $params )->execute();
		$page = reset( $res->query->pages );
		$content = $page->revisions[0]->slots->main->{ '*' };
		// Let's hope that this is good enough...
		$votes = substr_count( $content, "\n\# *(?![#*])" );
		return $votes >= 15;
	}

	/**
	 * Whether this page is a vote
	 *
	 * @return bool
	 */
	public function isVote() : bool {
		$sectionReg = '/<!-- SEZIONE DA UTILIZZARE PER/';
		return preg_match( $sectionReg, $this->getContent() ) === false;
	}

	/**
	 * Get the end time
	 *
	 * @return int
	 */
	public function getEndTimestamp() : int {
		if ( $this->isVote() ) {
			$matches = [];
			$reg = "!La votazione ha inizio il.+ e ha termine.+ '''([^']+)''' alle ore '''([^']+)'''!";
			preg_match( $reg, $this->getContent(), $matches );
			list( , $day, $hours ) = $matches;
			$day = preg_replace( '![^\d \w]!', '', $day );
			return WikiController::getTimestampFromLocalTime( $day . " alle " . $hours );
		} else {
			$created = $this->controller->getPageCreationTS( $this->title );
			return $created + 60 * 60 * 24 * 7;
		}
	}

	public function __toString() {
		return $this->getTitle();
	}
}
