<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

/**
 * Notify the affected users
 */
class UserNotice extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
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

		return self::STATUS_OK;
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
