<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\TaskResult;

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

		if ( !$pages || !$users ) {
			return TaskResult::STATUS_NOTHING;
		}

		$ricNums = [];
		foreach ( $pages as $page ) {
			$ricNums[ $page->getUser()->getName() ] = $page->getNum();
		}

		foreach ( $users as $user => $_ ) {
			$this->addMsg( $user, $ricNums[ $user ] );
		}

		return TaskResult::STATUS_GOOD;
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
			'sectiontitle' => $this->msg( 'user-notice-sectiontitle' )->text(),
			'summary' => $this->msg( 'user-notice-summary' )->text()
		];

		$this->getWiki()->editPage( $params );
	}
}
