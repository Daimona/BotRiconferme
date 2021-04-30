<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\User;

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
			$ricNums[ $page->getUserName() ] = $page->getNum();
		}

		foreach ( $users as $name => $user ) {
			$this->addMsg( $user, $ricNums[ $name ] );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Leaves a message to the talk page
	 *
	 * @param User $user
	 * @param int $ricNum
	 */
	protected function addMsg( User $user, int $ricNum ) : void {
		$this->getLogger()->info( "Leaving msg to $user" );
		$msg = $this->msg( 'user-notice-msg' )->params( [ '$num' => $ricNum ] )->text();

		$params = [
			'section' => 'new',
			'text' => $msg,
			'sectiontitle' => $this->msg( 'user-notice-sectiontitle' )->text(),
			'summary' => $this->msg( 'user-notice-summary' )->text()
		];

		$user->getTalkPage()->edit( $params );
	}
}
