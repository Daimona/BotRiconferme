<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Clock;
use BotRiconferme\Message\Message;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\Wiki\Page\PageRiconferma;

/**
 * Remove pages from the main page and add them to the archive
 */
class ArchivePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): Status {
		$pages = $this->getDataProvider()->getPagesToClose();

		if ( !$pages ) {
			return Status::NOTHING;
		}

		$this->removeFromMainPage( $pages );
		$this->addToArchive( $pages );

		return Status::GOOD;
	}

	/**
	 * Removes pages from WP:A/Riconferme annuali
	 *
	 * @param PageRiconferma[] $pages
	 * @see OpenUpdates::addToMainPage()
	 */
	protected function removeFromMainPage( array $pages ): void {
		$this->getLogger()->info(
			'Removing from main: ' . implode( ', ', $pages )
		);

		$mainPage = $this->getPage( $this->getOpt( 'main-page-title' ) );
		$remove = [];
		foreach ( $pages as $page ) {
			// Order matters here. It's not guaranteed that there'll be a newline, but avoid
			// regexps for that single character.
			$remove[] = '{{' . $page->getTitle() . "}}\n";
			$remove[] = '{{' . $page->getTitle() . '}}';
		}

		$newContent = str_replace( $remove, '', $mainPage->getContent() );

		$reg = '!\{\{(?:Wikipedia:Amministratori\/Riconferma annuale)?\/[^\/}]+\/\d+}}!';
		if ( preg_match_all( $reg, $newContent ) === 0 ) {
			$newContent = preg_replace(
				"/<!-- (:''Nessuna riconferma in corso\.'') -->/",
				'$1',
				$newContent
			);
		}

		$summary = $this->msg( 'close-main-summary' )
			->params( [ '$num' => count( $pages ) ] )
			->text();

		$mainPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Adds closed pages to the current archive
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToArchive( array $pages ): void {
		$this->getLogger()->info(
			'Adding to archive: ' . implode( ', ', $pages )
		);

		$simple = $votes = [];
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$votes[] = $page;
			} else {
				$simple[] = $page;
			}
		}

		$simpleTitle = $this->getOpt( 'close-simple-archive-title' );
		$voteTitle = $this->getOpt( 'close-vote-archive-title' );

		if ( $simple ) {
			$this->reallyAddToArchive( $simpleTitle, $simple );
		}
		if ( $votes ) {
			$this->reallyAddToArchive( $voteTitle, $votes );
		}
	}

	/**
	 * Really add $pages to the given archive
	 *
	 * @param string $archiveTitle
	 * @param PageRiconferma[] $pages
	 */
	private function reallyAddToArchive( string $archiveTitle, array $pages ): void {
		$archivePage = $this->getPage( "$archiveTitle/" . Clock::getDate( 'Y' ) );
		$existed = $archivePage->exists();

		$append = "\n";
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

		if ( !$existed ) {
			$this->addArchiveYear( $archiveTitle );
		}
	}

	/**
	 * Add a link to the newly-created archive for this year to the main archive page
	 */
	private function addArchiveYear( string $archiveTitle ): void {
		$page = $this->getPage( $archiveTitle );
		$year = Clock::getDate( 'Y' );

		$summary = $this->msg( 'new-archive-summary' )
			->params( [ '$year' => $year ] )->text();

		$page->edit( [
			'appendtext' => "\n*[[/$year|$year]]",
			'summary' => $summary
		] );
	}
}
