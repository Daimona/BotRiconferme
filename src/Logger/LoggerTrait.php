<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use BotRiconferme\Clock;
use LogicException;
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
		$intLevel = array_search( $level, $mapping, true );
		if ( $intLevel === false ) {
			throw new LogicException( "Unexpected log level $level" );
		}
		return $intLevel;
	}

	protected function getFormattedMessage( string $level, string|Stringable $message ): string {
		return sprintf( '%s [%s] - %s', Clock::getDate( 'd M H:i:s' ), $level, $message );
	}
}
