<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use Psr\Log\LoggerInterface;

class RequestFactory {
	/** @var string */
	private $domain;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param LoggerInterface $logger
	 * @param string $domain
	 */
	public function __construct( LoggerInterface $logger, string $domain ) {
		$this->logger = $logger;
		$this->domain = $domain;
	}

	/**
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @return RequestBase
	 */
	public function newFromParams( array $params ): RequestBase {
		if ( extension_loaded( 'curl' ) ) {
			return new CurlRequest( $this->logger, $params, $this->domain );
		}
		return new NativeRequest( $this->logger, $params, $this->domain );
	}
}
