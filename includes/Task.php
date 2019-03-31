<?php

namespace BotRiconferme;

abstract class Task extends ContextSource {
	// Status codes
	const STATUS_OK = 0;
	const STATUS_ERROR = 1;

	/** @var WikiController */
	private $controller;

	public function __construct() {
		set_exception_handler( [ $this, 'handleException' ] );
		set_error_handler( [ $this, 'handleError' ] );
		parent::__construct();
		$this->controller = new WikiController;
	}

	/**
	 * Main routine
	 *
	 * @return int One of the STATUS_* constants
	 */
	abstract public function run() : int;

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
	 * @return WikiController
	 */
	protected function getController() : WikiController {
		return $this->controller;
	}
}
