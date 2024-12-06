<?php /** @noinspection PhpComposerExtensionStubsInspection */
declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Request\Exception\APIRequestException;
use BotRiconferme\Request\Exception\TimeoutException;
use CurlHandle;
use RuntimeException;

/**
 * Request done using cURL, if available
 */
class CurlRequest extends RequestBase {
	/**
	 * @inheritDoc
	 */
	protected function reallyMakeRequest( string $params ): string {
		$curl = curl_init();
		if ( $curl === false ) {
			throw new RuntimeException( 'Cannot open cURL handler.' );
		}
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_HEADER, true );
		curl_setopt( $curl, CURLOPT_HEADERFUNCTION, [ $this, 'headersHandler' ] );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $this->getHeaders() );

		if ( $this->method === self::METHOD_POST ) {
			curl_setopt( $curl, CURLOPT_URL, $this->url );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $params );
		} else {
			curl_setopt( $curl, CURLOPT_URL, "{$this->url}?$params" );
		}

		$result = curl_exec( $curl );

		if ( $result === false ) {
			$debugUrl = $this->getDebugURL( $params );
			if ( curl_errno( $curl ) === CURLE_OPERATION_TIMEDOUT ) {
				throw new TimeoutException( "Curl timeout for $debugUrl" );
			}
			throw new APIRequestException( "Curl error for $debugUrl: " . curl_error( $curl ) );
		}

		// Extract response body
		$headerSize = curl_getinfo( $curl, CURLINFO_HEADER_SIZE );
		assert( is_string( $result ), 'Result must be string when RETURNTRANSFER is set' );
		$body = substr( $result, $headerSize );
		curl_close( $curl );

		return $body;
	}

	/**
	 * cURL's headers handler
	 *
	 * @param CurlHandle $ch
	 * @param string $header
	 * @return int
	 * @internal Only used as CB for cURL (CURLOPT_HEADERFUNCTION)
	 * @suppress PhanUnreferencedPublicMethod,PhanUnusedPublicNoOverrideMethodParameter
	 */
	public function headersHandler( CurlHandle $ch, string $header ): int {
		$this->handleResponseHeader( $header );
		return strlen( $header );
	}
}
