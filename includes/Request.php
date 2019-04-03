<?php

namespace BotRiconferme;

use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\ProtectedPageException;

class Request {
	const USER_AGENT = 'Daimona - BotRiconferme 1.0 (https://github.com/Daimona/BotRiconferme)';

	const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];

	// In seconds
	const MAXLAG = 5;

	/** @var array */
	private $params;

	/** @var string */
	private $method;

	/** @var array */
	private static $cookies;

	/**
	 * @param array $params
	 * @param bool $isPOST
	 */
	public function __construct( array $params, $isPOST = false ) {
		$this->params = [ 'format' => 'json' ] + $params;
		$this->method = $isPOST ? 'POST' : 'GET';
	}

	/**
	 * Make an API request
	 *
	 * @return array
	 * @throws APIRequestException
	 */
	public function execute() : array {
		$params = $this->params;
		$sets = [];
		do {
			$res = $this->reallyMakeRequest( $params );

			if ( isset( $res->error ) ) {
				switch ( $res->error->code ) {
					case 'missingtitle':
						$ex = new MissingPageException;
						break;
					case 'protectedpage':
						$ex = new ProtectedPageException;
						break;
					default:
						$ex = new APIRequestException( $res->error->code . ' - ' . $res->error->info );
				}
				throw $ex;
			} elseif ( isset( $res->warnings ) ) {
				$act = $params[ 'action' ];
				if ( is_string( $act ) ) {
					$warning = $res->warnings->$act;
				} elseif ( is_array( $act ) ) {
					$warning = $res->warnings->{ $act[0] };
				} else {
					$warning = reset( $res->warnings );
				}
				throw new APIRequestException( reset( $warning ) );
			}

			$sets[] = $res;

			$finished = true;
			if ( !isset( $res->batchcomplete ) && isset( $res->continue ) ) {
				$params = array_merge( $params, get_object_vars( $res->continue ) );
				$finished = false;
			}
		} while ( !$finished );

		return $sets;
	}

	/**
	 * @param array $cookies
	 */
	private function setCookies( array $cookies ) {
		foreach ( $cookies as $cookie ) {
			$bits = explode( ';', $cookie );
			list( $name, $value ) = explode( '=', $bits[0] );
			self::$cookies[ $name ] = $value;
		}
	}

	/**
	 * @return array
	 */
	private function getHeaders() :array {
		$ret = self::HEADERS;
		if ( self::$cookies ) {
			$cookies = [];
			foreach ( self::$cookies as $cname => $cval ) {
				$cookies[] = trim( "$cname=$cval" );
			}
			$ret[] = 'Cookie: ' . implode( '; ', $cookies );
		}
		return $ret;
	}
	/**
	 * Perform an API request, either via cURL (if available) or file_get_contents
	 *
	 * @param array $params
	 * @return mixed
	 */
	private function reallyMakeRequest( array $params ) {
		$url = Config::getInstance()->get( 'url' );
		$headers = $this->getHeaders();

		$cookies = [];

		if ( $this->method === 'POST' ) {
			$params['maxlag'] = self::MAXLAG;
		}
		$params = http_build_query( $params );

		if ( extension_loaded( 'curl' ) ) {
			$headersHandler = function ( $ch, $header ) use ( &$cookies ) {
				$bits = explode( ':', $header, 2 );
				if ( trim( $bits[0] ) === 'Set-Cookie' ) {
					$cookies[] = $bits[1];
				}
				return strlen( $header );
			};

			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_HEADER, true );
			curl_setopt( $curl, CURLOPT_HEADERFUNCTION, $headersHandler );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );

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
		} else {
			$context = [
				'http' => [
					'method' => $this->method,
					'header' => $this->buildHeadersString( $headers )
				]
			];
			if ( $this->method === 'POST' ) {
				$context['http']['content'] = $params;
			} else {
				$url = "$url?$params";
			}
			$context = stream_context_create( $context );
			$body = file_get_contents( $url, false, $context );

			foreach ( $http_response_header as $header ) {
				$bits = explode( ':', $header, 2 );
				if ( trim( $bits[0] ) === 'Set-Cookie' ) {
					$cookies[] = $bits[1];
				}
			}
		}

		$this->setCookies( $cookies );
		return json_decode( $body );
	}

	/**
	 * @param array $headers
	 * @return string
	 */
	private function buildHeadersString( array $headers ) : string {
		$ret = '';
		foreach ( $headers as $header ) {
			$ret .= "$header\r\n";
		}
		return $ret;
	}
}
