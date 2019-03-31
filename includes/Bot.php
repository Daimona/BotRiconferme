<?php

class Bot {
	/** @var string[] */
	const TASKS_MAP = [
		'update-list' => UpdateList::class
	];

	/**
	 * @param string $task
	 */
	public function runTask( string $task ) {
		if ( !isset( self::TASKS_MAP[ $task ] ) ) {
			throw new InvalidArgumentException( "'$task' is not a valid task." );
		}
		$class = self::TASKS_MAP[ $task ];
		/** @var Task $task */
		$task = new $class;
		$task->run();
	}
}
