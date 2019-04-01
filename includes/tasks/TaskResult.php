<?php

namespace BotRiconferme;

class TaskResult {
	// Status codes
	const STATUS_OK = 0;
	const STATUS_ERROR = 1;

	/** @var mixed */
	private $value;
	
	/** @var int */
	private $status;

	/**
	 * @param int $status One of the Task::STATUS_* constants
	 * @param mixed $value
	 */
	public function __construct( int $status, $value ) {
		$this->status = $status;
		$this->value = $value;
	}

	/**
	 * @return int
	 */
	public function getStatus() : int {
		return $this->status;
	}

	/**
	 * @return mixed
	 */
	public function getValue() {
		return $this->value;
	}
}
