<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Request\Exception\APIRequestException;

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
		// @phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		$body = @file_get_contents( $url, false, $context );

		if ( $body === false ) {
			$httpStatus = http_get_last_response_headers()[0] ?? '(unset)';
			throw new APIRequestException( "Can't make request to $url. HTTP status: $httpStatus" );
		}

		foreach ( $http_response_header as $header ) {
			$this->handleResponseHeader( $header );
		}

		return $body;
	}
}
