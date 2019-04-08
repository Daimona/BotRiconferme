<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\ContextSource;
use BotRiconferme\TaskDataProvider;

/**
 * Base framework for all kind of tasks and subtasks
 */
abstract class TaskBase extends ContextSource {
	// Status codes. GOOD = everything fine, NOTHING = nothing to do, ERROR = found non-fatal errors
	const STATUS_GOOD = 0;
	const STATUS_NOTHING = 1;
	const STATUS_ERROR = 2;
	/** @var string[] */
	protected $errors = [];
	/** @var TaskDataProvider */
	protected $dataProvider;

	/**
	 * Final to keep calls linear in the TaskManager
	 *
	 * @param TaskDataProvider $dataProvider
	 */
	final public function __construct( TaskDataProvider $dataProvider ) {
		set_exception_handler( [ $this, 'handleException' ] );
		set_error_handler( [ $this, 'handleError' ] );
		parent::__construct();
		$this->dataProvider = $dataProvider;
	}

	public function __destruct() {
		restore_error_handler();
		restore_exception_handler();
	}

	/**
	 * Entry point
	 *
	 * @return TaskResult
	 */
	final public function run() : TaskResult {
		$task = static::class;
		$this->getLogger()->info( "Starting task $task" );

		$status = $this->runInternal();

		switch ( $status ) {
			case self::STATUS_GOOD:
				$msg = "Task $task completed successfully.";
				break;
			case self::STATUS_NOTHING:
				$msg = "Task $task: nothing to do.";
				break;
			case self::STATUS_ERROR:
				// We're fine with it, but don't run other tasks
				$msg = "Task $task completed with warnings.";
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
	 * Exception handler.
	 *
	 * @param \Throwable $ex
	 * @protected
	 */
	public function handleException( \Throwable $ex ) {
		$this->getLogger()->error(
			get_class( $ex ) . ': ' .
			$ex->getMessage() . "\nTrace:\n" .
			$ex->getTraceAsString()
		);
	}

	/**
	 * Error handler. As default, always throw
	 *
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @protected
	 */
	public function handleError( $errno, $errstr, $errfile, $errline ) {
		throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}

	/**
	 * @return TaskDataProvider
	 */
	protected function getDataProvider() : TaskDataProvider {
		return $this->dataProvider;
	}
}
