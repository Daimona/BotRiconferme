<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Wiki\Element;
use BotRiconferme\Wiki\Page\Page;
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
	 * @see UpdatesAround::addToVotazioni()
	 */
	protected function updateVotazioni( array $pages ) {
		$this->getLogger()->info(
			'Updating votazioni: ' . implode( ', ', $pages )
		);
		$votePage = new Page( $this->getConfig()->get( 'vote-page-title' ) );

		$users = [];
		foreach ( $pages as $page ) {
			$users[] = $page->getUser();
		}
		$usersReg = Element::regexFromArray( $users );

		$search = "!^.+\{\{Wikipedia:Wikipediano\/Votazioni\/Riga\|[^|]*riconferma[^|]*\|utente=$usersReg\|.+\n!m";

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
	 * @see UpdatesAround::addToNews()
	 */
	protected function updateNews( array $pages ) {
		$simpleAmount = $voteAmount = 0;
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$voteAmount++;
			} else {
				$simpleAmount++;
			}
		}

		$this->getLogger()->info(
			"Decreasing the news counter: $simpleAmount simple, $voteAmount votes."
		);

		$newsPage = new Page( $this->getConfig()->get( 'news-page-title' ) );

		$content = $newsPage->getContent();
		$simpleReg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d*)(?=\s*[}|])!';
		$voteReg = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d*)(?=\s*[}|])!';

		$simpleMatches = $newsPage->getMatch( $simpleReg );
		$voteMatches = $newsPage->getMatch( $voteReg );

		$newSimp = (int)$simpleMatches[2] - $simpleAmount ?: '';
		$newVote = (int)$voteMatches[2] - $voteAmount ?: '';
		$newContent = preg_replace( $simpleReg, '${1}' . $newSimp, $content );
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
	protected function updateAdminList( array $outcomes ) {
		$this->getLogger()->info( 'Updating admin list' );
		$adminsPage = new Page( $this->getConfig()->get( 'admins-list-title' ) );
		$newContent = $adminsPage->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $outcomes as $user => $confirmed ) {
			$userReg = ( new User( $user ) )->getRegex();
			$reg = "!(\{\{Amministratore\/riga\|$userReg.+\| *)\d+( *\|[ \w]*\}\}.*\n)!";
			if ( $confirmed ) {
				$newContent = preg_replace( $reg, '${1}{{subst:#time:Ymd|+1 year}}$2', $newContent );
				$riconfNames[] = $user;
			} else {
				$newContent = preg_replace( $reg, '', $newContent );
				$removeNames[] = $user;
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
	 * @param bool[] $outcomes
	 */
	protected function updateCUList( array $outcomes ) {
		$this->getLogger()->info( 'Updating CU list.' );
		$cuList = new Page( $this->getConfig()->get( 'cu-list-title' ) );
		$newContent = $cuList->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $outcomes as $user => $confirmed ) {
			$userReg = ( new User( $user ) )->getRegex();
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
