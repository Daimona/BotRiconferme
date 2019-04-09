<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\ContextSource;
use BotRiconferme\TaskDataProvider;

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
	 * Exception handler.
	 *
	 * @param \Throwable $ex
	 * @protected
	 */
	public function handleException( \Throwable $ex ) {
		$this->getLogger()->error(
			( new \ReflectionClass( $ex ) )->getShortName() . ': ' .
			$ex->getMessage() . "\nin " . $ex->getFile() . ' line ' .
			$ex->getLine() . "\nTrace:\n" . $ex->getTraceAsString()
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
