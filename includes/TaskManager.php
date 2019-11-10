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
use BotRiconferme\Task\Subtask\OpenUpdates;
use BotRiconferme\Task\Subtask\UserNotice;
use BotRiconferme\Task\Task;
use BotRiconferme\Task\UpdateList;
use BotRiconferme\Wiki\Controller;

/**
 * Wrapper for single tasks
 * @todo Reduce duplication with Task class and subclasses
 */
class TaskManager {
	// Run modes
	public const MODE_COMPLETE = 'full process';
	public const MODE_TASK = 'single task';
	public const MODE_SUBTASK = 'single subtask';

	/** @var string[] */
	private const TASKS_MAP = [
		'start-new' => StartNew::class,
		'close-old' => CloseOld::class,
		'update-list' => UpdateList::class,
		'start-vote' => StartVote::class
	];
	private const SUBTASKS_MAP = [
		'archive-pages' => ArchivePages::class,
		'close-pages' => ClosePages::class,
		'create-pages' => CreatePages::class,
		'failed-updates' => FailedUpdates::class,
		'simple-updates' => SimpleUpdates::class,
		'open-updates' => OpenUpdates::class,
		'user-notice' => UserNotice::class
	];
	/** @var TaskDataProvider */
	private $provider;
	/** @var Logger */
	private $logger;
	/** @var Controller */
	private $controller;

	/**
	 * @param Logger $logger
	 * @param Controller $controller
	 */
	public function __construct( Logger $logger, Controller $controller ) {
		$this->logger = $logger;
		$this->controller = $controller;
		$this->provider = new TaskDataProvider( $this->logger, $this->controller );
	}

	/**
	 * Main entry point
	 *
	 * @param string $mode One of the MODE_ constants
	 * @param string|null $name Only used in MODE_TASK and MODE_SUBTASK
	 * @return TaskResult
	 */
	public function run( string $mode, string $name = null ) : TaskResult {
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
		$orderedList = [
			'update-list',
			'start-new',
			'start-vote',
			'close-old'
		];

		$res = new TaskResult( TaskResult::STATUS_GOOD );
		do {
			$res->merge( $this->runTask( current( $orderedList ) ) );
		} while ( $res->isOK() && next( $orderedList ) );

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
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Task
	 */
	private function getTaskInstance( string $class ) : Task {
		return new $class( $this->logger, $this->controller, $this->provider );
	}

	/**
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Subtask
	 */
	private function getSubtaskInstance( string $class ) : Subtask {
		return new $class( $this->logger, $this->controller, $this->provider );
	}
}
