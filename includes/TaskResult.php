<?php declare( strict_types=1 );

namespace BotRiconferme;

class TaskResult {
	// Status codes
	const STATUS_OK = 0;
	const STATUS_ERROR = 1;

	/** @var string[] */
	private $errors;

	/** @var int */
	private $status;

	/**
	 * @param int $status One of the Task::STATUS_* constants
	 * @param string[] $errors
	 */
	public function __construct( int $status, array $errors = [] ) {
		$this->status = $status;
		$this->errors = $errors;
	}

	/**
	 * @return int
	 */
	public function getStatus() : int {
		return $this->status;
	}

	/**
	 * @return string[]
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * @param TaskResult $that
	 */
	public function merge( TaskResult $that ) {
		$this->status |= $that->status;
		$this->errors = array_merge( $this->errors, $that->errors );
	}

	/**
	 * @return string
	 */
	public function __toString() {
		if ( $this->isOK() ) {
			$stat = 'OK';
			$errs = "\tNo errors.";
		} else {
			$stat = 'ERROR';
			$formattedErrs = [];
			foreach ( $this->errors as $err ) {
				$formattedErrs[] = "\t - $err";
			}
			$errs = implode( "\n", $formattedErrs );
		}
		return "=== RESULT ===\n - Status: $stat\n - Errors:\n$errs\n";
	}

	/**
	 * Shorthand
	 *
	 * @return bool
	 */
	public function isOK() : bool {
		return $this->status === self::STATUS_OK;
	}
}
