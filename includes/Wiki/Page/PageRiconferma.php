<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BotRiconferme\Message;
use BotRiconferme\Wiki\User;

/**
 * Represents a single riconferma page
 */
class PageRiconferma extends Page {
	// Sections of the page, value = section number. Loaded in self::defineSections
	private $supportSection;
	private $opposeSection;
	/** @var array Counts of votes for each section */
	private $sectionCounts = [];

	// Possible outcomes of a vote
	public const OUTCOME_OK = 0;
	public const OUTCOME_FAIL_VOTES = 1;
	public const OUTCOME_NO_QUOR = 2;
	public const OUTCOME_FAIL = self::OUTCOME_FAIL_VOTES | self::OUTCOME_NO_QUOR;

	// Values depending on bureaucracy
	public const REQUIRED_OPPOSE = 15;
	public const SIMPLE_DURATION = 7;
	public const VOTE_DURATION = 14;
	public const SUCCESS_RATIO = 2 / 3;

	/**
	 * Define the numbers of the support and oppose sections. These are lazy-loaded
	 * because they can vary depending on whether the page is a vote, which is relatively
	 * expensive to know since it requires parsing the content of the page.
	 */
	private function defineSections() {
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
		return substr( $this->getTitle(), 0, strrpos( $this->getTitle(), '/' ) );
	}

	/**
	 * Get the amount of opposing votes
	 *
	 * @return int
	 */
	public function getOpposingCount() : int {
		$this->defineSections();
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
		$this->defineSections();
		return $this->getCountForSection( $this->supportSection );
	}

	/**
	 * Count the votes in the given section
	 *
	 * @param int $secNum
	 * @return int
	 */
	protected function getCountForSection( int $secNum ) : int {
		if ( !isset( $this->sectionCounts[ $secNum ] ) ) {
			$content = $this->controller->getPageContent( $this->title, $secNum );
			// Let's hope that this is good enough...
			$this->sectionCounts[$secNum] = preg_match_all( "/^\# *(?![# *:]|\.\.\.$)/m", $content );
		}
		return $this->sectionCounts[$secNum];
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
		return $this->getOpposingCount() >= self::REQUIRED_OPPOSE;
	}

	/**
	 * Gets the outcome for the vote
	 *
	 * @return int One of the OUTCOME_* constants
	 */
	public function getOutcome() : int {
		if ( !$this->isVote() ) {
			return self::OUTCOME_OK;
		}
		$totalVotes = $this->getOpposingCount() + $this->getSupportCount();

		if ( $this->getSupportCount() < $this->getQuorum() ) {
			$ret = self::OUTCOME_NO_QUOR;
		} elseif ( $this->getSupportCount() < self::SUCCESS_RATIO * $totalVotes ) {
			$ret = self::OUTCOME_FAIL_VOTES;
		} else {
			$ret = self::OUTCOME_OK;
		}
		return $ret;
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
				// Fall-through intended
			case self::OUTCOME_FAIL:
				$text .= " $user non viene riconfermato amministratore.";
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
			$reg = "!La votazione ha inizio il.+ alle ore ([\d:]+) e ha termine il (.+) alla stessa ora!";
			list( , $hours, $day ) = $this->getMatch( $reg );
			$day = preg_replace( '![^\d \w]!', '', $day );
			return Message::getTimestampFromLocalTime( "$day $hours" );
		} else {
			return $this->getCreationTimestamp() + 60 * 60 * 24 * self::SIMPLE_DURATION;
		}
	}
}
