<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Logger that just prints stuff to stdout
 */
class SimpleLogger extends AbstractLogger {
	use LoggerTrait;

	/** @var int */
	private $minLevel;

	/**
	 * @param string $minlevel
	 */
	public function __construct( $minlevel = LogLevel::INFO ) {
		$this->minLevel = $this->levelToInt( $minlevel );
	}

	/**
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, $message, array $context = [] ) {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			echo $this->getFormattedMessage( $level, $message );
		}
	}
}
