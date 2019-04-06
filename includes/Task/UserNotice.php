<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;

/**
 * Notify the affected users
 */
class UserNotice extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UserNotice' );

		$pages = $this->getDataProvider()->getCreatedPages();
		$users = $this->getDataProvider()->getUsersToProcess();
		if ( $pages && $users ) {
			$ricNums = [];
			foreach ( $pages as $page ) {
				$ricNums[ $page->getUser() ] = $page->getNum();
			}

			foreach ( $users as $user => $_ ) {
				$this->addMsg( $user, $ricNums[ $user ] );
			}
		} else {
			$this->getLogger()->info( 'No messages to leave.' );
		}

		$this->getLogger()->info( 'Task UserNotice completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Leaves a message to the talk page
	 *
	 * @param string $user
	 * @param int $ricNum
	 */
	protected function addMsg( string $user, int $ricNum ) {
		$this->getLogger()->info( "Leaving msg to $user" );
		$msg = $this->msg( 'user-notice-msg' )->params( [ '$num' => $ricNum ] )->text();

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
