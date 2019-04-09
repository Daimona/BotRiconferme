<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Message;
use BotRiconferme\Wiki\User;

/**
 * Represents a single riconferma page
 */
class PageRiconferma extends Page {
	// Sections of the page. Value = section number, and depends on whether the page is a vote
	private $supportSection = 3;
	private $opposeSection = 4;

	// Possible outcomes of a vote
	const OUTCOME_OK = 0;
	const OUTCOME_FAIL_VOTES = 1;
	const OUTCOME_NO_QUOR = 2;
	const OUTCOME_FAIL = self::OUTCOME_FAIL_VOTES | self::OUTCOME_NO_QUOR;

	/**
	 * @param string $title
	 */
	public function __construct( string $title ) {
		parent::__construct( $title );
		$this->supportSection = $this->isVote() ? 3 : 0;
		$this->opposeSection = $this->isVote() ? 4 : 3;
	}

	/**
	 * Get the name of the user from the title
	 *
	 * @return User
	 */
	public function getUser() : User {
		$name = explode( '/', $this->title )[2];
		return new User( $name );
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
	 * Get the amount of opposing votes
	 *
	 * @return int
	 */
	public function getOpposingCount() : int {
		return $this->getCountForSection( $this->opposeSection );
	}

	/**
	 * Get the amount support votes
	 *
	 * @return int
	 * @throws \BadMethodCallException
	 */
	public function getSupportCount() : int {
		if ( !$this->isVote() ) {
			throw new \BadMethodCallException( 'Cannot get support for a non-vote page.' );
		}
		return $this->getCountForSection( $this->supportSection );
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
		return intval( $this->getMatch( $reg )[1] );
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
	 * @throws \LogicException
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
			default:
				throw new \LogicException( 'Invalid outcome: ' . $this->getOutcome() );
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
		return !$this->matches( $sectionReg );
	}

	/**
	 * Get the timestamp of the creation of the page
	 *
	 * @return int
	 */
	public function getCreationTimestamp() : int {
		return $this->controller->getPageCreationTS( $this->title );
	}
	/**
	 * Get the end time
	 *
	 * @return int
	 */
	public function getEndTimestamp() : int {
		if ( $this->isVote() ) {
			$reg = "!La votazione ha inizio il.+ e ha termine.+ '''([^']+)''' alle ore '''([^']+)'''!";
			list( , $day, $hours ) = $this->getMatch( $reg );
			$day = preg_replace( '![^\d \w]!', '', $day );
			return Message::getTimestampFromLocalTime( $day . " alle " . $hours );
		} else {
			return $this->getCreationTimestamp() + 60 * 60 * 24 * 7;
		}
	}
}
