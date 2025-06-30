<?php declare( strict_types=1 );

namespace BotRiconferme\TaskHelper;

/**
 * Object wrapping the result of the execution of a task.
 */
class TaskResult {
	/**
	 * @param Status $status
	 * @param string[] $errors
	 */
	public function __construct(
		private Status $status,
		private array $errors = []
	) {
	}

	public function getStatus(): Status {
		return $this->status;
	}

	/**
	 * @return string[]
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function getErrors(): array {
		return $this->errors;
	}

	public function merge( TaskResult $that ): void {
		$this->status = $that->status->combinedWith( $that->status );
		$this->errors = array_merge( $this->errors, $that->errors );
	}

	public function __toString(): string {
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
	 */
	public function isOK(): bool {
		return $this->status->isOK();
	}
}
