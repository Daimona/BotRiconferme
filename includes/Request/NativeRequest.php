<?php

namespace BotRiconferme\Request;

/**
 * Request done via file_get_contents, when cURL isn't available
 */
class NativeRequest extends RequestBase {
	/**
	 * @inheritDoc
	 */
	protected function reallyMakeRequest( string $url, string $params ) : string {
		$context = [
			'http' => [
				'method' => $this->method,
				'header' => $this->buildHeadersString( $this->getHeaders() )
			]
		];
		if ( $this->method === 'POST' ) {
			$context['http']['content'] = $params;
		} else {
			$url = "$url?$params";
		}
		$context = stream_context_create( $context );
		$body = file_get_contents( $url, false, $context );

		$cookies = [];
		foreach ( $http_response_header as $header ) {
			$bits = explode( ':', $header, 2 );
			if ( trim( $bits[0] ) === 'Set-Cookie' ) {
				$cookies[] = $bits[1];
			}
		}
		$this->setCookies( $cookies );
		return $body;
	}
}
