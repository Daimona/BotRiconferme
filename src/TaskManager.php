<?php declare( strict_types=1 );

namespace BotRiconferme;

use BadMethodCallException;
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
	private const FULL_RUN_ORDERED = [
		'update-list',
		'start-new',
		'start-vote',
		'close-old'
	];
	private TaskDataProvider $provider;
	private LoggerInterface $logger;
	private WikiGroup $wikiGroup;
	private MessageProvider $messageProvider;
	private PageBotList $pageBotList;

	/**
	 * @param LoggerInterface $logger
	 * @param WikiGroup $wikiGroup
	 * @param MessageProvider $mp
	 * @param PageBotList $pbl
	 */
	public function __construct(
		LoggerInterface $logger,
		WikiGroup $wikiGroup,
		MessageProvider $mp,
		PageBotList $pbl
	) {
		$this->logger = $logger;
		$this->wikiGroup = $wikiGroup;
		$this->messageProvider = $mp;
		$this->pageBotList = $pbl;
		$this->provider = new TaskDataProvider(
			$this->logger,
			$this->wikiGroup,
			$this->messageProvider,
			$pbl
		);
	}

	/**
	 * Main entry point
	 *
	 * @param string $mode One of the MODE_ constants
	 * @param string[] $tasks Only used in MODE_TASK and MODE_SUBTASK
	 * @return TaskResult
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
	 * @return TaskResult
	 */
	private function runTasks( array $tasks ): TaskResult {
		$res = new TaskResult( TaskResult::STATUS_GOOD );
		do {
			$res->merge( $this->runTask( current( $tasks ) ) );
		} while ( $res->isOK() && next( $tasks ) );

		return $res;
	}

	/**
	 * Run a single task
	 *
	 * @param string $name
	 * @return TaskResult
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
	 * @return TaskResult
	 */
	private function runSubtasks( array $subtasks ): TaskResult {
		$res = new TaskResult( TaskResult::STATUS_GOOD );
		do {
			$res->merge( $this->runSubtask( current( $subtasks ) ) );
		} while ( $res->isOK() && next( $subtasks ) );

		return $res;
	}

	/**
	 * Run a single subtask
	 *
	 * @param string $name
	 * @return TaskResult
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
	 *
	 * @param string $name
	 * @return Task
	 */
	private function getTaskInstance( string $name ): Task {
		$class = self::TASKS_MAP[ $name ];
		return new $class(
			$this->logger,
			$this->wikiGroup,
			$this->provider,
			$this->messageProvider,
			$this->pageBotList
		);
	}

	/**
	 * Helper to make type inferencing easier
	 *
	 * @param string $class
	 * @return Subtask
	 */
	private function getSubtaskInstance( string $class ): Subtask {
		return new $class(
			$this->logger,
			$this->wikiGroup,
			$this->provider,
			$this->messageProvider,
			$this->pageBotList
		);
	}
}
