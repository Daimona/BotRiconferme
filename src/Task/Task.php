<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Task\Subtask\Subtask;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\TaskHelper\TaskResult;
use InvalidArgumentException;

/**
 * Base class for a high-level task.
 */
abstract class Task extends TaskBase {
	/**
	 * Get a map of [ 'task name' => 'its class name' ]
	 *
	 * @return string[]
	 */
	abstract protected function getSubtasksMap(): array;

	/**
	 * @phan-param non-empty-list<string> $orderedList
	 */
	protected function runSubtaskList( array $orderedList ): Status {
		$res = new TaskResult( Status::NOTHING );
		do {
			$subtask = current( $orderedList );
			assert( is_string( $subtask ) );
			$res->merge( $this->runSubtask( $subtask ) );
		} while ( $res->isOK() && next( $orderedList ) );

		return $res->getStatus();
	}

	/**
	 * @param string $subtask Defined in self::SUBTASKS_MAP
	 */
	protected function runSubtask( string $subtask ): TaskResult {
		$map = $this->getSubtasksMap();
		$class = $map[ $subtask ] ?? throw new InvalidArgumentException( "'$subtask' is not a valid task." );
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
	 * @phan-param class-string<Subtask> $class
	 */
	private function getSubtaskInstance( string $class ): Subtask {
		return new $class(
			$this->getLogger(),
			$this->getWikiGroup(),
			$this->getMessageProvider(),
			$this->getBotList(),
			$this->getDataProvider()
		);
	}
}
