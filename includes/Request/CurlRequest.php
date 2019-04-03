<?php

namespace BotRiconferme\Request;

use BotRiconferme\Exception\APIRequestException;

/**
 * Request done using cURL, if available
 */
class CurlRequest extends RequestBase {
	/**
	 * @inheritDoc
	 */
	protected function reallyMakeRequest( string $url, string $params ) : string {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, true );
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION, [ $this, 'headersHandler' ] );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->getHeaders() );

		if ( $this->method === 'POST' ) {
			curl_setopt( $curl, CURLOPT_URL, $url );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $params );
		} else {
			curl_setopt( $curl, CURLOPT_URL, "$url?$params" );
		}

		$result = curl_exec( $curl );

		if ( $result === false ) {
			throw new APIRequestException( curl_error( $curl ) );
		}

		// Extract response body
		$headerSize = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
		$body = substr( $result, $headerSize );
		curl_close( $curl );

		return $body;
	}

	/**
	 * cURL's headers handler
	 *
	 * @param Resource $ch
	 * @param string $header
	 * @return int
	 */
	private function headersHandler( $ch, string $header ) {
		$bits = explode( ':', $header, 2 );
		if ( trim( $bits[0] ) === 'Set-Cookie' ) {
			$this->newCookies[] = $bits[1];
		}
		return strlen( $header );
	}
}
