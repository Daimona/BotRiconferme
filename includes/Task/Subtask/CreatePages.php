<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\TaskResult;

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

		foreach ( $users as $user => $groups ) {
			$this->processUser( $user, $groups );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Determine what pages we need to create for a single user.
	 *
	 * @param string $user
	 * @param array $groups
	 */
	protected function processUser( string $user, array $groups ) {
		try {
			$num = $this->getLastPageNum( $user ) + 1;
		} catch ( TaskException $e ) {
			// The page was already created today. PLZ let this poor bot work!
			$this->getDataProvider()->removeUser( $user );
			$this->getLogger()->warning( $e->getMessage() . " - User $user won't be processed." );
			return;
		}

		$baseTitle = $this->getOpt( 'main-page-title' ) . "/$user";
		$pageTitle = "$baseTitle/$num";
		$this->createPage( $pageTitle, $user, $groups );

		$newText = $this->msg( 'base-page-text' )->params( [ '$title' => $pageTitle ] )->text();
		if ( $num === 1 ) {
			$this->createBasePage( $baseTitle, $newText );
		} else {
			$this->updateBasePage( $baseTitle, $newText );
		}

		$pageObj = new PageRiconferma( $pageTitle, $this->getController() );
		$this->getDataProvider()->addCreatedPages( $pageObj );
	}

	/**
	 * Get the number of last page for the given user
	 *
	 * @param string $user
	 * @return int
	 * @throws TaskException
	 */
	protected function getLastPageNum( string $user ) : int {
		$this->getLogger()->debug( "Retrieving previous pages for $user" );
		$unprefixedTitle = explode( ':', $this->getOpt( 'main-page-title' ), 2 )[1];
		$params = [
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => 4,
			'apprefix' => "$unprefixedTitle/$user/",
			'aplimit' => 'max'
		];

		$res = RequestBase::newFromParams( $params )->execute();

		// Little hack to have getNum() return 0
		$last = new PageRiconferma( 'X/Y/Z/0', $this->getController() );
		foreach ( $res->query->allpages as $resPage ) {
			$page = new PageRiconferma( $resPage->title, $this->getController() );

			if ( $page->getNum() > $last->getNum() ) {
				$last = $page;
			}
		}

		if ( $last->getNum() !== 0 && date( 'z/Y' ) === date( 'z/Y', $last->getCreationTimestamp() ) ) {
			throw new TaskException( "Page $last was already created." );
		}

		return $last->getNum();
	}

	/**
	 * Really creates the page WP:A/Riconferma_annuale/USERNAME/XXX
	 *
	 * @param string $title
	 * @param string $user
	 * @param array $groups
	 */
	protected function createPage( string $title, string $user, array $groups ) {
		$this->getLogger()->info( "Creating page $title" );
		$textParams = [
			'$user' => $user,
			'$date' => $groups['sysop'],
			'$burocrate' => $groups['bureaucrat'] ?? '',
			'$checkuser' => $groups['checkuser'] ?? ''
		];

		$params = [
			'title' => $title,
			'text' => $this->msg( 'ric-page-text' )->params( $textParams )->text(),
			'summary' => $this->msg( 'ric-page-summary' )->text()
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Creates the page WP:A/Riconferma_annuale/USERNAME if it doesn't exist
	 *
	 * @param string $title
	 * @param string $newText
	 */
	protected function createBasePage( string $title, string $newText ) {
		$this->getLogger()->info( "Creating base page $title" );

		$params = [
			'title' => $title,
			'text' => $newText,
			'summary' => $this->msg( 'base-page-summary' )->text()
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Updates the page WP:A/Riconferma_annuale/USERNAME if it already exists
	 * @param string $title
	 * @param string $newText
	 */
	protected function updateBasePage( string $title, string $newText ) {
		$this->getLogger()->info( "Updating base page $title" );

		$params = [
			'title' => $title,
			'appendtext' => "\n$newText",
			'summary' => $this->msg( 'base-page-summary-update' )->text()
		];

		$this->getController()->editPage( $params );
	}
}
