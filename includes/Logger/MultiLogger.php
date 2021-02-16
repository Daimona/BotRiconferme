<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\AbstractLogger;

/**
 * Proxies calls to multiple loggers
 */
class MultiLogger extends AbstractLogger implements IFlushingAwareLogger {
	/** @var IFlushingAwareLogger[] */
	private $loggers;

	/**
	 * @param IFlushingAwareLogger ...$loggers
	 */
	public function __construct( IFlushingAwareLogger ...$loggers ) {
		$this->loggers = $loggers;
	}

	/**
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter,PhanPluginUnknownArrayMethodParamType
	 */
	public function log( $level, $message, array $context = [] ) :void {
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
