<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Proxies calls to multiple loggers
 */
class MultiLogger extends AbstractLogger implements IFlushingAwareLogger {
	/** @var IFlushingAwareLogger[] */
	private array $loggers;

	/**
	 * @param IFlushingAwareLogger ...$loggers
	 */
	public function __construct( IFlushingAwareLogger ...$loggers ) {
		$this->loggers = $loggers;
	}

	/**
	 * @inheritDoc
	 * @phan-param mixed[] $context
	 */
	public function log( $level, string|Stringable $message, array $context = [] ): void {
		foreach ( $this->loggers as $logger ) {
			$logger->log( $level, $message );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function flush(): void {
		foreach ( $this->loggers as $logger ) {
			$logger->flush();
		}
	}
}
