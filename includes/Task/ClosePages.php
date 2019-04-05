<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\WikiController;

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

		$titles = $this->getPagesList();
		$protectReason = $this->getConfig()->get( 'close-protect-summary' );
		foreach ( $titles as $title ) {
			$this->getController()->protectPage( $title, $protectReason );
			$this->updateBasePage( $title );
		}

		$this->removeFromMainPage( $titles );
		$this->addToArchive( $titles );
		$this->updateVote( $titles );
		$this->updateNews( count( $titles ) );
		$this->updateAdminList( $titles );

		$this->getLogger()->info( 'Task ClosePages completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Get a list of pages to close
	 *
	 * @return string[]
	 */
	protected function getPagesList() : array {
		$allPages = $this->getDataProvider()->getOpenPages();
		$ret = [];
		foreach ( $allPages as $page ) {
			$created = $this->getController()->getPageCreationTS( $page );
			if ( time() - $created <= 60 * 60 * 24 * 7 && !WikiController::hasOpposition( $page ) ) {
				$ret[] = $page;
			}
		}
		return $ret;
	}

	/**
	 * Removes pages from WP:A/Riconferme annuali
	 * @param string[] $titles
	 * @see UpdatesAround::addToMainPage()
	 */
	protected function removeFromMainPage( array $titles ) {
		$this->getLogger()->info( 'Removing from main: ' . implode( ', ', $titles ) );

		$mainPage = $this->getConfig()->get( 'ric-main-page' );
		$content = $this->getController()->getPageContent( $mainPage );
		$translations = [];
		foreach ( $titles as $title ) {
			$translations[ '{{' . $title . '}}' ] = '';
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
	 * @param string[] $titles
	 */
	protected function addToArchive( array $titles ) {
		$this->getLogger()->info( 'Adding to archive: ' . implode( ', ', $titles ) );

		$archiveTitle = $this->getConfig()->get( 'close-archive-title' );
		$archiveTitle = "$archiveTitle/" . date( 'Y' );

		$append = '';
		$archivedList = [];
		foreach ( $titles as $page ) {
			$append .= '{{' . $page . "}}\n";
			$archivedList[] = explode( '/', $page, 3 )[2];
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
			'title' => $archiveTitle,
			'appendtext' => $append,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param string $title
	 * @see CreatePages::updateBasePage()
	 */
	protected function updateBasePage( string $title ) {
		$this->getLogger()->info( "Updating base page $title" );

		// @phan-suppress-next-line PhanTypeMismatchArgumentInternal WTF Phan what's wrong w/ u?
		$baseTitle = substr( $title, 0, strrpos( $title, '/' ) );
		$current = $this->getController()->getPageContent( $baseTitle );

		$newContent = str_replace( 'riconferma in corso', 'riconferma tacita', $current );
		$params = [
			'title' => $title,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'close-base-page-summary-update' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param string[] $titles
	 * @see UpdatesAround::addVote()
	 */
	protected function updateVote( array $titles ) {
		$votePage = $this->getConfig()->get( 'ric-vote-page' );
		$content = $this->getController()->getPageContent( $votePage );

		$titleReg = implode( '|', array_map( 'preg_quote', $titles ) );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

		$newContent = preg_replace( $search, '', $content );
		// Make sure the last line ends with a full stop
		$sectionReg = '!(^;È in corso.+riconferma tacita.+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $sectionReg, '$1.', $newContent );

		$summary = strtr(
			$this->getConfig()->get( 'close-vote-page-summary' ),
			[ '$num' => count( $titles ) ]
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
	 * @param int $amount
	 * @throws TaskException
	 * @see UpdatesAround::addNews()
	 */
	protected function updateNews( int $amount ) {
		$this->getLogger()->info( "Decreasing the news counter by $amount" );
		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';

		$matches = [];
		if ( preg_match( $reg, $content, $matches ) === false ) {
			throw new TaskException( 'Param not found in news page' );
		}

		$newNum = (int)$matches[2] - $amount;
		$newContent = preg_replace( $reg, '${1}' . $newNum, $content );

		$summary = strtr(
			$this->getConfig()->get( 'close-news-page-summary' ),
			[ '$num' => $amount ]
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
	 * @param string[] $titles
	 */
	protected function updateAdminList( array $titles ) {
		$listTitle = $this->getConfig()->get( 'admins-list' );
		$content = $this->getController()->getPageContent( $listTitle );
		$newDate = date( 'Ymd', strtotime( '+1 year' ) );

		$names = [];
		foreach ( $titles as $title ) {
			$user = explode( '/', $title )[2];
			$names[] = $user;
			$reg = "!(\{\{Amministratore\/riga\|$user.+\| *)\d+(?= *\|(?: *pausa)? *\}\})!";
			$content = preg_replace( $reg, '$1' . $newDate, $content );
		}

		if ( count( $names ) > 1 ) {
			$lastUser = array_pop( $names );
			$usersList = implode( ', ', $names ) . " e $lastUser";
		} else {
			$usersList = $names[0];
		}

		$summary = strtr(
			$this->getConfig()->get( 'close-update-list-summary' ),
			[ '$names' => $usersList ]
		);

		$params = [
			'title' => $listTitle,
			'text' => $content,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}
}
