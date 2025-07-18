<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use AppendIterator;
use BotRiconferme\Clock;
use BotRiconferme\Task\Exception\PageCreatedTodayException;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Wiki\User;
use NoRewindIterator;

/**
 * For each user, create the WP:A/Riconferma_annuale/USERNAME/XYZ page and add it to its base page
 */
class CreatePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): Status {
		$users = $this->getDataProvider()->getUsersToProcess();

		if ( !$users ) {
			return Status::NOTHING;
		}

		foreach ( $users as $user ) {
			$this->processUser( $user );
		}

		return Status::GOOD;
	}

	/**
	 * Determine what pages we need to create for a single user.
	 */
	protected function processUser( User $user ): void {
		$this->getLogger()->info( "Processing user $user" );
		try {
			$num = $this->getLastPageNum( $user ) + 1;
		} catch ( PageCreatedTodayException $e ) {
			// The page was already created today. PLZ let this poor bot work!
			$this->getDataProvider()->removeUser( $user->getName() );
			$this->getLogger()->warning( $e->getMessage() . " - User $user won't be processed." );
			return;
		}

		$basePage = $user->getBasePage();
		// This should always use the new username
		$pageTitle = "$basePage/$num";
		$this->createPage( $pageTitle, $user );
		$ricPage = new PageRiconferma( $pageTitle, $this->getWiki() );

		$newText = $this->msg( 'base-page-text' )->params( [ '$title' => $pageTitle ] )->text();
		if ( $num === 1 ) {
			$this->createBasePage( $basePage, $newText );
		} else {
			$basePage = $user->getExistingBasePage();
			$this->updateBasePage( $basePage, $newText );
		}

		$this->getDataProvider()->addCreatedPage( $ricPage );
	}

	/**
	 * Get the number of last page for the given user
	 *
	 * @throws PageCreatedTodayException
	 */
	protected function getLastPageNum( User $user ): int {
		$this->getLogger()->info( "Retrieving previous pages for $user" );

		$unprefixedTitle = explode( ':', $this->getOpt( 'main-page-title' ), 2 )[1];

		$prefixes = [ "$unprefixedTitle/$user/" ];
		foreach ( $user->getAliases() as $alias ) {
			$prefixes[] = "$unprefixedTitle/$alias/";
		}

		$params = [
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => 4,
			'apprefix' => implode( '|', $prefixes ),
			'aplimit' => 'max'
		];
		$pagesIterator = new AppendIterator();
		foreach ( $prefixes as $prefix ) {
			$params['apprefix'] = $prefix;
			$res = $this->getWiki()->buildRequest( $params )->executeAsQuery();
			$pagesIterator->append( new NoRewindIterator( $res ) );
		}

		$lastNum = 0;
		foreach ( $pagesIterator as $resPage ) {
			$page = new PageRiconferma( $resPage->title, $this->getWiki() );

			// Note: we may be able to just check the page with the greatest number, but unsure if that
			// assumption will work when considering renames etc.
			if ( Clock::getDate( 'z/Y', $page->getCreationTimestamp() ) === Clock::getDate( 'z/Y' ) ) {
				throw new PageCreatedTodayException( "Page $page was already created today!" );
			}
			if ( $page->getNum() > $lastNum ) {
				$lastNum = $page->getNum();
			}
		}

		return $lastNum;
	}

	/**
	 * Really creates the page WP:A/Riconferma_annuale/USERNAME/XYZ
	 */
	protected function createPage( string $title, User $user ): void {
		$this->getLogger()->info( "Creating page $title" );
		$groups = $user->getGroupsWithDates();
		$textParams = [
			'$user' => $user->getName(),
			'$date' => $groups['sysop'],
			'$burocrate' => $groups['bureaucrat'] ?? '',
			'$checkuser' => $groups['checkuser'] ?? ''
		];

		$params = [
			'title' => $title,
			'text' => $this->msg( 'ric-page-text' )->params( $textParams )->text(),
			'summary' => $this->msg( 'ric-page-summary' )->text()
		];

		$this->getWiki()->editPage( $params );
	}

	/**
	 * Creates the page WP:A/Riconferma_annuale/USERNAME if it doesn't exist
	 */
	protected function createBasePage( Page $basePage, string $newText ): void {
		$this->getLogger()->info( "Creating base page $basePage" );

		$params = [
			'text' => $newText,
			'summary' => $this->msg( 'base-page-summary' )->text()
		];

		$basePage->edit( $params );
	}

	/**
	 * Updates the page WP:A/Riconferma_annuale/USERNAME if it already exists
	 */
	protected function updateBasePage( Page $basePage, string $newText ): void {
		$this->getLogger()->info( "Updating base page $basePage" );

		$params = [
			'appendtext' => "\n$newText",
			'summary' => $this->msg( 'base-page-summary-update' )->text()
		];

		$basePage->edit( $params );
	}
}
