<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\ContextSource;
use BotRiconferme\MessageProvider;
use BotRiconferme\TaskDataProvider;
use BotRiconferme\TaskResult;
use BotRiconferme\Wiki\Wiki;
use Psr\Log\LoggerInterface;

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
	 * @param Wiki $wiki
	 * @param TaskDataProvider $dataProvider
	 * @param MessageProvider $mp
	 */
	final public function __construct(
		LoggerInterface $logger,
		Wiki $wiki,
		TaskDataProvider $dataProvider,
		MessageProvider $mp
	) {
		parent::__construct( $logger, $wiki, $mp );
		$this->dataProvider = $dataProvider;
	}

	/**
	 * Entry point
	 *
	 * @return TaskResult
	 */
	final public function run() : TaskResult {
		$class = ( new \ReflectionClass( $this ) )->getShortName();
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
				throw new \LogicException( "Unexpected status: $status." );
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
