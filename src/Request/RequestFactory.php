<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use Psr\Log\LoggerInterface;

readonly class RequestFactory {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly string $domain
	) {
	}

	/**
	 * @param array<int|string|bool> $params
	 * @param string[] $cookies
	 */
	public function createRequest( array $params, array $cookies, callable $cookiesCallback ): RequestBase {
		$ret = extension_loaded( 'curl' )
			? new CurlRequest( $this->logger, $params, $this->domain, $cookiesCallback )
			: new NativeRequest( $this->logger, $params, $this->domain, $cookiesCallback );
		$ret->setCookies( $cookies );
		return $ret;
	}
}
