<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\TaskResult;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Wiki\User;
use BotRiconferme\Utils\RegexUtils;

/**
 * Update various pages around, to be done for all failed procedures
 */
class FailedUpdates extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$failed = $this->getFailures();
		if ( !$failed ) {
			return TaskResult::STATUS_NOTHING;
		}

		$bureaucrats = $this->getFailedBureaucrats( $failed );
		if ( $bureaucrats ) {
			$this->updateBurList( $bureaucrats );
		}
		$this->requestRemoval( $failed );
		$this->updateAnnunci( $failed );
		$this->updateUltimeNotizie( $failed );

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
	 * @param User[] $users
	 */
	protected function updateBurList( array $users ) : void {
		$this->getLogger()->info( 'Updating bur list. Removing: ' . implode( ', ', $users ) );
		$remList = RegexUtils::regexFromArray( '!', $users );
		$burList = $this->getPage( $this->getOpt( 'bur-list-title' ) );
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
	protected function requestRemoval( array $pages ) : void {
		$this->getLogger()->info( 'Requesting flag removal for: ' . implode( ', ', $pages ) );

		$metaWiki = $this->getWikiGroup()->getCentralWiki();
		// FIXME There should be some layer like "WikiFamily" for sharing Login info and bot status
		//  and/or a factory method to get wikis.
		$metaWiki->setEditsAsBot( $this->getWiki()->getEditsAsBot() );
		$flagRemPage = new Page(
			$this->getOpt( 'flag-removal-page-title' ),
			$metaWiki
		);
		$baseText = $this->msg( 'flag-removal-text' );

		$append = '';
		foreach ( $pages as $page ) {
			$append .=
				$baseText->params( [
					'$username' => $page->getUserName(),
					'$link' => '[[:it:' . $page->getTitle() . ']]',
					'$groups' => implode( ', ', $this->getUser( $page->getUserName() )->getGroups() )
				] )->text();
		}

		$after = '=== Miscellaneous requests ===';
		$newContent = str_replace( $after, "$append\n$after", $flagRemPage->getContent() );
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
	protected function updateAnnunci( array $pages ) : void {
		$this->getLogger()->info( 'Updating annunci' );
		$section = 1;

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUserName();
			$names[] = $user;
			$text .= $this->msg( 'annunci-text' )->params( [ '$user' => $user ] )->text();
		}

		$month = ucfirst( Message::MONTHS[ date( 'F' ) ] );

		$annunciPage = $this->getPage( $this->getOpt( 'annunci-page-title' ) );
		$content = $annunciPage->getContent( $section );
		$secReg = "!=== *$month *===!";
		if ( $annunciPage->matches( $secReg ) ) {
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
	protected function updateUltimeNotizie( array $pages ) : void {
		$this->getLogger()->info( 'Updating ultime notizie' );
		$notiziePage = $this->getPage( $this->getOpt( 'ultimenotizie-page-title' ) );

		$names = [];
		$text = '';
		$msg = $this->msg( 'ultimenotizie-text' );
		foreach ( $pages as $page ) {
			$user = $page->getUserName();
			$names[] = $user;
			$text .= $msg->params( [ '$user' => $user, '$title' => $page->getTitle() ] )->text();
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
			->params( [ '$names' => Message::commaList( $names ) ] )->text();

		$notiziePage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Get a list of bureaucrats from the given $pages
	 *
	 * @param PageRiconferma[] $pages
	 * @return User[]
	 */
	private function getFailedBureaucrats( array $pages ) : array {
		$ret = [];
		foreach ( $pages as $page ) {
			$user = $this->getUser( $page->getUserName() );
			if ( $user->inGroup( 'bureaucrat' ) ) {
				$ret[] = $user;
			}
		}
		return $ret;
	}
}
