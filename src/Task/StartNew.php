<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\CreatePages;
use BotRiconferme\Task\Subtask\OpenUpdates;
use BotRiconferme\Task\Subtask\UserNotice;

/**
 * Task for opening new procedures
 */
class StartNew extends Task {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): int {
		$orderedList = [
			'create-pages',
			'open-updates',
			'user-notice'
		];

		return $this->runSubtaskList( $orderedList );
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubtasksMap(): array {
		return [
			'create-pages' => CreatePages::class,
			'open-updates' => OpenUpdates::class,
			'user-notice' => UserNotice::class,
		];
	}
}
