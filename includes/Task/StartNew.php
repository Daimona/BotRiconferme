<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\CreatePages;
use BotRiconferme\Task\Subtask\UpdatesAround;
use BotRiconferme\Task\Subtask\UserNotice;
use BotRiconferme\TaskResult;

/**
 * Task for opening new procedures
 */
class StartNew extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$orderedList = [
			'update-list',
			'create-pages',
			'updates-around',
			'user-notice'
		];

		$res = new TaskResult( TaskResult::STATUS_OK );
		do {
			$res->merge( $this->runSubtask( current( $orderedList ) ) );
		} while ( $res->isOK() && next( $orderedList ) );

		return $res;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubtasksMap() : array {
		return [
			'create-pages' => CreatePages::class,
			'update-list' => UpdateList::class,
			'updates-around' => UpdatesAround::class,
			'user-notice' => UserNotice::class,
		];
	}
}
