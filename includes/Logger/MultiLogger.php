<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Proxies calls to multiple loggers
 */
class MultiLogger extends AbstractLogger implements IFlushingAwareLogger {
	/** @var IFlushingAwareLogger[] */
	private $loggers = [];

	/**
	 * @param IFlushingAwareLogger ...$loggers
	 */
	public function __construct( IFlushingAwareLogger ...$loggers ) {
		$this->loggers = $loggers;
	}

	/**
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, $message, array $context = [] ) {
		foreach ( $this->loggers as $logger ) {
			$logger->log( $level, $message );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function flush() : void {
		foreach ( $this->loggers as $logger ) {
			$logger->flush();
		}
	}
}
