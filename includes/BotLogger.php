<?php

use Psr\Log\AbstractLogger;

class BotLogger extends AbstractLogger {
	/**
	 * @inheritDoc
	 */
	public function log( $level, $message, array $context = [] ) {
		error_log( "$level - $message" );
	}
}