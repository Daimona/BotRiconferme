<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\ArchivePages;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\Task\Subtask\FailedUpdates;
use BotRiconferme\Task\Subtask\SimpleUpdates;
use BotRiconferme\TaskHelper\TaskResult;

/**
 * Task for closing old procedures
 */
class CloseOld extends Task {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$orderedList = [
			'close-pages',
			'archive-pages',
			'simple-updates',
			'failed-updates'
		];

		$res = new TaskResult( TaskResult::STATUS_NOTHING );
		do {
			$res->merge( $this->runSubtask( current( $orderedList ) ) );
		} while ( $res->isOK() && next( $orderedList ) );

		return $res->getStatus();
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
