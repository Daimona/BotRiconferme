<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Wiki\Element;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\TaskResult;

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
		$this->updateAdminList( $pages );
		$this->updateCUList( $pages );

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

		$titleReg = Element::regexFromArray( $pages );
		$search = "!^\*.+ La \[\[$titleReg\|procedura]] termina.+\n!m";

		$newContent = preg_replace( $search, '', $votePage->getContent() );
		// Make sure the last line ends with a full stop in every section
		$simpleSectReg = '!(^;È in corso.+riconferma tacita.+amministrat.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$voteSectReg = '!(^;Si vota per la .+riconferma .+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $simpleSectReg, '$1.', $newContent );
		$newContent = preg_replace( $voteSectReg, '$1.', $newContent );

		// @fixme Remove empty sections, and add the "''Nessuna riconferma o votazione in corso''" message
		// if the page is empty! Or just wait for the page to be restyled...

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
	 * @param PageRiconferma[] $pages
	 */
	protected function updateAdminList( array $pages ) {
		$this->getLogger()->info(
			'Updating admin list: ' . implode( ', ', $pages )
		);
		$adminsPage = new Page( $this->getConfig()->get( 'admins-list-title' ) );
		$newContent = $adminsPage->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$reg = '!(\{\{Amministratore\/riga\|' . $user->getRegex() . ".+\| *)\d+( *\|[ \w]*\}\}.*\n)!";
			if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
				$newContent = preg_replace( $reg, '', $newContent );
				$removeNames[] = $user->getName();
			} else {
				$newContent = preg_replace( $reg, '${1}{{subst:#time:Ymd|+1 year}}$2', $newContent );
				$riconfNames[] = $user->getName();
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
	 * @param PageRiconferma[] $pages
	 */
	protected function updateCUList( array $pages ) {
		$this->getLogger()->info( 'Checking if CU list needs updating.' );
		$cuList = new Page( $this->getConfig()->get( 'cu-list-title' ) );
		$newContent = $cuList->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			if ( $user->inGroup( 'checkuser' ) ) {
				$reg = '!(\{\{ *Checkuser *\| *' . $user->getRegex() . " *\|[^}]+\| *)[\w \d]+(}}.*\n)!";
				if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
					$newContent = preg_replace( $reg, '', $newContent );
					$removeNames[] = $user->getName();
				} else {
					$newContent = preg_replace( $reg, '$1{{subst:#time:j F Y}}$2', $newContent );
					$riconfNames[] = $user->getName();
				}
			}
		}

		if ( !$riconfNames && !$removeNames ) {
			return;
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
}
