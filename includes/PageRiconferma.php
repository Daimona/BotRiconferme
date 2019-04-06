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

	// Sections of the page value is section number
	const SECTION_SUPPORT = 3;
	const SECTION_OPPOSE = 4;

	// Possible outcomes of a vote
	const OUTCOME_OK = 0;
	const OUTCOME_FAIL_VOTES = 1;
	const OUTCOME_NO_QUOR = 2;
	const OUTCOME_FAIL = self::OUTCOME_FAIL_VOTES | self::OUTCOME_NO_QUOR;

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
	 * Get the amount of opposing votes
	 *
	 * @return int
	 */
	public function getOpposingCount() : int {
		return $this->getCountForSection( self::SECTION_OPPOSE );
	}

	/**
	 * Get the amount support votes
	 *
	 * @return int
	 */
	public function getSupportCount() : int {
		return $this->getCountForSection( self::SECTION_SUPPORT );
	}

	/**
	 * Count the votes in the given section
	 *
	 * @param int $secNum
	 * @return int
	 */
	protected function getCountForSection( int $secNum ) : int {
		$content = $this->controller->getPageContent( $this->title, $secNum );
		// Let's hope that this is good enough...
		return substr_count( $content, "\n\# *(?![#*])" );
	}

	/**
	 * Gets the quorum used for the current page
	 *
	 * @return int
	 */
	protected function getQuorum() : int {
		$reg = "!soddisfare il \[\[[^|\]]+\|quorum]] di '''(\d+) voti'''!";
		$matches = [];
		preg_match( $reg, $this->getContent(), $matches );
		return intval( $matches[1] );
	}

	/**
	 * Whether this page has enough opposing votes
	 *
	 * @return bool
	 */
	public function hasOpposition() : bool {
		return $this->getOpposingCount() >= 15;
	}

	/**
	 * Gets the outcome for the vote
	 *
	 * @return int One of the OUTCOME_* constants
	 * @throws \BadMethodCallException
	 */
	public function getOutcome() : int {
		if ( !$this->isVote() ) {
			throw new \BadMethodCallException( 'Cannot get outcome for a non-vote page.' );
		}
		$totalVotes = $this->getOpposingCount() + $this->getSupportCount();
		if ( $this->getSupportCount() < $this->getQuorum() ) {
			return self::OUTCOME_NO_QUOR;
		} elseif ( $this->getSupportCount() < 2 * $totalVotes / 3 ) {
			return self::OUTCOME_FAIL_VOTES;
		}
		return self::OUTCOME_OK;
	}

	/**
	 * Get the result text for the page itself
	 *
	 * @return string
	 * @throws \BadMethodCallException
	 */
	public function getOutcomeText() : string {
		if ( !$this->isVote() ) {
			throw new \BadMethodCallException( 'No need for an outcome text.' );
		}

		$text = sprintf(
			' Con %d voti a favore e %d contrari',
			$this->getSupportCount(),
			$this->getOpposingCount()
		);
		$user = $this->getUser();

		switch ( $this->getOutcome() ) {
			case self::OUTCOME_OK:
				$text .= " $user viene riconfermato amministratore.";
				break;
			/** @noinspection PhpMissingBreakStatementInspection */
			case self::OUTCOME_NO_QUOR:
				$text .= ', non raggiungendo il quorum,';
				// Fall through intended
			case self::OUTCOME_FAIL:
				$text .= " $user non viene riconfermato amministratore";
				break;
		}
		return $text;
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
		}
	}
	public function __toString() {
		return $this->getTitle();
	}
}
