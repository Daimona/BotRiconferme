<?php

namespace BotRiconferme;

class UserNotice extends Task {
	/** @var array */
	private $usersList;

	/**
	 * @inheritDoc
	 */
	public function run() : int {
		$this->getLogger()->info( 'Starting task UserNotice' );

		foreach ( $this->getUsersList() as $user ) {
			$this->addMsg( $user );
		}

		$this->getLogger()->info( 'Task UserNotice completed successfully' );
		return self::STATUS_OK;
	}

	/**
	 * @return array
	 */
	protected function getUsersList() : array {
		$this->getLogger()->debug( 'Retrieving users list' );
		$content = $this->getController()->getPageContent( $this->getConfig()->get( 'list-title' ) );
		$users = json_decode( $content, true );
		$now = time();
		$period = $this->getConfig()->get( 'time-period' );

		$ret = [];
		foreach ( $users as $user => $time ) {
			if ( $now - $time > $period ) {
				$ret[] = $user;
			}
		}
		return $ret;
	}

	/**
	 * @param string $user
	 */
	protected function addMsg( string $user ) {
		$this->getLogger()->info( "Leaving msg to $user" );

		$params = [
			'action' => 'edit',
			'title' => "User talk:$user",
			'section' => 'new',
			'text' => $this->getConfig()->get( 'user-notice-msg' ),
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
