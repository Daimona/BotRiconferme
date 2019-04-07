<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Page;
use BotRiconferme\PageRiconferma;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\TaskResult;

/**
 * For each open page, close it if the time's up and no more than 15 opposing votes were added
 * @fixme Avoid duplication with UpdatesAround etc.
 */
class ClosePages extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task ClosePages' );

		$pages = $this->getPagesList();
		$protectReason = $this->getConfig()->get( 'close-protect-summary' );
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$this->addVoteCloseText( $page );
			}
			$this->getController()->protectPage( $page->getTitle(), $protectReason );
			$this->updateBasePage( $page );
		}

		$this->removeFromMainPage( $pages );
		$this->addToArchive( $pages );
		$this->updateVote( $pages );
		$this->updateNews( $pages );
		$this->updateAdminList( $pages );
		$this->updateCUList( $pages );

		$failed = $this->getFailures( $pages );
		if ( $failed ) {
			$this->updateBurList( $failed );
			$this->requestRemoval( $failed );
			$this->updateAnnunci( $failed );
			$this->updateUltimeNotizie( $failed );
		}

		$this->getLogger()->info( 'Task ClosePages completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Get a list of pages to close
	 *
	 * @return PageRiconferma[]
	 */
	protected function getPagesList() : array {
		$allPages = $this->getDataProvider()->getOpenPages();
		$ret = [];
		foreach ( $allPages as $page ) {
			if ( time() > $page->getEndTimestamp() ) {
				$ret[] = $page;
			}
		}
		return $ret;
	}

	/**
	 * Extract the list of failed votes from the given list of pages
	 *
	 * @param PageRiconferma[] $pages
	 * @return PageRiconferma[]
	 */
	private function getFailures( array $pages ) : array {
		$ret = [];
		foreach ( $pages as $page ) {
			if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
				$ret[] = $page;
			}
		}
		return $ret;
	}

	/**
	 * @param PageRiconferma $page
	 */
	protected function addVoteCloseText( PageRiconferma $page ) {
		$content = $page->getContent();
		$beforeReg = '!è necessario ottenere una maggioranza .+ votanti\.!';
		$newContent = preg_replace( $beforeReg, '$0' . "\n" . $page->getOutcomeText(), $content );

		$params = [
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'close-result-summary' )
		];
		$page->edit( $params );
	}

	/**
	 * Removes pages from WP:A/Riconferme annuali
	 *
	 * @param PageRiconferma[] $pages
	 * @see UpdatesAround::addToMainPage()
	 */
	protected function removeFromMainPage( array $pages ) {
		$this->getLogger()->info(
			'Removing from main: ' . implode( ', ', array_map( 'strval', $pages ) )
		);

		$mainPage = new Page( $this->getConfig()->get( 'ric-main-page' ) );
		$translations = [];
		foreach ( $pages as $page ) {
			$translations[ '{{' . $page->getTitle() . '}}' ] = '';
		}

		$params = [
			'title' => $mainPage,
			'text' => strtr( $mainPage->getContent(), $translations ),
			'summary' => $this->getConfig()->get( 'close-main-summary' )
		];
		$this->getController()->editPage( $params );
	}

	/**
	 * Adds closed pages to the current archive
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToArchive( array $pages ) {
		$this->getLogger()->info(
			'Adding to archive: ' . implode( ', ', array_map( 'strval', $pages ) )
		);

		$simple = $votes = [];
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$votes[] = $page;
			} else {
				$simple[] = $page;
			}
		}

		$simpleTitle = $this->getConfig()->get( 'close-simple-archive-title' );
		$voteTitle = $this->getConfig()->get( 'close-vote-archive-title' );

		$this->reallyAddToArchive( $simpleTitle, $simple );
		$this->reallyAddToArchive( $voteTitle, $votes );
	}

	/**
	 * Really add $pages to the given archive
	 *
	 * @param string $archiveTitle
	 * @param array $pages
	 */
	private function reallyAddToArchive( string $archiveTitle, array $pages ) {
		$curTitle = "$archiveTitle/" . date( 'Y' );

		$append = '';
		$archivedList = [];
		foreach ( $pages as $page ) {
			$append .= '{{' . $page->getTitle() . "}}\n";
			$archivedList[] = $page->getUserNum();
		}

		if ( count( $archivedList ) > 1 ) {
			$last = array_pop( $archivedList );
			$userNums = implode( ', ', $archivedList ) . " e $last";
		} else {
			$userNums = $archivedList[0];
		}

		$summary = $this->msg( 'close-archive-summary' )
			->params( [ '$usernums' => $userNums ] )->text();

		$params = [
			'title' => $curTitle,
			'appendtext' => $append,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param PageRiconferma $page
	 * @see CreatePages::updateBasePage()
	 */
	protected function updateBasePage( PageRiconferma $page ) {
		$this->getLogger()->info( "Updating base page for $page" );

		$basePage = new Page( $page->getBaseTitle() );
		$current = $basePage->getContent();

		$outcomeText = $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ?
			'non riconfermato' :
			'riconfermato';
		$text = $page->isVote() ? "votazione: $outcomeText" : 'riconferma tacita';

		$newContent = str_replace( 'riconferma in corso', $text, $current );
		$params = [
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'close-base-page-summary-update' )
		];

		$basePage->edit( $params );
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
		$content = $votePage->getContent();

		$titles = [];
		foreach ( $pages as $page ) {
			$titles[] = preg_quote( $page->getTitle() );
		}

		$titleReg = implode( '|', array_map( 'preg_quote', $titles ) );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

		$newContent = preg_replace( $search, '', $content );
		// Make sure the last line ends with a full stop in every section
		$simpleSectReg = '!(^;È in corso.+riconferma tacita.+amministrat.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$voteSectReg = '!(^;Si vota per la .+riconferma .+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $simpleSectReg, '$1.', $newContent );
		$newContent = preg_replace( $voteSectReg, '$1.', $newContent );

		// @fixme Remove empty sections, and add the "''Nessuna riconferma o votazione in corso''" message
		// if the page is empty! Or just wait for the page to be restyled...

		$summary = $this->msg( 'close-vote-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];

		$votePage->edit( $params );
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

		$simpleMatches = $voteMatches = [];
		preg_match( $simpleReg, $content, $simpleMatches );
		preg_match( $voteReg, $content, $voteMatches );

		$newSimp = (int)$simpleMatches[2] - $simpleAmount ?: '';
		$newVote = (int)$voteMatches[2] - $voteAmount ?: '';
		$newContent = preg_replace( $simpleReg, '${1}' . $newSimp, $content );
		$newContent = preg_replace( $voteReg, '${1}' . $newVote, $newContent );

		$summary = $this->msg( 'close-news-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];

		$newsPage->edit( $params );
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

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];

		$adminsPage->edit( $params );
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

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];
		$cuList->edit( $params );
	}

	/**
	 * @param PageRiconferma[] $pages
	 */
	protected function updateBurList( array $pages ) {
		$this->getLogger()->info( 'Checking if bur list needs updating.' );
		$admins = $this->getDataProvider()->getUsersList();

		$remove = [];
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			if ( array_key_exists( 'bureaucrat', $admins[ $user ] ) &&
				$page->getOutcome() & PageRiconferma::OUTCOME_FAIL
			) {
				$remove[] = $user;
			}
		}

		if ( !$remove ) {
			return;
		}

		$this->getLogger()->info( 'Updating bur list. Removing: ' . implode( ', ', $remove ) );
		$remList = implode( '|', array_map( 'preg_quote', $remove ) );
		$burList = new Page( $this->getConfig()->get( 'bur-list-title' ) );
		$content = $burList->getContent();
		$reg = "!^\#\{\{ *Burocrate *\| *($remList).+\n!m";
		$newContent = preg_replace( $reg, '', $content );

		if ( count( $remove ) > 1 ) {
			$lastUser = array_pop( $remove );
			$removeList = implode( ', ', $remove ) . " e $lastUser";
		} else {
			$removeList = $remove[0];
		}

		$summary = $this->msg( 'bur-list-update-summary' )
			->params( [ '$remove' => $removeList ] )
			->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];
		$burList->edit( $params );
	}

	/**
	 * Request the removal of the flag on meta
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function requestRemoval( array $pages ) {
		$this->getLogger()->info(
			'Requesting removal on meta for: ' . implode( ', ', array_map( 'strval', $pages ) )
		);
		$admins = $this->getDataProvider()->getUsersList();

		$flagRemPage = new Page(
			$this->getConfig()->get( 'flag-removal-page' ),
			'https://meta.wikimedia.org/w/api.php'
		);
		$section = $this->getConfig()->get( 'flag-removal-section' );
		$baseText = $this->getConfig()->get( 'flag-removal-text' );

		$newContent = $flagRemPage->getContent( $section );
		foreach ( $pages as $page ) {
			$curText = strtr(
				$baseText,
				[
					'$username' => $page->getUser(),
					'$link' => '[[:it:' . $page->getTitle() . ']]',
					'$groups' => implode( ', ', array_keys( $admins[ $page->getUser() ] ) )
				]
			);
			$newContent .= $curText;
		}

		$summary = $this->msg( 'flag-removal-summary' )
			->params( [ '$num' => count( $pages ) ] )
			->text();

		$params = [
			'section' => $section,
			'text' => $newContent,
			'summary' => $summary
		];
		$flagRemPage->edit( $params );
	}

	/**
	 * Update [[Wikipedia:Wikipediano/Annunci]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateAnnunci( array $pages ) {
		$this->getLogger()->info( 'Updating annunci' );

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$names[] = $user;
			$text .= "{{Breve|admin|{{subst:#time:j}}|[[Utente:$user|]] " .
				"non è stato riconfermato [[WP:A|amministratore]].}}\n";
		}

		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$month = ucfirst( strftime( '%B', time() ) );
		setlocale( LC_TIME, $oldLoc );

		$annunciPage = new Page( $this->getConfig()->get( 'annunci-title' ) );
		$content = $annunciPage->getContent( 1 );
		$secReg = "!=== *$month *===!";
		if ( preg_match( $secReg, $content ) !== false ) {
			$newContent = preg_replace( $secReg, '$0' . "\n" . $text, $content );
		} else {
			$re = '!</div>\s*}}\s*</includeonly>!';
			$newContent = preg_replace( $re, '$0' . "\n=== $month ===\n" . $text, $content );
		}

		if ( count( $names ) > 1 ) {
			$lastUser = array_pop( $names );
			$namesList = implode( ', ', $names ) . " e $lastUser";
		} else {
			$namesList = $names[0];
		}

		$summary = $this->msg( 'annunci-summary' )
			->params( [ '$names' => $namesList ] )
			->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];
		$annunciPage->edit( $params );
	}

	/**
	 * Update [[Wikipedia:Ultime notizie]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateUltimeNotizie( array $pages ) {
		$this->getLogger()->info( 'Updating ultime notizie' );
		$notiziePage = new Page( $this->getConfig()->get( 'ultimenotizie-title' ) );

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$title = $page->getTitle();
			$names[] = $user;
			$text .= "'''{{subst:#time:j F}}''': [[Utente:$user|]] non è stato [[$title|riconfermato]] " .
				'[[WP:A|amministratore]]; ora gli admin sono {{subst:#expr: {{NUMBEROFADMINS}} - 1}}.';
		}

		$content = $notiziePage->getContent();
		$year = date( 'Y' );
		$secReg = "!== *$year *==!";
		if ( preg_match( $secReg, $content ) !== false ) {
			$newContent = preg_replace( $secReg, '$0' . "\n" . $text, $content );
		} else {
			$re = '!si veda la \[\[[^\]+relativa discussione]]\.\n!';
			$newContent = preg_replace( $re, '$0' . "\n== $year ==\n" . $text, $content );
		}

		if ( count( $names ) > 1 ) {
			$lastUser = array_pop( $names );
			$namesList = implode( ', ', $names ) . " e $lastUser";
		} else {
			$namesList = $names[0];
		}

		$summary = $this->msg( 'ultimenotizie-summary' )
			->params( [ '$names' => $namesList ] )
			->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];
		$notiziePage->edit( $params );
	}
}
