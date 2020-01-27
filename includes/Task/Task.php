<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\Subtask;
use BotRiconferme\TaskResult;

/**
 * Base class for a high-level task.
 */
abstract class Task extends TaskBase {
	/**
	 * Get a map of [ 'task name' => 'its class name' ]
	 *
	 * @return string[]
	 */
	abstract protected function getSubtasksMap() : array;

	/**
	 * @param string $subtask Defined in self::SUBTASKS_MAP
	 * @return TaskResult
	 */
	protected function runSubtask( string $subtask ) : TaskResult {
		$map = $this->getSubtasksMap();
		if ( !isset( $map[ $subtask ] ) ) {
			throw new \InvalidArgumentException( "'$subtask' is not a valid task." );
		}

		$class = $map[ $subtask ];
		return $this->getSubtaskInstance( $class )->run();
	}

	/**
	 * @inheritDoc
	 */
	final public function getOperationName(): string {
		return 'task';
	}

	/**
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Subtask
	 */
	private function getSubtaskInstance( string $class ) : Subtask {
		/** @var Subtask $ret */
		$ret = new $class(
			$this->getLogger(),
			$this->getWikiGroup(),
			$this->getDataProvider(),
			$this->getMessageProvider(),
			$this->getBotList()
		);
		return $ret;
	}
}
