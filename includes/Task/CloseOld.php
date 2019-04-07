<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\ArchivePages;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\Task\Subtask\FailedUpdates;
use BotRiconferme\Task\Subtask\SimpleUpdates;
use BotRiconferme\TaskResult;

/**
 * Task for closing old procedures
 */
class CloseOld extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$orderedList = [
			'close-pages',
			'archive-pages',
			'simple-updates',
			'failed-updates'
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
	protected function getSubtasksMap(): array {
		return [
			'archive-pages' => ArchivePages::class,
			'close-pages' => ClosePages::class,
			'failed-updates' => FailedUpdates::class,
			'simple-updates' => SimpleUpdates::class
		];
	}
}
