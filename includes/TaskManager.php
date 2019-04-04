<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Task\Task;
use BotRiconferme\Task\CreatePages;
use BotRiconferme\Task\UpdateList;
use BotRiconferme\Task\UpdatesAround;
use BotRiconferme\Task\UserNotice;

/**
 * Wrapper for single tasks
 */
class TaskManager {
	// Run modes
	const MODE_COMPLETE = 0;
	const MODE_SINGLE = 1;

	// File where the date of the last full run is stored
	const LOG_FILE = './lastrun.log';
	/** @var string[] */
	const TASKS_MAP = [
		'create-pages' => CreatePages::class,
		'update-list' => UpdateList::class,
		'updates-around' => UpdatesAround::class,
		'user-notice' => UserNotice::class,
	];
	/** @var TaskDataProvider */
	private $provider;

	/**
	 * Should only be used for debugging purpose.
	 */
	public static function resetLastRunDate() {
		file_put_contents( self::LOG_FILE, '' );
	}

	/**
	 * Main entry point
	 *
	 * @param int $mode One of the MODE_ constants
	 * @param string|null $taskName Only used in MODE_SINGLE
	 * @return TaskResult
	 */
	public function run( int $mode, string $taskName = null ) : TaskResult {
		$this->provider = new TaskDataProvider;
		if ( $mode === self::MODE_COMPLETE ) {
			return $this->runAllTasks();
		} elseif ( $taskName === null ) {
			throw new \BadMethodCallException( 'A task name must be specified in MODE_SINGLE' );
		} else {
			return $this->runTask( $taskName );
		}
	}

	/**
	 * @return TaskResult
	 */
	protected function runAllTasks() : TaskResult {
		if ( self::getLastFullRunDate() === date( 'd/m/Y' ) ) {
			// Really avoid executing twice the same day
			return new TaskResult( TaskResult::STATUS_ERROR, [ 'A full run was already executed today.' ] );
		}

		// Order matters here
		$list = [
			'update-list',
			'create-pages',
			'updates-around',
			'user-notice'
		];

		$res = new TaskResult( TaskResult::STATUS_OK );
		do {
			$res->merge( $this->runTask( current( $list ) ) );
		} while ( $res->isOK() && next( $list ) );

		if ( $res->isOK() ) {
			self::setLastFullRunDate();
		}

		return $res;
	}

	/**
	 * Get the last execution date to ensure no more than one full run is executed every day
	 * @return string|null d/m/Y or null if no last run registered
	 */
	public static function getLastFullRunDate() : ?string {
		if ( file_exists( self::LOG_FILE ) ) {
			return file_get_contents( self::LOG_FILE ) ?: null;
		} else {
			return null;
		}
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
		return $this->getTaskInstance( $class )->run();
	}

	/**
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Task
	 */
	private function getTaskInstance( string $class ) : Task {
		return new $class( $this->provider );
	}

	public static function setLastFullRunDate() {
		file_put_contents( self::LOG_FILE, date( 'd/m/Y' ) );
	}
}
