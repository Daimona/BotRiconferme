<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Task\TaskBase;

/**
 * Base class for subtasks
 */
abstract class Subtask extends TaskBase {
	/**
	 * @inheritDoc
	 */
	final protected function getOperationName(): string {
		return 'subtask';
	}
}
