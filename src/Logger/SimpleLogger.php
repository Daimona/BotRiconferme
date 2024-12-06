<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Logger that just prints stuff to stdout
 */
class SimpleLogger extends AbstractLogger implements IFlushingAwareLogger {
	use LoggerTrait;

	private int $minLevel;

	/**
	 * @param string $minlevel
	 */
	public function __construct( string $minlevel = LogLevel::INFO ) {
		$this->minLevel = $this->levelToInt( $minlevel );
	}

	/**
	 * @inheritDoc
	 * @phan-param mixed[] $context
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, string|Stringable $message, array $context = [] ): void {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			echo $this->getFormattedMessage( $level, $message ) . "\n";
		}
	}

	/**
	 * @inheritDoc
	 */
	public function flush(): void {
		// Everything else is printed immediately
		echo "\n" . str_repeat( '-', 80 ) . "\n\n";
	}
}
