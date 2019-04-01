<?php

namespace BotRiconferme;

class CreatePage extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task CreatePage' );
		$users = $this->getDataProvider()->getUsersToProcess();

		$created = [];
		foreach ( $users as $user ) {
			$num = $this->getLastPageNum( $user ) + 1;
			$pageTitle = $this->getConfig()->get( 'ric-page-prefix' ) . "/$user/$num";
			$this->doCreatePage( $pageTitle );
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
		$params = [
			'action' => 'query',
			'list' => 'allpages',
			'apnamespace' => 4,
			'apprefix' => $this->getConfig()->get( 'ric-page-prefix' ) . "/$user",
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
	 */
	protected function doCreatePage( string $title ) {
		$this->getLogger()->info( "Creating page $title" );

		$params = [
			'action' => 'edit',
			'title' => $title,
			'text' => $this->getConfig()->get( 'ric-page-text' ),
			'summary' => $this->getConfig()->get( 'ric-page-summary' ),
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
