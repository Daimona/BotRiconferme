<?php

namespace BotRiconferme\Tasks;

use BotRiconferme\TaskResult;
use BotRiconferme\Request;
use BotRiconferme\Exceptions\TaskException;

class CreatePage extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task CreatePage' );
		$users = $this->getDataProvider()->getUsersToProcess();

		$created = [];
		foreach ( $users as $user => $groups ) {
			$num = $this->getLastPageNum( $user ) + 1;
			$baseTitle = $this->getConfig()->get( 'ric-main-page' ) . "/$user";
			$pageTitle = "$baseTitle/$num";
			$this->doCreatePage( $pageTitle, $user, $groups );

			$newText = str_replace( '$title', $baseTitle, $this->getConfig()->get( 'ric-base-page-text' ) );
			if ( $num === 1 ) {
				$this->createBasePage( $baseTitle, $newText );
			} else {
				$this->updateBasePage( $baseTitle, $newText );
			}
			$created[] = $pageTitle;
		}

		$this->getLogger()->info( 'Task CreatePage completed successfully' );
		$this->getDataProvider()->setCreatedPages( $created );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param string $user
	 * @return int
	 */
	protected function getLastPageNum( string $user ) : int {
		$this->getLogger()->debug( "Retrieving previous pages for $user" );
		$baseTitle = explode( ':', $this->getConfig()->get( 'ric-main-page' ), 2 )[1];
		$params = [
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => 4,
			'apprefix' => "$baseTitle/$user",
			'aplimit' => 'max'
		];

		$res = ( new Request( $params ) )->execute();

		$last = 0;
		foreach ( $res as $set ) {
			foreach ( $set->query->allpages as $page ) {
				$created = $this->getPageCreationTS( $page->title );
				if ( date( 'z/Y' ) === date( 'z/Y', $created ) ) {
					throw new TaskException( 'Page ' . $page->title . ' was already created.' );
				}
				$bits = explode( '/', $page->title );
				$cur = end( $bits );
				if ( is_numeric( $cur ) && $cur > $last ) {
					$last = intval( $cur );
				}
			}
		}
		return $last;
	}

	/**
	 * @param string $title
	 * @return int
	 */
	private function getPageCreationTS( string $title ) : int {
		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $title,
			'rvprop' => 'timestamp',
			'rvslots' => 'main',
			'rvlimit' => 1,
			'rvdir' => 'newer'
		];

		$res = ( new Request( $params ) )->execute();
		$data = $res[0]->query->pages;
		return strtotime( reset( $data )->revisions->timestamp );
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
			'$quorum' => ''##################################################################Handle on-wiki
		];
		$text = strtr( $text, $textParams );

		$params = [
			'action' => 'edit',
			'title' => $title,
			'text' => $text,
			'summary' => $this->getConfig()->get( 'ric-page-summary' ),
			'bot' => 1,
			'token' => $this->getController()->getToken( 'csrf' )
		];

		$this->getController()->login();
		$req = new Request( $params, true );
		$req->execute();
	}

	/**
	 * @param string $title
	 * @param string $newText
	 */
	protected function createBasePage( string $title, string $newText ) {
		$this->getLogger()->info( "Creating base page $title" );

		$params = [
			'action' => 'edit',
			'title' => $title,
			'text' => $newText,
			'summary' => $this->getConfig()->get( 'ric-base-page-summary' ),
			'bot' => 1,
			'token' => $this->getController()->getToken( 'csrf' )
		];

		$this->getController()->login();
		$req = new Request( $params, true );
		$req->execute();
	}

	/**
	 * @param string $title
	 * @param string $newText
	 */
	protected function updateBasePage( string $title, string $newText ) {
		$this->getLogger()->info( "Updating base page $title" );

		$params = [
			'action' => 'edit',
			'title' => $title,
			'appendtext' => $newText,
			'summary' => $this->getConfig()->get( 'ric-base-page-summary-update' ),
			'bot' => 1,
			'token' => $this->getController()->getToken( 'csrf' )
		];

		$this->getController()->login();
		$req = new Request( $params, true );
		$req->execute();
	}

	/**
	 * @inheritDoc
	 * Throw everything
	 */
	public function handleException( \Throwable $ex ) {
		$this->getLogger()->error( $ex->getMessage() );
	}

	/**
	 * @inheritDoc
	 * Abort on anything
	 */
	public function handleError( $errno, $errstr, $errfile, $errline ) {
		throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
}
