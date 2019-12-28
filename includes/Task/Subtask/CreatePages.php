<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\TaskResult;
use BotRiconferme\Wiki\User;

/**
 * For each user, create the WP:A/Riconferma_annuale/USERNAME/XXX page and add it to its base page
 */
class CreatePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$users = $this->getDataProvider()->getUsersToProcess();

		if ( !$users ) {
			return TaskResult::STATUS_NOTHING;
		}

		foreach ( $users as $user ) {
			$this->processUser( $user );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Determine what pages we need to create for a single user.
	 *
	 * @param User $user
	 */
	protected function processUser( User $user ) {
		try {
			$num = $this->getLastPageNum( $user ) + 1;
		} catch ( TaskException $e ) {
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
	 * @param User $user
	 * @return int
	 * @throws TaskException
	 */
	protected function getLastPageNum( User $user ) : int {
		$this->getLogger()->debug( "Retrieving previous pages for $user" );

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

		$res = RequestBase::newFromParams( $params )->execute();

		$last = 0;
		foreach ( $res->query->allpages as $resPage ) {
			$page = new PageRiconferma( $resPage->title, $this->getWiki() );

			if ( date( 'z/Y', $page->getCreationTimestamp() ) === date( 'z/Y' ) ) {
				throw new TaskException( "Page $page was already created today!" );
			}
			if ( $page->getNum() > $last ) {
				$last = $page->getNum();
			}
		}

		return $last;
	}

	/**
	 * Really creates the page WP:A/Riconferma_annuale/USERNAME/XXX
	 *
	 * @param string $title
	 * @param User $user
	 */
	protected function createPage( string $title, User $user ) {
		$this->getLogger()->info( "Creating page $title" );
		$groups = $user->getGroups();
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
	 *
	 * @param Page $basePage
	 * @param string $newText
	 */
	protected function createBasePage( Page $basePage, string $newText ) {
		$this->getLogger()->info( "Creating base page $basePage" );

		$params = [
			'text' => $newText,
			'summary' => $this->msg( 'base-page-summary' )->text()
		];

		$basePage->edit( $params );
	}

	/**
	 * Updates the page WP:A/Riconferma_annuale/USERNAME if it already exists
	 * @param Page $basePage
	 * @param string $newText
	 */
	protected function updateBasePage( Page $basePage, string $newText ) {
		$this->getLogger()->info( "Updating base page $basePage" );

		$params = [
			'appendtext' => "\n$newText",
			'summary' => $this->msg( 'base-page-summary-update' )->text()
		];

		$basePage->edit( $params );
	}
}
