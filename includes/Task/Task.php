<?php declare(strict_types=1);

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\ContextSource;
use BotRiconferme\TaskDataProvider;

abstract class Task extends ContextSource {
	// Status codes
	const STATUS_OK = 0;
	const STATUS_ERROR = 1;
	/** @var string[] */
	protected $errors = [];
	/** @var TaskDataProvider */
	private $dataProvider;

	/**
	 * @param TaskDataProvider $dataProvider
	 */
	public function __construct( TaskDataProvider $dataProvider ) {
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
	 * Main routine
	 *
	 * @return TaskResult
	 */
	abstract public function run() : TaskResult;

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
