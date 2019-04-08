<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
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
			$this->updateBurList( $failed );
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

		$summary = $this->msg( 'bur-list-update-summary' )
			->params( [ '$remove' => Message::commaList( $remove ) ] )
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
			'Requesting removal on meta for: ' . implode( ', ', array_map( 'strval', $pages ) )
		);
		$admins = $this->getDataProvider()->getUsersList();

		$flagRemPage = new Page(
			$this->getConfig()->get( 'flag-removal-page' ),
			'https://meta.wikimedia.org/w/api.php'
		);
		$section = $this->getConfig()->get( 'flag-removal-section' );
		$baseText = $this->msg( 'flag-removal-text' )->text();

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

		$flagRemPage->edit( [
			'section' => $section,
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

		$summary = $this->msg( 'annunci-summary' )
			->params( [ '$names' => Message::commaList( $names ) ] )
			->text();

		$annunciPage->edit( [
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

		$summary = $this->msg( 'ultimenotizie-summary' )
			->params( [ '$names' => Message::commaList( $names ) ] )
			->text();

		$notiziePage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}
}
