<?php

namespace BotRiconferme;

class TaskManager {
	// Run modes
	const MODE_COMPLETE = 0;
	const MODE_SINGLE = 1;

	/** @var TaskDataProvider */
	private $provider;

	/** @var string[] */
	const TASKS_MAP = [
		'create-page' => CreatePage::class,
		'update-list' => UpdateList::class,
		'updates-around' => UpdatesAround::class,
		'user-notice' => UserNotice::class,
	];

	/**
	 * @param int $mode One of the MODE_ constants
	 * @param string|null $taskName Only used in MODE_SINGLE
	 * @return TaskResult
	 */
	public function run( int $mode, string $taskName = null ) : TaskResult {
		$this->provider = new TaskDataProvider;
		if ( $mode === self::MODE_COMPLETE ) {
			return $this->runAllTasks();
		} else {
			return $this->runTask( $taskName );
		}
	}

	/**
	 * @return TaskResult
	 */
	protected function runAllTasks() : TaskResult {
		// Order matters here
		$list = [
			'update-list',
			'create-page',
			'updates-around',
			'user-notice'
		];

		$res = new TaskResult( TaskResult::STATUS_OK );
		do {
			$res->merge( $this->runTask( current( $list ) ) );
		} while ( $res->isOK() && next( $list ) );

		return $res;
	}

	/**
	 * @param string $task Defined in self::TASKS_MAP
	 * @return TaskResult
	 */
	protected function runTask( string $task ) : TaskResult {
		if ( !isset( self::TASKS_MAP[ $task ] ) ) {
			throw new \InvalidArgumentException( "'$task' is not a valid task." );
		}

		$class = self::TASKS_MAP[ $task ];
		/** @var Task $task */
		$task = new $class( $this->provider );
		return $task->run();
	}
}
