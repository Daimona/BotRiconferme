<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;

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

		foreach ( $this->getDataProvider()->getUsersToProcess() as $user => $_ ) {
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
		$msg = str_replace( '$num', "$ricNum", $this->getConfig()->get( 'user-notice-msg' ) );

		$params = [
			'title' => "User talk:$user",
			'section' => 'new',
			'text' => $msg,
			'sectiontitle' => $this->getConfig()->get( 'user-notice-title' ),
			'summary' => $this->getConfig()->get( 'user-notice-summary' )
		];

		$this->getController()->editPage( $params );
	}
}
