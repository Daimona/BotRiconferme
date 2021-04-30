<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\ContextSource;
use BotRiconferme\MessageProvider;
use BotRiconferme\TaskHelper\TaskDataProvider;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\WikiGroup;
use LogicException;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Base framework for all kind of tasks and subtasks
 */
abstract class TaskBase extends ContextSource {
	/** @var string[] */
	protected $errors = [];
	/** @var TaskDataProvider */
	protected $dataProvider;

	/**
	 * Final to keep calls linear in the TaskManager
	 *
	 * @param LoggerInterface $logger
	 * @param WikiGroup $wikiGroup
	 * @param TaskDataProvider $dataProvider
	 * @param MessageProvider $mp
	 * @param PageBotList $pbl
	 */
	final public function __construct(
		LoggerInterface $logger,
		WikiGroup $wikiGroup,
		TaskDataProvider $dataProvider,
		MessageProvider $mp,
		PageBotList $pbl
	) {
		parent::__construct( $logger, $wikiGroup, $mp, $pbl );
		$this->dataProvider = $dataProvider;
	}

	/**
	 * Entry point
	 *
	 * @return TaskResult
	 */
	final public function run() : TaskResult {
		$class = ( new ReflectionClass( $this ) )->getShortName();
		$opName = $this->getOperationName();
		$this->getLogger()->info( "Starting $opName $class" );

		$status = $this->runInternal();

		switch ( $status ) {
			case TaskResult::STATUS_GOOD:
				$msg = ucfirst( $opName ) . " $class completed successfully.";
				break;
			case TaskResult::STATUS_NOTHING:
				$msg = ucfirst( $opName ) . " $class: nothing to do.";
				break;
			case TaskResult::STATUS_ERROR:
				// We're fine with it, but don't run other tasks
				$msg = ucfirst( $opName ) . " $class completed with warnings.";
				break;
			default:
				throw new LogicException( "Unexpected status: $status." );
		}

		$this->getLogger()->info( $msg );
		return new TaskResult( $status, $this->errors );
	}

	/**
	 * Actual main routine.
	 *
	 * @return int One of the STATUS_* constants
	 */
	abstract protected function runInternal() : int;

	/**
	 * How this operation should be called in logs
	 *
	 * @return string
	 */
	abstract public function getOperationName() : string;

	/**
	 * @return TaskDataProvider
	 */
	protected function getDataProvider() : TaskDataProvider {
		return $this->dataProvider;
	}
}
