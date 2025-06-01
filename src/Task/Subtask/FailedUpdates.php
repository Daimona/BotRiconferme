<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Clock;
use BotRiconferme\Message\Message;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Utils\RegexUtils;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Wiki\User;

/**
 * Update various pages around, to be done for all failed procedures
 */
class FailedUpdates extends Subtask {
	private const LOCALLY_REMOVED_GROUPS = [];

	/**
	 * @inheritDoc
	 */
	public function runInternal(): int {
		$failed = $this->getFailures();
		if ( !$failed ) {
			return TaskResult::STATUS_NOTHING;
		}

		$bureaucrats = $this->getFailedBureaucrats( $failed );
		if ( $bureaucrats ) {
			$this->updateBurList( $bureaucrats );
		}

		$this->requestRemovalOnLocalWiki( $failed );
		$this->requestRemovalOnCentralWiki( $failed );

		$this->updateAnnunci( $failed );
		$this->updateUltimeNotizie( $failed );
		$this->updateTimeline( $failed );
		$this->updateCronologia( $failed );
		$this->blockOnPrivate( $failed );

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Get the list of failed votes
	 *
	 * @return PageRiconferma[]
	 */
	private function getFailures(): array {
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
	protected function updateBurList( array $users ): void {
		$this->getLogger()->info( 'Updating bur list. Removing: ' . implode( ', ', $users ) );
		$remList = RegexUtils::regexFromArray( '!', ...$users );
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
	 * Request removal of locally-managed groups on the local wiki
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function requestRemovalOnLocalWiki( array $pages ): void {
		$this->getLogger()->info( 'Checking local group removal for: ' . implode( ', ', $pages ) );

		$flagRemPage = new Page(
			$this->getOpt( 'flag-removal-page-title-local' ),
			$this->getWiki()
		);
		$baseText = $this->msg( 'flag-removal-text-local' );

		$append = '';
		$localRemovalsCount = 0;
		$debugInfo = [];
		foreach ( $pages as $page ) {
			$username = $page->getUserName();
			$groups = $this->getUser( $page->getUserName() )->getGroups();
			$locallyRemovedGroups = array_intersect( $groups, self::LOCALLY_REMOVED_GROUPS );
			if ( $locallyRemovedGroups ) {
				$append .= $baseText->params( [
					'$username' => $username,
					'$linkTarget' => $page->getTitle(),
				] )->text() . "\n";
				$localRemovalsCount++;
				$debugInfo[] = "$username (" . implode( $locallyRemovedGroups ) . ')';
			}
		}

		if ( !$localRemovalsCount ) {
			return;
		}

		$this->getLogger()->info( 'Requesting removal on local wiki for: ' . implode( '; ', $debugInfo ) );

		// NOTE: This assumes that the section for removal of access comes immediately
		// before the "Amministratori dell'interfaccia" section. This is obviously not guaranteed, and this code
		// might break if the page structure changes.
		$after = "== Amministratori dell'interfaccia ==";
		$newContent = str_replace( $after, "$append\n$after", $flagRemPage->getContent() );
		$summary = $this->msg( 'flag-removal-summary-local' )
			->params( [ '$num' => $localRemovalsCount ] )
			->text();

		$flagRemPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Request removal of centrally-managed groups on the central wiki
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function requestRemovalOnCentralWiki( array $pages ): void {
		$this->getLogger()->info( 'Checking central group removal for: ' . implode( ', ', $pages ) );

		$metaWiki = $this->getWikiGroup()->getCentralWiki();
		$flagRemPage = new Page(
			$this->getOpt( 'flag-removal-page-title-central' ),
			$metaWiki
		);
		$baseText = $this->msg( 'flag-removal-text-central' );

		$append = '';
		$centralRemovalsCount = 0;
		$debugInfo = [];
		foreach ( $pages as $page ) {
			$username = $page->getUserName();
			$groups = $this->getUser( $page->getUserName() )->getGroups();
			$centrallyRemovedGroups = array_diff( $groups, self::LOCALLY_REMOVED_GROUPS );
			if ( $centrallyRemovedGroups ) {
				$append .= $baseText->params( [
					'$username' => $username,
					'$linkTarget' => $page->getTitle(),
					'$groups' => implode( ', ', $centrallyRemovedGroups )
				] )->text() . "\n";
				$centralRemovalsCount++;
				$debugInfo[] = "$username (" . implode( $centrallyRemovedGroups ) . ')';
			}
		}

		if ( !$centralRemovalsCount ) {
			return;
		}

		$this->getLogger()->info( 'Requesting removal on central wiki for: ' . implode( '; ', $debugInfo ) );

		// NOTE: This assumes that the section for removal of access comes immediately
		// before the "see also" section. This is obviously not guaranteed, and this code
		// might break if the page structure changes.
		$after = '== See also ==';
		$newContent = str_replace( $after, "$append\n$after", $flagRemPage->getContent() );
		$summary = $this->msg( 'flag-removal-summary-central' )
			->params( [ '$num' => $centralRemovalsCount ] )
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
	protected function updateAnnunci( array $pages ): void {
		$this->getLogger()->info( 'Updating annunci' );
		$section = 1;

		$names = [];
		$text = '';
		foreach ( $pages as $page ) {
			$user = $page->getUserName();
			$names[] = $user;
			$text .= $this->msg( 'annunci-text' )->params( [ '$user' => $user ] )->text();
		}

		$curMonth = Clock::getDate( 'F' );
		$month = ucfirst( Message::MONTHS[$curMonth] );

		$annunciPage = $this->getPage( $this->getOpt( 'annunci-page-title' ) );
		$content = $annunciPage->getSectionContent( $section );
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
	protected function updateUltimeNotizie( array $pages ): void {
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
		$year = Clock::getDate( 'Y' );
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
	 * Update [[Wikipedia:Amministratori/Timeline]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function updateTimeline( array $pages ): void {
		$this->getLogger()->info( 'Updating timeline' );
		$timelinePage = $this->getPage( $this->getOpt( 'timeline-page-title' ) );
		$content = $timelinePage->getContent();

		$today = Clock::getDate( 'm/d/Y' );
		foreach ( $pages as $page ) {
			$name = $page->getUserName();
			$content = preg_replace(
				"/(?<=color:)current( *from:\d+/\d+/\d+ till:)end(?= text:\"\[\[(User|Utente):$name|$name]]\")/",
				'nonriconf$1' . $today,
				$content
			);
		}

		$summary = $this->msg( 'timeline-summary' )->text();

		$timelinePage->edit( [
			'text' => $content,
			'summary' => $summary
		] );
	}

	/**
	 * Update [[Wikipedia:Amministratori/Cronologia]]
	 *
	 * @param PageRiconferma[] $pages
	 */
	private function updateCronologia( array $pages ): void {
		$this->getLogger()->info( 'Updating cronologia' );
		$timelinePage = $this->getPage( $this->getOpt( 'cronologia-page-title' ) );
		$content = $timelinePage->getContent();

		foreach ( $pages as $page ) {
			$name = $page->getUserName();
			$content = preg_replace(
				"/(\* *)'''(\[\[(Utente|User):$name|$name]])'''( <small>dal \d+ \w+ \d+)(</small>)/",
				'$1$2$3' . " al {{subst:#timel:j F Y}} (non riconfermato)" . '$4',
				$content
			);
		}

		$summary = $this->msg( 'cronologia-summary' )->text();

		$timelinePage->edit( [
			'text' => $content,
			'summary' => $summary
		] );
	}

	/**
	 * Block the user on the private wiki
	 *
	 * @param PageRiconferma[] $pages
	 */
	private function blockOnPrivate( array $pages ): void {
		$this->getLogger()->info( 'Blocking on private wiki: ' . implode( ', ', $pages ) );

		$privWiki = $this->getWikiGroup()->getPrivateWiki();
		$reason = $this->msg( 'private-block-reason' )->text();

		foreach ( $pages as $page ) {
			$privWiki->blockUser( $page->getUserName(), $reason );
		}
	}

	/**
	 * Get a list of bureaucrats from the given $pages
	 *
	 * @param PageRiconferma[] $pages
	 * @return User[]
	 */
	private function getFailedBureaucrats( array $pages ): array {
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
