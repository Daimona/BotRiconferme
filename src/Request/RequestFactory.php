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
	 * @param string[] $cookies
	 * @param callable $cookiesCallback
	 * @return RequestBase
	 */
	public function createRequest( array $params, array $cookies, callable $cookiesCallback ) {
		$ret = extension_loaded( 'curl' )
			? new CurlRequest( $this->logger, $params, $this->domain, $cookiesCallback )
			: new NativeRequest( $this->logger, $params, $this->domain, $cookiesCallback );
		$ret->setCookies( $cookies );
		return $ret;
	}

	/**
	 * Similar to createRequest, but doesn't save any info like cookies.
	 *
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @return RequestBase
	 */
	public function createStandaloneRequest( array $params ) {
		$cookiesCallback = function ( array $newCookies ) {
			$this->logger->warning( 'Standalone request with set-cookie: ' . implode( ', ', array_keys( $newCookies ) ) );
		};
		return extension_loaded( 'curl' )
			? new CurlRequest( $this->logger, $params, $this->domain, $cookiesCallback )
			: new NativeRequest( $this->logger, $params, $this->domain, $cookiesCallback );
	}
}
