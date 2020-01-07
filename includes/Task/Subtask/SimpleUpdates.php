<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Wiki\Element;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\TaskResult;
use BotRiconferme\Wiki\User;

/**
 * Update various pages around, to be done for all closed procedures
 */
class SimpleUpdates extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$pages = $this->getDataProvider()->getPagesToClose();

		if ( !$pages ) {
			return TaskResult::STATUS_NOTHING;
		}

		$this->updateVotazioni( $pages );
		$this->updateNews( $pages );
		$this->updateAdminList( $this->getGroupOutcomes( 'sysop', $pages ) );
		$checkUsers = $this->getGroupOutcomes( 'checkuser', $pages );
		if ( $checkUsers ) {
			$this->updateCUList( $checkUsers );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * @param PageRiconferma[] $pages
	 * @see OpenUpdates::addToVotazioni()
	 */
	protected function updateVotazioni( array $pages ) : void {
		$this->getLogger()->info(
			'Updating votazioni: ' . implode( ', ', $pages )
		);
		$votePage = $this->getPage( $this->getOpt( 'vote-page-title' ) );

		$users = [];
		foreach ( $pages as $page ) {
			$users[] = $page->getUser();
		}
		$usersReg = Element::regexFromArray( $users, '!' );

		$search = "!^.+\{\{[^|}]*\/Riga\|[^|]*riconferma[^|]*\|utente=$usersReg\|.+\n!m";

		$newContent = preg_replace( $search, '', $votePage->getContent() );

		$summary = $this->msg( 'close-vote-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$votePage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * @param array $pages
	 * @see OpenUpdates::addToNews()
	 */
	protected function updateNews( array $pages ) : void {
		$simpleAmount = $voteAmount = 0;
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$voteAmount++;
			} else {
				$simpleAmount++;
			}
		}

		$this->getLogger()->info( "Updating news counter: -$simpleAmount simple, -$voteAmount votes." );

		$newsPage = $this->getPage( $this->getOpt( 'news-page-title' ) );

		$simpleReg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d*)(?=\s*[}|])!';
		$voteReg = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d*)(?=\s*[}|])!';

		$simpleMatches = $newsPage->getMatch( $simpleReg );
		$voteMatches = $newsPage->getMatch( $voteReg );

		$newSimp = ( (int)$simpleMatches[2] - $simpleAmount ) ?: '';
		$newVote = ( (int)$voteMatches[2] - $voteAmount ) ?: '';
		$newContent = preg_replace( $simpleReg, '${1}' . $newSimp, $newsPage->getContent() );
		$newContent = preg_replace( $voteReg, '${1}' . $newVote, $newContent );

		$summary = $this->msg( 'close-news-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$newsPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Update date on WP:Amministratori/Lista
	 *
	 * @param bool[] $outcomes
	 */
	protected function updateAdminList( array $outcomes ) : void {
		$this->getLogger()->info( 'Updating admin list' );
		$adminsPage = $this->getPage( $this->getOpt( 'admins-list-title' ) );
		$newContent = $adminsPage->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $outcomes as $username => $confirmed ) {
			$user = $this->getUser( $username );
			$userReg = $user->getRegex( '!' );
			$reg = "!({{Ammini\w+\/riga\|$userReg\|\D+\|\d{8}\|)(?:\d{8})?\|\d{8}((?:\|[a-z]*)?}}.*\n)!";
			if ( $confirmed ) {
				$nextDate = date( 'Ymd', $this->getNextTs( $user ) );
				$newContent = preg_replace(
					$reg,
					'${1}{{subst:#timel:Ymd}}|' . $nextDate . '$2',
					$newContent
				);
				$riconfNames[] = $username;
			} else {
				$newContent = preg_replace( $reg, '', $newContent );
				$removeNames[] = $username;
			}
		}

		$summary = $this->msg( 'close-update-list-summary' )
			->params( [
				'$riconf' => Message::commaList( $riconfNames ),
				'$remove' => Message::commaList( $removeNames )
			] )
			->text();

		$adminsPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Get the next valid timestamp for the given user
	 *
	 * @param User $user
	 * @return int
	 */
	private function getNextTs( User $user ) : int {
		$userInfo = $user->getUserInfo();
		if ( isset( $userInfo['override-perm'] ) ) {
			$date = \DateTime::createFromFormat(
				'd/m/Y',
				$userInfo['override-perm'] . '/' . date( 'Y' )
			);
		} else {
			$date = null;
			if ( isset( $userInfo['override'] ) ) {
				$date = \DateTime::createFromFormat( 'd/m/Y', $userInfo['override'] );
			}
			if ( !$date || $date <= new \DateTime ) {
				$ts = PageBotList::getValidFlagTimestamp( $userInfo );
				$date = ( new \DateTime )->setTimestamp( $ts );
			}
		}
		while ( $date <= new \DateTime ) {
			$date->modify( '+1 year' );
		}
		return $date->getTimestamp();
	}

	/**
	 * @param bool[] $outcomes
	 */
	protected function updateCUList( array $outcomes ) : void {
		$this->getLogger()->info( 'Updating CU list.' );
		$cuList = $this->getPage( $this->getOpt( 'cu-list-title' ) );
		$newContent = $cuList->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $outcomes as $user => $confirmed ) {
			$userReg = $this->getUser( $user )->getRegex( '!' );
			$reg = "!(\{\{ *Checkuser *\| *$userReg *\|[^}]+\| *)[\w \d]+(}}.*\n)!";
			if ( $confirmed ) {
				$newContent = preg_replace( $reg, '${1}{{subst:#time:j F Y}}$2', $newContent );
				$riconfNames[] = $user;
			} else {
				$newContent = preg_replace( $reg, '', $newContent );
				$removeNames[] = $user;
			}
		}

		$summary = $this->msg( 'cu-list-update-summary' )
			->params( [
				'$riconf' => Message::commaList( $riconfNames ),
				'$remove' => Message::commaList( $removeNames )
			] )
			->text();

		$cuList->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Given a user group and an array of PageRiconferma, get an array of users from $pages
	 * which are in the given groups and the outcome of the procedure (true = confirmed)
	 * @param string $group
	 * @param PageRiconferma[] $pages
	 * @return bool[]
	 */
	private function getGroupOutcomes( string $group, array $pages ) : array {
		$ret = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			if ( $user->inGroup( $group ) ) {
				$ret[ $user->getName() ] = !( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL );
			}
		}
		return $ret;
	}
}
