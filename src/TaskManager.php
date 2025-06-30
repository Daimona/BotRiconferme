<?php declare( strict_types=1 );

namespace BotRiconferme;

use BadMethodCallException;
use BotRiconferme\Message\MessageProvider;
use BotRiconferme\Task\CloseOld;
use BotRiconferme\Task\StartNew;
use BotRiconferme\Task\StartVote;
use BotRiconferme\Task\Subtask\ArchivePages;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\Task\Subtask\CreatePages;
use BotRiconferme\Task\Subtask\FailedUpdates;
use BotRiconferme\Task\Subtask\OpenUpdates;
use BotRiconferme\Task\Subtask\SimpleUpdates;
use BotRiconferme\Task\Subtask\Subtask;
use BotRiconferme\Task\Subtask\UserNotice;
use BotRiconferme\Task\Task;
use BotRiconferme\Task\UpdateList;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\TaskHelper\TaskDataProvider;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\WikiGroup;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Wrapper for single tasks
 * @todo Reduce duplication with Task class and subclasses
 */
class TaskManager {
	// Run modes
	public const MODE_COMPLETE = 'full';
	public const MODE_TASK = 'task';
	public const MODE_SUBTASK = 'subtask';

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
	private const FULL_RUN_ORDERED = [
		'update-list',
		'start-new',
		'start-vote',
		'close-old'
	];
	private TaskDataProvider $provider;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly WikiGroup $wikiGroup,
		private readonly MessageProvider $messageProvider,
		private readonly PageBotList $pageBotList
	) {
		$this->provider = new TaskDataProvider(
			$this->logger,
			$this->wikiGroup,
			$this->messageProvider,
			$pageBotList
		);
	}

	/**
	 * Main entry point
	 *
	 * @param string $mode One of the MODE_ constants
	 * @param string[] $tasks Only used in MODE_TASK and MODE_SUBTASK
	 */
	public function run( string $mode, array $tasks = [] ): TaskResult {
		if ( $mode === self::MODE_COMPLETE ) {
			return $this->runTasks( self::FULL_RUN_ORDERED );
		}
		if ( !$tasks ) {
			throw new BadMethodCallException( 'MODE_TASK and MODE_SUBTASK need at least a (sub)task name.' );
		}
		return $mode === self::MODE_TASK ? $this->runTasks( $tasks ) : $this->runSubtasks( $tasks );
	}

	/**
	 * Run $tasks in the given order
	 *
	 * @param string[] $tasks
	 */
	private function runTasks( array $tasks ): TaskResult {
		$res = new TaskResult( Status::GOOD );
		do {
			$curTask = current( $tasks );
			assert( is_string( $curTask ) );
			$res->merge( $this->runTask( $curTask ) );
		} while ( $res->isOK() && next( $tasks ) );

		return $res;
	}

	/**
	 * Run a single task
	 */
	protected function runTask( string $name ): TaskResult {
		if ( !isset( self::TASKS_MAP[ $name ] ) ) {
			throw new InvalidArgumentException( "'$name' is not a valid task." );
		}

		return $this->getTaskInstance( $name )->run();
	}

	/**
	 * Run $subtasks in the given order
	 *
	 * @param string[] $subtasks
	 */
	private function runSubtasks( array $subtasks ): TaskResult {
		$res = new TaskResult( Status::GOOD );
		do {
			$subtask = current( $subtasks );
			assert( is_string( $subtask ) );
			$res->merge( $this->runSubtask( $subtask ) );
		} while ( $res->isOK() && next( $subtasks ) );

		return $res;
	}

	/**
	 * Run a single subtask
	 */
	protected function runSubtask( string $name ): TaskResult {
		if ( !isset( self::SUBTASKS_MAP[ $name ] ) ) {
			throw new InvalidArgumentException( "'$name' is not a valid subtask." );
		}

		$class = self::SUBTASKS_MAP[ $name ];
		return $this->getSubtaskInstance( $class )->run();
	}

	/**
	 * Helper to make type inferencing easier
	 */
	private function getTaskInstance( string $name ): Task {
		$class = self::TASKS_MAP[ $name ];
		'@phan-var class-string<Task> $class';
		return new $class(
			$this->logger,
			$this->wikiGroup,
			$this->messageProvider,
			$this->pageBotList,
			$this->provider
		);
	}

	/**
	 * Helper to make type inferencing easier
	 * @phan-param class-string<Subtask> $class
	 */
	private function getSubtaskInstance( string $class ): Subtask {
		return new $class(
			$this->logger,
			$this->wikiGroup,
			$this->messageProvider,
			$this->pageBotList,
			$this->provider
		);
	}
}
