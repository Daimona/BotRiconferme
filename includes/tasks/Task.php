<?php

namespace BotRiconferme;

abstract class Task extends ContextSource {
	// Status codes
	const STATUS_OK = 0;
	const STATUS_ERROR = 1;

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

	/**
	 * Main routine
	 *
	 * @return TaskResult
	 */
	abstract public function run() : TaskResult;

	/**
	 * Exception handler
	 *
	 * @param \Throwable $ex
	 * @internal To be used as exception handler only
	 */
	abstract public function handleException( \Throwable $ex );

	/**
	 * Error handler
	 *
	 * @param int $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param int $errline
	 * @internal To be used as error handler only
	 */
	abstract public function handleError( $errno, $errstr, $errfile, $errline );

	/**
	 * @return TaskDataProvider
	 */
	protected function getDataProvider() : TaskDataProvider {
		return $this->dataProvider;
	}
}