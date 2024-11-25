<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\LogLevel;
use Stringable;

trait LoggerTrait {
	/**
	 * Translate a LogLevel constant to an integer
	 *
	 * @param string $level
	 * @return int
	 */
	protected function levelToInt( string $level ): int {
		// Order matters
		$mapping = [
			LogLevel::DEBUG,
			LogLevel::INFO,
			LogLevel::NOTICE,
			LogLevel::WARNING,
			LogLevel::ERROR,
			LogLevel::CRITICAL,
			LogLevel::ALERT,
			LogLevel::EMERGENCY
		];
		return array_search( $level, $mapping, true );
	}

	protected function getFormattedMessage( string $level, string|Stringable $message ): string {
		return sprintf( '%s [%s] - %s', date( 'd M H:i:s' ), $level, $message );
	}
}
