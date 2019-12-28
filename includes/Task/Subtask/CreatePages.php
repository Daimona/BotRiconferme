<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

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

		$baseTitle = $this->getOpt( 'main-page-title' ) . "/$user";
		$pageTitle = "$baseTitle/$num";
		$this->createPage( $pageTitle, $user );

		$newText = $this->msg( 'base-page-text' )->params( [ '$title' => $pageTitle ] )->text();
		if ( $num === 1 ) {
			$this->createBasePage( $baseTitle, $newText );
		} else {
			$this->updateBasePage( $baseTitle, $newText );
		}

		$pageObj = new PageRiconferma( $pageTitle, $this->getWiki() );
		$this->getDataProvider()->addCreatedPages( $pageObj );
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

		// Little hack to have getNum() return 0
		$last = new PageRiconferma( 'X/Y/Z/0', $this->getWiki() );
		foreach ( $res->query->allpages as $resPage ) {
			$page = new PageRiconferma( $resPage->title, $this->getWiki() );

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

		$this->getWiki()->editPage( $params );
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

		$this->getWiki()->editPage( $params );
	}
}
