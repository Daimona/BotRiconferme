<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * Remove pages from the main page and add them to the archive
 */
class ArchivePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$pages = $this->getDataProvider()->getPagesToClose();
		$this->removeFromMainPage( $pages );
		$this->addToArchive( $pages );

		return TaskResult::STATUS_GOOD;
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
			'summary' => $this->msg( 'close-main-summary' )->text()
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

		$summary = $this->msg( 'close-archive-summary' )
			->params( [ '$usernums' => Message::commaList( $archivedList ) ] )->text();

		$archivePage->edit( [
			'appendtext' => $append,
			'summary' => $summary
		] );
	}
}
