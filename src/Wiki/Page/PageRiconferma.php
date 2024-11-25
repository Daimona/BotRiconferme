<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page;

use BadMethodCallException;
use BotRiconferme\Exception\MissingMatchException;
use BotRiconferme\Message\Message;
use LogicException;

/**
 * Represents a single riconferma page
 */
class PageRiconferma extends Page {
	/** Sections of the page, value = section number. Loaded in self::defineSections */
	private ?int $supportSection;
	private ?int $opposeSection;
	/** @var int[] Counts of votes for each section */
	private array $sectionCounts = [];

	// Possible outcomes of a vote
	public const OUTCOME_OK = 0;
	public const OUTCOME_FAIL_VOTES = 1;
	public const OUTCOME_NO_QUOR = 2;
	public const OUTCOME_FAIL = self::OUTCOME_FAIL_VOTES | self::OUTCOME_NO_QUOR;

	// Values depending on bureaucracy
	public const REQUIRED_OPPOSE_MAX = 15;
	public const REQUIRED_OPPOSE_QUORUM_RATIO = 1 / 4;
	public const SIMPLE_DURATION = 7;
	public const VOTE_DURATION = 14;
	public const SUCCESS_RATIO = 2 / 3;

	/**
	 * Define the numbers of the support and oppose sections. These are lazy-loaded
	 * because they can vary depending on whether the page is a vote, which is relatively
	 * expensive to know since it requires parsing the content of the page.
	 */
	private function defineSections(): void {
		if ( $this->supportSection !== null ) {
			return;
		}
		$this->supportSection = $this->isVote() ? 3 : 0;
		$this->opposeSection = $this->isVote() ? 4 : 3;
	}

	private function getSupportSection(): int {
		$this->defineSections();
		return $this->supportSection;
	}

	private function getOpposeSection(): int {
		$this->defineSections();
		return $this->opposeSection;
	}

	/**
	 * Get the name of the user from the title
	 *
	 * @return string
	 */
	public function getUserName(): string {
		return explode( '/', $this->title )[2];
	}

	/**
	 * Returns the progressive number in the title
	 *
	 * @return int
	 */
	public function getNum(): int {
		$bits = explode( '/', $this->getTitle() );
		return (int)end( $bits );
	}

	/**
	 * Get the last part of the title as Username/Num
	 *
	 * @return string
	 */
	public function getUserNum(): string {
		return explode( '/', $this->getTitle(), 3 )[2];
	}

	/**
	 * Get the amount of opposing votes
	 *
	 * @return int
	 */
	public function getOpposingCount(): int {
		return $this->getCountForSection( $this->getOpposeSection() );
	}

	/**
	 * Get the amount support votes
	 *
	 * @return int
	 * @throws BadMethodCallException
	 */
	public function getSupportCount(): int {
		if ( !$this->isVote() ) {
			throw new BadMethodCallException( 'Cannot get support for a non-vote page.' );
		}
		return $this->getCountForSection( $this->getSupportSection() );
	}

	/**
	 * Count the votes in the given section
	 *
	 * @param int $secNum
	 * @return int
	 */
	protected function getCountForSection( int $secNum ): int {
		if ( !isset( $this->sectionCounts[ $secNum ] ) ) {
			$content = $this->wiki->getPageContent( $this->title, $secNum );
			// Let's hope that this is good enough...
			$voteCount = preg_match_all( "/^# *(?![# *:]|\.\.\.$)/m", $content );
			if ( $voteCount === false ) {
				throw new MissingMatchException( "Can't figure out vote count for $this->title#$secNum." );
			}
			$this->sectionCounts[$secNum] = $voteCount;
		}
		return $this->sectionCounts[$secNum];
	}

	/**
	 * Gets the quorum used for the current page
	 *
	 * @return int
	 */
	protected function getQuorum(): int {
		$reg = "!soddisfare il \[\[[^|\]]+\|quorum]] di '''(\d+) voti'''!";
		return (int)$this->getMatch( $reg )[1];
	}

	/**
	 * Whether this page has enough opposing votes
	 *
	 * @return bool
	 */
	public function hasOpposition(): bool {
		$req = min(
			self::REQUIRED_OPPOSE_MAX,
			ceil( $this->getQuorum() * self::REQUIRED_OPPOSE_QUORUM_RATIO )
		);
		return $this->getOpposingCount() >= $req;
	}

	/**
	 * Gets the outcome for the vote
	 *
	 * @return int One of the OUTCOME_* constants
	 */
	public function getOutcome(): int {
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
	 * @throws BadMethodCallException
	 * @throws LogicException
	 */
	public function getOutcomeText(): string {
		if ( !$this->isVote() ) {
			throw new BadMethodCallException( 'No need for an outcome text.' );
		}

		$text = sprintf(
			' Con %d voti a favore e %d contrari',
			$this->getSupportCount(),
			$this->getOpposingCount()
		);
		$user = $this->getUserName();

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
				throw new LogicException( 'Invalid outcome: ' . $this->getOutcome() );
		}
		return $text;
	}

	/**
	 * Whether this page is a vote
	 *
	 * @return bool
	 */
	public function isVote(): bool {
		$sectionReg = '/<!-- SEZIONE DA UTILIZZARE PER/';
		return !$this->matches( $sectionReg );
	}

	/**
	 * Get the timestamp of the creation of the page
	 *
	 * @return int
	 */
	public function getCreationTimestamp(): int {
		return $this->wiki->getPageCreationTS( $this->title );
	}

	/**
	 * Get the end time
	 *
	 * @return int
	 */
	public function getEndTimestamp(): int {
		if ( $this->isVote() ) {
			$reg = "!La votazione ha inizio il.+ e ha termine il (\d+ \w+ \d+) alle ([\d:]+)!";
			[ , $day, $hours ] = $this->getMatch( $reg );
			$day = preg_replace( '![^ \w]!', '', $day );
			return Message::getTimestampFromLocalTime( "$day $hours" );
		}
		return $this->getCreationTimestamp() + 60 * 60 * 24 * self::SIMPLE_DURATION;
	}
}
