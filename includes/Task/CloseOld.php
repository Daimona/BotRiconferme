<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\ClosePages;
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
			'close-pages'
		];

		$res = new TaskResult( TaskResult::STATUS_OK );
		do {
			$res->merge( $this->runSubtask( current( $orderedList ) ) );
		} while ( $res->isOK() && next( $list ) );

		return $res;
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubtasksMap(): array {
		return [
			'close-pages' => ClosePages::class
		];
	}
}
