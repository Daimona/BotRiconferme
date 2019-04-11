<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Wiki\Element;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * Update various pages around, to be done for all failed procedures
 */
class FailedUpdates extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$failed = $this->getFailures();
		if ( $failed ) {
			$bureaucrats = array_keys( $this->getDataProvider()->getGroupOutcomes( 'bureaucrat', $failed ) );
			if ( $bureaucrats ) {
				$this->updateBurList( $bureaucrats );
			}
			$this->requestRemoval( $failed );
			$this->updateAnnunci( $failed );
			$this->updateUltimeNotizie( $failed );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Get the list of failed votes
	 *
	 * @return PageRiconferma[]
	 */
	private function getFailures() : array {
		$ret = [];
		$allPages = $this->getDataProvider()->getPagesToClose();
		foreach ( $allPages as $page ) {
			if ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) {
				$ret[] = $page;
			}
		}
		return $ret;
	}

	/**
	 * @param string[] $users
	 */
	protected function updateBurList( array $users ) {
		$this->getLogger()->info( 'Updating bureaucrats list.' );

		$this->getLogger()->info( 'Updating bur list. Removing: ' . implode( ', ', $users ) );
		$remList = Element::regexFromArray( $users );
		$burList = new Page( $this->getConfig()->get( 'bur-list-title' ) );
		$content = $burList->getContent();
		$reg = "!^\#\{\{ *Burocrate *\| *$remList.+\n!m";
		$newContent = preg_replace( $reg, '', $content );

		$summary = $this->msg( 'bur-list-update-summary' )
			->params( [ '$remove' => Message::commaList( $users ) ] )
			->text();

		$burList->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Request the removal of the flag on meta
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function requestRemoval( array $pages ) {
		$this->getLogger()->info(
			'Requesting removal on meta for: ' . implode( ', ', $pages )
		);

		$flagRemPage = new Page(
			$this->getConfig()->get( 'flag-removal-page-title' ),
			'https://meta.wikimedia.org/w/api.php'
		);
		$baseText = $this->msg( 'flag-removal-text' );

		$content = $flagRemPage->getContent();
		$append = '';
		foreach ( $pages as $page ) {
			$append .=
				$baseText->params( [
					'$username' => $page->getUser()->getName(),
					'$link' => '[[:it:' . $page->getTitle() . ']]',
					'$groups' => implode( ', ', $page->getUser()->getGroups() )
				] )->text();
		}

		$after = '=== Miscellaneous requests ===';
		$newContent = str_replace( $after, "$append\n$after", $content );
		$summary = $this->msg( 'flag-removal-summary' )
			->params( [ '$num' => count( $pages ) ] )
			->text();

		$flagRemPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Update [[Wikipedia:Wikipediano/Annunci]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateAnnunci( array $pages ) {
		$this->getLogger()->info( 'Updating annunci' );
		$section = 1;

		$names = [];
		$text = '';
		$msg = $this->msg( 'annunci-text' );
		foreach ( $pages as $page ) {
			$user = $page->getUser()->getName();
			$names[] = $user;
			$text .= $msg->params( [ '$user' => $user ] )->text();
		}

		$month = ucfirst( Message::MONTHS[ date( 'F' ) ] );

		$annunciPage = new Page( $this->getConfig()->get( 'annunci-page-title' ) );
		$content = $annunciPage->getContent( $section );
		$secReg = "!=== *$month *===!";
		if ( preg_match( $secReg, $content ) ) {
			$newContent = preg_replace( $secReg, '$0' . "\n" . $text, $content );
		} else {
			$before = '!</div>\s*}}\s*</includeonly>!';
			$newContent = preg_replace( $before, '$0' . "\n=== $month ===\n" . $text, $content );
		}

		$summary = $this->msg( 'annunci-summary' )
			->params( [ '$names' => Message::commaList( $names ) ] )
			->text();

		$annunciPage->edit( [
			'section' => $section,
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Update [[Wikipedia:Ultime notizie]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateUltimeNotizie( array $pages ) {
		$this->getLogger()->info( 'Updating ultime notizie' );
		$notiziePage = new Page( $this->getConfig()->get( 'ultimenotizie-page-title' ) );

		$names = [];
		$text = '';
		$msg = $this->msg( 'ultimenotizie-text' );
		foreach ( $pages as $page ) {
			$user = $page->getUser()->getName();
			$title = $page->getTitle();
			$names[] = $user;
			$text .= $msg->params( [ '$user' => $user, '$title' => $title ] )->text();
		}

		$content = $notiziePage->getContent();
		$year = date( 'Y' );
		$secReg = "!== *$year *==!";
		if ( preg_match( $secReg, $content ) ) {
			$newContent = preg_replace( $secReg, '$0' . "\n" . $text, $content );
		} else {
			$reg = '!si veda la \[\[[^\]+relativa discussione]]\.\n!';
			$newContent = preg_replace( $reg, '$0' . "\n== $year ==\n" . $text, $content );
		}

		$summary = $this->msg( 'ultimenotizie-summary' )
			->params( [ '$names' => Message::commaList( $names ) ] )
			->text();

		$notiziePage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}
}
