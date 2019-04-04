<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Exception\APIRequestException;

/**
 * Request done using cURL, if available
 */
class CurlRequest extends RequestBase {
	/**
	 * @inheritDoc
	 */
	protected function reallyMakeRequest( string $params ) : string {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, true );
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION, [ $this, 'headersHandler' ] );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->getHeaders() );

		$url = self::$url;
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
	 * @internal Only used as CB for cURL
	 */
	public function headersHandler( $ch, string $header ) : int {
		$bits = explode( ':', $header, 2 );
		if ( trim( $bits[0] ) === 'Set-Cookie' ) {
			$this->newCookies[] = $bits[1];
		}
		// @phan-suppress-next-line PhanTypeMismatchReturn WTF? Why does phan thinks this is a string?
		return strlen( $header );
	}
}
