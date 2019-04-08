<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Task\CloseOld;
use BotRiconferme\Task\StartNew;
use BotRiconferme\Task\StartVote;
use BotRiconferme\Task\Subtask\ArchivePages;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\Task\Subtask\CreatePages;
use BotRiconferme\Task\Subtask\FailedUpdates;
use BotRiconferme\Task\Subtask\SimpleUpdates;
use BotRiconferme\Task\Subtask\Subtask;
use BotRiconferme\Task\Subtask\UpdatesAround;
use BotRiconferme\Task\Subtask\UserNotice;
use BotRiconferme\Task\Task;
use BotRiconferme\Task\UpdateList;

/**
 * Wrapper for single tasks
 * @fixme Reduce duplication with Task class and subclasses
 */
class TaskManager {
	// Run modes
	const MODE_COMPLETE = 0;
	const MODE_TASK = 1;
	const MODE_SUBTASK = 2;

	// File where the date of the last full run is stored
	const LOG_FILE = './lastrun.log';
	/** @var string[] */
	const TASKS_MAP = [
		'start-new' => StartNew::class,
		'close-old' => CloseOld::class,
		'update-list' => UpdateList::class,
		'start-vote' => StartVote::class
	];
	const SUBTASKS_MAP = [
		'archive-pages' => ArchivePages::class,
		'close-pages' => ClosePages::class,
		'create-pages' => CreatePages::class,
		'failed-updates' => FailedUpdates::class,
		'simple-updates' => SimpleUpdates::class,
		'updates-around' => UpdatesAround::class,
		'user-notice' => UserNotice::class
	];
	/** @var TaskDataProvider */
	private $provider;

	/**
	 * Main entry point
	 *
	 * @param int $mode One of the MODE_ constants
	 * @param string|null $name Only used in MODE_TASK and MODE_SUBTASK
	 * @return TaskResult
	 */
	public function run( int $mode, string $name = null ) : TaskResult {
		$this->provider = new TaskDataProvider;

		if ( $mode === self::MODE_COMPLETE ) {
			return $this->runAllTasks();
		} elseif ( $name === null ) {
			throw new \BadMethodCallException( 'MODE_TASK and MODE_SUBTASK need a (sub)task name.' );
		} else {
			return $mode === self::MODE_TASK ? $this->runTask( $name ) : $this->runSubtask( $name );
		}
	}

	/**
	 * Run everything
	 *
	 * @return TaskResult
	 */
	protected function runAllTasks() : TaskResult {
		if ( self::getLastFullRunDate() === date( 'd/m/Y' ) ) {
			// Really avoid executing twice the same day
			return new TaskResult( TaskResult::STATUS_ERROR, [ 'A full run was already executed today.' ] );
		}

		$orderedList = [
			'update-list',
			'start-new',
			'start-vote',
			'close-old'
		];

		$res = new TaskResult( TaskResult::STATUS_GOOD );
		do {
			$res->merge( $this->runSubtask( current( $orderedList ) ) );
		} while ( $res->isOK() && next( $orderedList ) );

		if ( $res->isOK() ) {
			self::setLastFullRunDate();
		}

		return $res;
	}

	/**
	 * Run a single task
	 *
	 * @param string $name
	 * @return TaskResult
	 */
	protected function runTask( string $name ) : TaskResult {
		if ( !isset( self::TASKS_MAP[ $name ] ) ) {
			throw new \InvalidArgumentException( "'$name' is not a valid task." );
		}

		$class = self::TASKS_MAP[ $name ];
		return $this->getTaskInstance( $class )->run();
	}

	/**
	 * Run a single subtask
	 *
	 * @param string $name
	 * @return TaskResult
	 */
	protected function runSubtask( string $name ) : TaskResult {
		if ( !isset( self::SUBTASKS_MAP[ $name ] ) ) {
			throw new \InvalidArgumentException( "'$name' is not a valid subtask." );
		}

		$class = self::SUBTASKS_MAP[ $name ];
		return $this->getSubtaskInstance( $class )->run();
	}

	/**
	 * Get the last execution date to ensure no more than one full run is executed every day
	 * @return string|null d/m/Y or null if no last run registered
	 * @fixme Is this even necessary?
	 */
	public static function getLastFullRunDate() : ?string {
		if ( file_exists( self::LOG_FILE ) ) {
			return file_get_contents( self::LOG_FILE ) ?: null;
		} else {
			return null;
		}
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

	/**
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Subtask
	 */
	private function getSubtaskInstance( string $class ) : Subtask {
		return new $class( $this->provider );
	}

	public static function setLastFullRunDate() {
		file_put_contents( self::LOG_FILE, date( 'd/m/Y' ) );
	}
}
