<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Proxies calls to multiple loggers
 */
class MultiLogger extends AbstractLogger {
	/** @var LoggerInterface[] */
	private $loggers = [];

	/**
	 * @param LoggerInterface ...$loggers
	 */
	public function __construct( LoggerInterface ...$loggers ) {
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
}
