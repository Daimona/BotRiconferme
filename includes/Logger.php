<?php declare( strict_types=1 );

namespace BotRiconferme;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Implementation for a PSR-3 logger
 */
class Logger extends AbstractLogger {
	/** @var int */
	private $minLevel;

	/**
	 * @param string $minlevel
	 */
	public function __construct( $minlevel = LogLevel::INFO ) {
		$this->minLevel = $this->levelToInt( $minlevel );
	}

	/**
	 * Translate a LogLevel constant to an integer
	 *
	 * @param string $level
	 * @return int
	 */
	private function levelToInt( string $level ) : int {
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
		return array_search( $level, $mapping );
	}

	/**
	 * @inheritDoc
	 */
	public function log( $level, $message, array $context = [] ) {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			printf( "%s [%s] - %s\n", date( 'd M H:i:s' ), $level, $message );
		}
	}
}
