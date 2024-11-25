<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\ArchivePages;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\Task\Subtask\FailedUpdates;
use BotRiconferme\Task\Subtask\SimpleUpdates;

/**
 * Task for closing old procedures
 */
class CloseOld extends Task {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): int {
		$orderedList = [
			'close-pages',
			'archive-pages',
			'simple-updates',
			'failed-updates'
		];

		return $this->runSubtaskList( $orderedList );
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
