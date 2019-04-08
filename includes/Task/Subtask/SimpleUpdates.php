<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * Update various pages around, to be done for all closed procedures
 */
class SimpleUpdates extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task SimpleUpdates' );

		$pages = $this->getDataProvider()->getPagesToClose();
		$this->updateVote( $pages );
		$this->updateNews( $pages );
		$this->updateAdminList( $pages );
		$this->updateCUList( $pages );

		$this->getLogger()->info( 'Task SimpleUpdates completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param PageRiconferma[] $pages
	 * @see UpdatesAround::addVote()
	 */
	protected function updateVote( array $pages ) {
		$this->getLogger()->info(
			'Updating votazioni: ' . implode( ', ', array_map( 'strval', $pages ) )
		);
		$votePage = new Page( $this->getConfig()->get( 'ric-vote-page' ) );

		$titles = [];
		foreach ( $pages as $page ) {
			$titles[] = preg_quote( $page->getTitle() );
		}

		$titleReg = implode( '|', $titles );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

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
	 * @see UpdatesAround::addNews()
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

		$newsPage = new Page( $this->getConfig()->get( 'ric-news-page' ) );

		$content = $newsPage->getContent();
		$simpleReg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';
		$voteReg = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d+)!';

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
			'Updating admin list: ' . implode( ', ', array_map( 'strval', $pages ) )
		);
		$adminsPage = new Page( $this->getConfig()->get( 'admins-list' ) );
		$newContent = $adminsPage->getContent();
		$newDate = date( 'Ymd', strtotime( '+1 year' ) );

		$riconfNames = $removeNames = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$reg = "!(\{\{Amministratore\/riga\|$user.+\| *)\d+( *\|(?: *pausa)? *\}\}\n)!";
			if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
				// Remove the line
				$newContent = preg_replace( $reg, '', $newContent );
				$removeNames[] = $user;
			} else {
				$newContent = preg_replace( $reg, '$1' . $newDate . '$2', $newContent );
				$riconfNames[] = $user;
			}
		}

		if ( count( $riconfNames ) > 1 ) {
			$lastUser = array_pop( $riconfNames );
			$riconfList = implode( ', ', $riconfNames ) . " e $lastUser";
		} elseif ( $riconfNames ) {
			$riconfList = $riconfNames[0];
		} else {
			$riconfList = 'nessuno';
		}

		if ( count( $removeNames ) > 1 ) {
			$lastUser = array_pop( $removeNames );
			$removeList = implode( ', ', $removeNames ) . " e $lastUser";
		} elseif ( $removeNames ) {
			$removeList = $removeNames[0];
		} else {
			$removeList = 'nessuno';
		}

		$summary = $this->msg( 'close-update-list-summary' )
			->params( [
				'$riconf' => $riconfList,
				'$remove' => $removeList
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
		$admins = $this->getDataProvider()->getUsersList();
		$newContent = $cuList->getContent();

		$riconfNames = $removeNames = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			if ( array_key_exists( 'checkuser', $admins[ $user ] ) ) {
				$reg = "!(\{\{ *Checkuser *\| *$user *\|[^}]+\| *)[\w \d](}}.*\n)!";
				if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
					// Remove the line
					$newContent = preg_replace( $reg, '', $newContent );
					$removeNames[] = $user;
				} else {
					$newContent = preg_replace( $reg, '$1{{subst:#time:j F Y}}$2', $newContent );
					$riconfNames[] = $user;
				}
			}
		}

		if ( !$riconfNames || !$removeNames ) {
			return;
		}

		$this->getLogger()->info(
			'Updating CU list. Riconf: ' . implode( ', ', $riconfNames ) .
			'; remove: ' . implode( ', ', $removeNames )
		);
		if ( count( $riconfNames ) > 1 ) {
			$lastUser = array_pop( $riconfNames );
			$riconfList = implode( ', ', $riconfNames ) . " e $lastUser";
		} elseif ( $riconfNames ) {
			$riconfList = $riconfNames[0];
		} else {
			$riconfList = 'nessuno';
		}

		if ( count( $removeNames ) > 1 ) {
			$lastUser = array_pop( $removeNames );
			$removeList = implode( ', ', $removeNames ) . " e $lastUser";
		} elseif ( $removeNames ) {
			$removeList = $removeNames[0];
		} else {
			$removeList = 'nessuno';
		}

		$summary = $this->msg( 'cu-list-update-summary' )
			->params( [
				'$riconf' => $riconfList,
				'$remove' => $removeList
			] )
			->text();

		$cuList->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}
}
