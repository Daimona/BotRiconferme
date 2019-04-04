<?php declare( strict_types=1 );

namespace BotRiconferme;

use Psr\Log\AbstractLogger;

/**
 * Implementation for a PSR-3 logger
 */
class Logger extends AbstractLogger {
	/**
	 * @inheritDoc
	 */
	public function log( $level, $message, array $context = [] ) {
		error_log( "$level - $message" );
	}
}
