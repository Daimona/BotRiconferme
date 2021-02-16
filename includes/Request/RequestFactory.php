<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

class RequestFactory {
	/** @var string */
	private $domain;

	/**
	 * @param string $domain
	 */
	public function __construct( string $domain ) {
		$this->domain = $domain;
	}

	/**
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @return RequestBase
	 */
	public function newFromParams( array $params ) : RequestBase {
		if ( extension_loaded( 'curl' ) ) {
			$ret = new CurlRequest( $params, $this->domain );
		} else {
			$ret = new NativeRequest( $params, $this->domain );
		}
		return $ret;
	}
}
