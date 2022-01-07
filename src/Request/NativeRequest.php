<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

/**
 * Request done via file_get_contents, when cURL isn't available
 */
class NativeRequest extends RequestBase {
	/**
	 * @inheritDoc
	 */
	protected function reallyMakeRequest( string $params ): string {
		$context = [
			'http' => [
				'method' => $this->method,
				'header' => $this->buildHeadersString( $this->getHeaders() )
			]
		];
		$url = $this->url;
		if ( $this->method === self::METHOD_POST ) {
			$context['http']['content'] = $params;
		} else {
			$url = "$url?$params";
		}
		$context = stream_context_create( $context );
		$body = file_get_contents( $url, false, $context );

		foreach ( $http_response_header as $header ) {
			/** @var string[] $bits */
			$bits = explode( ':', $header, 2 );
			if ( trim( $bits[0] ) === 'Set-Cookie' ) {
				$this->newCookies[] = $bits[1];
			}
		}

		return $body;
	}
}
