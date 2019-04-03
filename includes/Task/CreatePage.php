<?php

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;

class CreatePage extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task CreatePage' );
		$users = $this->getDataProvider()->getUsersToProcess();

		foreach ( $users as $user => $groups ) {
			$this->processUser( $user, $groups );
		}

		$this->getLogger()->info( 'Task CreatePage completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param string $user
	 * @param array $groups
	 */
	protected function processUser( string $user, array $groups ) {
		try {
			$num = $this->getLastPageNum( $user ) + 1;
		} catch ( TaskException $e ) {
			// The page was already created.
			$this->getDataProvider()->removeUser( $user );
			$this->getLogger()->warning( $e->getMessage() . "\nRemoving $user." );
			return;
		}

		$baseTitle = $this->getConfig()->get( 'ric-main-page' ) . "/$user";
		$pageTitle = "$baseTitle/$num";
		$this->doCreatePage( $pageTitle, $user, $groups );

		$newText = str_replace( '$title', $pageTitle, $this->getConfig()->get( 'ric-base-page-text' ) );
		if ( $num === 1 ) {
			$this->createBasePage( $baseTitle, $newText );
		} else {
			$this->updateBasePage( $baseTitle, $newText );
		}
		$this->getDataProvider()->addCreatedPages( $pageTitle );
	}

	/**
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
			if ( $this->pageWasCreatedToday( $page->title ) ) {
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
	 * @param string $title
	 * @return bool
	 */
	private function pageWasCreatedToday( string $title ) : bool {
		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $title,
			'rvprop' => 'timestamp',
			'rvslots' => 'main',
			'rvlimit' => 1,
			'rvdir' => 'newer'
		];

		$res = ( RequestBase::newFromParams( $params ) )->execute();
		$data = $res->query->pages;
		$time = strtotime( reset( $data )->revisions[0]->timestamp );
		return date( 'z/Y' ) === date( 'z/Y', $time );
	}

	/**
	 * @param string $title
	 * @param string $user
	 * @param array $groups
	 */
	protected function doCreatePage( string $title, string $user, array $groups ) {
		$this->getLogger()->info( "Creating page $title" );
		$text = $this->getConfig()->get( 'ric-page-text' );
		$textParams = [
			'$user' => $user,
			'$date' => $groups['sysop'],
			'$quorum' => '',##################################################################Handle on-wiki
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
