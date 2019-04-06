<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\PageRiconferma;
use BotRiconferme\TaskResult;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;

/**
 * For each user, create the WP:A/Riconferma_annuale/USERNAME/XXX page and add it to its base page
 */
class CreatePages extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task CreatePages' );
		$users = $this->getDataProvider()->getUsersToProcess();

		if ( $users ) {
			foreach ( $users as $user => $groups ) {
				$this->processUser( $user, $groups );
			}
		} else {
			$this->getLogger()->info( 'No pages to create.' );
		}

		$this->getLogger()->info( 'Task CreatePages completed successfully' );
		return new TaskResult( self::STATUS_OK );
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
			$this->getLogger()->warning( $e->getMessage() . "\nRemoving $user." );
			return;
		}

		$baseTitle = $this->getConfig()->get( 'ric-main-page' ) . "/$user";
		$pageTitle = "$baseTitle/$num";
		$this->createPage( $pageTitle, $user, $groups );

		$newText = $this->msg( 'ric-base-page-text' )->params( [ '$title' => $pageTitle ] )->text();
		if ( $num === 1 ) {
			$this->createBasePage( $baseTitle, $newText );
		} else {
			$this->updateBasePage( $baseTitle, $newText );
		}

		$pageObj = new PageRiconferma( $pageTitle );
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
		$unprefixedTitle = explode( ':', $this->getConfig()->get( 'ric-main-page' ), 2 )[1];
		$params = [
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => 4,
			'apprefix' => "$unprefixedTitle/$user",
			'aplimit' => 'max'
		];

		$res = ( RequestBase::newFromParams( $params ) )->execute();

		$last = 0;
		foreach ( $res->query->allpages as $page ) {
			$createdOn = $this->getController()->getPageCreationTS( $page->title );
			if ( date( 'z/Y' ) === date( 'z/Y', $createdOn ) ) {
				// Was created today
				throw new TaskException( 'Page ' . $page->title . ' was already created.' );
			}

			$bits = explode( '/', $page->title );
			$cur = intval( end( $bits ) );
			if ( is_numeric( $cur ) && $cur > $last ) {
				$last = $cur;
			}
		}
		return $last;
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
		$text = $this->getConfig()->get( 'ric-page-text' );
		$textParams = [
			'$user' => $user,
			'$date' => $groups['sysop'],
			'$burocrate' => $groups['bureaucrat'] ?? '',
			'$checkuser' => $groups['checkuser'] ?? ''
		];
		$text = strtr( $text, $textParams );

		$params = [
			'title' => $title,
			'text' => $text,
			'summary' => $this->getConfig()->get( 'ric-page-summary' )
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
			'summary' => $this->getConfig()->get( 'ric-base-page-summary' )
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
			'appendtext' => $newText,
			'summary' => $this->getConfig()->get( 'ric-base-page-summary-update' )
		];

		$this->getController()->editPage( $params );
	}
}
