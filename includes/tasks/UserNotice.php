<?php

namespace BotRiconferme;

class UserNotice extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UserNotice' );

		$ricNums = [];
		foreach ( $this->getDataProvider()->getCreatedPages() as $page ) {
			$bits = explode( '/', $page );
			$num = intval( array_pop( $bits ) );
			$ricNums[ array_pop( $bits ) ] = $num;
		}
		
		foreach ( $this->getDataProvider()->getUsersToProcess() as $user ) {
			$this->addMsg( $user, $ricNums[ $user ] );
		}

		$this->getLogger()->info( 'Task UserNotice completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param string $user
	 * @param int $ricNum
	 */
	protected function addMsg( string $user, int $ricNum ) {
		$this->getLogger()->info( "Leaving msg to $user" );
		$msg = str_replace( '$1', $ricNum, $this->getConfig()->get( 'user-notice-msg' ) );

		$params = [
			'action' => 'edit',
			'title' => "User talk:$user",
			'section' => 'new',
			'text' => $msg,
			'sectiontitle' => $this->getConfig()->get( 'user-notice-title' ),
			'summary' => $this->getConfig()->get( 'user-notice-summary' ),
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
