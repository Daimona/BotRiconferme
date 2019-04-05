<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * For each open page, close it if the time's up and no more than 15 opposing votes were added
 * @fixme Avoid duplication with UpdatesAround etc.
 * @todo Handle votes
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
	 * @param PageRiconferma $page
	 */
	protected function addVoteCloseText( PageRiconferma $page ) {
		$content = $page->getContent();
		$beforeReg = '!è necessario ottenere una maggioranza .+ votanti\.!';
		$newContent = preg_replace( $beforeReg, '$0' . "\n" . $page->getOutcomeText(), $content );

		$params = [
			'title' => $page->getTitle(),
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'close-result-summary' )
		];
		$this->getController()->editPage( $params );
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

		$text = $page->isVote() ?
			'votazione: ' . ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ? 'non riconfermato' : 'riconfermato' ) :
			'riconferma tacita';

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

		$titleReg = implode( '|', $titles );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

		$newContent = preg_replace( $search, '', $content );
		// Make sure the last line ends with a full stop in every section
		$simpleSectReg = '!(^;È in corso.+riconferma tacita.+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$voteSectReg = '!(^;Si vota per la .+riconferma .+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $simpleSectReg, '$1.', $newContent );
		$newContent = preg_replace( $voteSectReg, '$1.', $newContent );

		// @fixme Remove empty sections, and add the "''Nessuna riconferma o votazione in corso''" message
		//   if the page is empty! Or just wait for the page to be restyled...

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

		$this->getLogger()->info( "Decreasing the news counter: $simpleAmount simple, $voteAmount votes." );
		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$simpleReg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';
		$voteReg = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d+)!';

		$simpleMatches = $voteMatched = [];
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
}
