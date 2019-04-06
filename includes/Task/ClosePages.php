<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

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

		$mainPage = $this->getConfig()->get( 'ric-main-page' );
		$content = $this->getController()->getPageContent( $mainPage );
		$translations = [];
		foreach ( $pages as $page ) {
			$translations[ '{{' . $page->getTitle() . '}}' ] = '';
		}

		$params = [
			'title' => $mainPage,
			'text' => strtr( $content, $translations ),
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

		$summary = strtr(
			$this->getConfig()->get( 'close-archive-summary' ),
			[ '$usernums' => $userNums ]
		);

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

		$current = $this->getController()->getPageContent( $page->getBaseTitle() );

		$outcomeText = $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ?
			'non riconfermato' :
			'riconfermato';
		$text = $page->isVote() ? "votazione: $outcomeText" : 'riconferma tacita';

		$newContent = str_replace( 'riconferma in corso', $text, $current );
		$params = [
			'title' => $page->getTitle(),
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'close-base-page-summary-update' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param PageRiconferma[] $pages
	 * @see UpdatesAround::addVote()
	 */
	protected function updateVote( array $pages ) {
		$votePage = $this->getConfig()->get( 'ric-vote-page' );
		$content = $this->getController()->getPageContent( $votePage );

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

		$summary = strtr(
			$this->getConfig()->get( 'close-vote-page-summary' ),
			[ '$num' => count( $pages ) ]
		);
		$summary = preg_replace_callback(
			'!\{\{$plur|(\d+)|([^|]+)|([^|]+)}}!',
			function ( $matches ) {
				return intval( $matches[1] ) > 1 ? trim( $matches[3] ) : trim( $matches[2] );
			},
			$summary
		);

		$params = [
			'title' => $votePage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
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

		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$simpleReg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';
		$voteReg = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d+)!';

		$simpleMatches = $voteMatches = [];
		preg_match( $simpleReg, $content, $simpleMatches );
		preg_match( $voteReg, $content, $voteMatches );

		$newSimp = (int)$simpleMatches[2] - $simpleAmount ?: '';
		$newVote = (int)$voteMatches[2] - $voteAmount ?: '';
		$newContent = preg_replace( $simpleReg, '${1}' . $newSimp, $content );
		$newContent = preg_replace( $voteReg, '${1}' . $newVote, $newContent );

		$summary = strtr(
			$this->getConfig()->get( 'close-news-page-summary' ),
			[ '$num' => count( $pages ) ]
		);
		$summary = preg_replace_callback(
			'!\{\{$plur|(\d+)|([^|]+)|([^|]+)}}!',
			function ( $matches ) {
				return intval( $matches[1] ) > 1 ? trim( $matches[3] ) : trim( $matches[2] );
			},
			$summary
		);

		$params = [
			'title' => $newsPage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Update date on WP:Amministratori/Lista
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateAdminList( array $pages ) {
		$listTitle = $this->getConfig()->get( 'admins-list' );
		$newContent = $this->getController()->getPageContent( $listTitle );
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

		$summary = strtr(
			$this->getConfig()->get( 'close-update-list-summary' ),
			[
				'$riconf' => $riconfList,
				'$remove' => $removeList
			]
		);

		$params = [
			'title' => $listTitle,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param PageRiconferma[] $pages
	 */
	protected function updateCUList( array $pages ) {
		$cuListTitle = $this->getConfig()->get( 'cu-list-title' );
		$listTitle = $this->getConfig()->get( 'list-title' );
		$admins = json_decode( $this->getController()->getPageContent( $listTitle ), true );
		$newContent = $this->getController()->getPageContent( $cuListTitle );

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

		if ( !$riconfNames && !$removeNames ) {
			return;
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

		$summary = strtr(
			$this->getConfig()->get( 'cu-list-update-summary' ),
			[
				'$riconf' => $riconfList,
				'$remove' => $removeList
			]
		);

		$params = [
			'title' => $cuListTitle,
			'text' => $newContent,
			'summary' => $summary
		];
		$this->getController()->editPage( $params );
	}

	/**
	 * @param PageRiconferma[] $pages
	 */
	protected function updateBurList( array $pages ) {
		$listTitle = $this->getConfig()->get( 'list-title' );
		$admins = json_decode( $this->getController()->getPageContent( $listTitle ), true );

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

		$remList = implode( '|', array_map( 'preg_quote', $remove ) );
		$burListTitle = $this->getConfig()->get( 'bur-list-title' );
		$content = $this->getController()->getPageContent( $burListTitle );
		$reg = "!^\#\{\{ *Burocrate *\| *($remList).+\n!m";
		$newContent = preg_replace( $reg, '', $content );

		if ( count( $remove ) > 1 ) {
			$lastUser = array_pop( $remove );
			$removeList = implode( ', ', $remove ) . " e $lastUser";
		} else {
			$removeList = $remove[0];
		}

		$summary = strtr(
			$this->getConfig()->get( 'bur-list-update-summary' ),
			[
				'$remove' => $removeList
			]
		);

		$params = [
			'title' => $burListTitle,
			'text' => $newContent,
			'summary' => $summary
		];
		$this->getController()->editPage( $params );
	}

	/**
	 * Request the removal of the flag on meta
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function requestRemoval( array $pages ) {
		$listTitle = $this->getConfig()->get( 'list-title' );
		$admins = json_decode( $this->getController()->getPageContent( $listTitle ), true );

		$oldUrl = RequestBase::$url;
		RequestBase::$url = 'https://meta.wikimedia.org/w/api.php';
		$pageTitle = $this->getConfig()->get( 'flag-removal-page' );
		$section = $this->getConfig()->get( 'flag-removal-section' );
		$baseText = $this->getConfig()->get( 'flag-removal-text' );

		$newContent = $this->getController()->getPageContent( $pageTitle, $section );
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

		$summary = strtr(
			$this->getConfig()->get( 'flag-removal-summary' ),
			[
				'$num' => count( $pages )
			]
		);

		$params = [
			'title' => $pageTitle,
			'text' => $newContent,
			'summary' => $summary
		];
		$this->getController()->editPage( $params );

		RequestBase::$url = $oldUrl;
	}

	/**
	 * Update [[Wikipedia:Wikipediano/Annunci]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateAnnunci( array $pages ) {
		$title = $this->getConfig()->get( 'annunci-title' );

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$names[] = $user;
			$text .= "{{Breve|admin|{{subst:#time:j}}|[[Utente:$user|]] non è stato riconfermato [[WP:A|amministratore]].}}\n";
		}

		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$month = ucfirst( strftime( '%B', time() ) );
		setlocale( LC_TIME, $oldLoc );

		$content = $this->getController()->getPageContent( $title, 1 );
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

		$summary = strtr(
			$this->getConfig()->get( 'annunci-summary' ),
			[ '$names' => $namesList ]
		);

		$params = [
			'title' => $title,
			'text' => $newContent,
			'summary' => $summary
		];
		$this->getController()->editPage( $params );
	}

	/**
	 * Update [[Wikipedia:Ultime notizie]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateUltimeNotizie( array $pages ) {
		$title = $this->getConfig()->get( 'ultimenotizie-title' );

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUser();
			$title = $page->getTitle();
			$names[] = $user;
			$text .= "'''{{subst:#time:j F}}''': [[Utente:$user|]] non è stato [[$title|riconfermato]] [[WP:A|amministratore]]; ora gli admin sono {{subst:#expr: {{NUMBEROFADMINS}} - 1}}.";
		}

		$content = $this->getController()->getPageContent( $title );
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

		$summary = strtr(
			$this->getConfig()->get( 'ultimenotizie-summary' ),
			[ '$names' => $namesList ]
		);

		$params = [
			'title' => $title,
			'text' => $newContent,
			'summary' => $summary
		];
		$this->getController()->editPage( $params );
	}
}
