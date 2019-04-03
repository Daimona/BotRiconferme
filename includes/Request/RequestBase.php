<?php

namespace BotRiconferme\Request;

use BotRiconferme\Config;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\ProtectedPageException;

abstract class RequestBase {
	const USER_AGENT = 'Daimona - BotRiconferme 1.0 (https://github.com/Daimona/BotRiconferme)';

	const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];

	// In seconds
	const MAXLAG = 5;
	/** @var array */
	protected static $cookies;
	/** @var array */
	protected $params;
	/** @var string */
	protected $method;

	/**
	 * Use self::newFromParams, which will provide the right class to use
	 *
	 * @param array $params
	 * @param bool $isPOST
	 */
	protected function __construct( array $params, bool $isPOST = false ) {
		$this->params = [ 'format' => 'json' ] + $params;
		$this->method = $isPOST ? 'POST' : 'GET';
	}

	/**
	 * Instance getter, will instantiate the proper subclass
	 *
	 * @param array $params
	 * @param bool $isPOST
	 * @return self
	 */
	public static function newFromParams( array $params, bool $isPOST = false ) : self {
		if ( extension_loaded( 'curl' ) ) {
			$ret = new CurlRequest( $params, $isPOST );
		} else {
			$ret = new NativeRequest( $params, $isPOST );
		}
		return $ret;
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
			$res = $this->makeRequestInternal( $params );

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
			if ( isset( $res->continue ) ) {
				$params = array_merge( $params, get_object_vars( $res->continue ) );
				$finished = false;
			}
		} while ( !$finished );

		return $sets;
	}

	/**
	 * Perform an API request, either via cURL (if available) or file_get_contents
	 *
	 * @param array $params
	 * @return mixed
	 */
	private function makeRequestInternal( array $params ) {
		$url = Config::getInstance()->get( 'url' );

		$cookies = [];

		if ( $this->method === 'POST' ) {
			$params['maxlag'] = self::MAXLAG;
		}
		$params = http_build_query( $params );

		$body = $this->reallyMakeRequest( $url, $params );

		$this->setCookies( $cookies );
		return json_decode( $body );
	}

	/**
	 * Actual method which will make the request
	 *
	 * @param string $url
	 * @param string $params
	 * @return string
	 */
	abstract protected function reallyMakeRequest( string $url, string $params ) : string;

	/**
	 * @param array $cookies
	 */
	protected function setCookies( array $cookies ) {
		foreach ( $cookies as $cookie ) {
			$bits = explode( ';', $cookie );
			list( $name, $value ) = explode( '=', $bits[0] );
			self::$cookies[ $name ] = $value;
		}
	}

	/**
	 * @return array
	 */
	protected function getHeaders() :array {
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
	 * @param array $headers
	 * @return string
	 */
	protected function buildHeadersString( array $headers ) : string {
		$ret = '';
		foreach ( $headers as $header ) {
			$ret .= "$header\r\n";
		}
		return $ret;
	}
}
