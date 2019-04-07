<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Page;
use BotRiconferme\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * Remove pages from the main page and add them to the archive
 */
class ArchivePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task ArchivePages' );

		$pages = $this->getDataProvider()->getPagesToClose();
		$this->removeFromMainPage( $pages );
		$this->addToArchive( $pages );

		$this->getLogger()->info( 'Task ArchivePages completed successfully' );
		return new TaskResult( self::STATUS_OK );
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
		$remove = [];
		foreach ( $pages as $page ) {
			$remove[] = '{{' . $page->getTitle() . '}}';
		}

		$mainPage->edit( [
			'text' => str_replace( $remove, '', $mainPage->getContent() ),
			'summary' => $this->getConfig()->get( 'close-main-summary' )
		] );
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
		$archivePage = new Page( "$archiveTitle/" . date( 'Y' ) );

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

		$archivePage->edit( [
			'appendtext' => $append,
			'summary' => $summary
		] );
	}
}
