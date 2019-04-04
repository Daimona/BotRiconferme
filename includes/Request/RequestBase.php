<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Bot;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\ProtectedPageException;

/**
 * Core wrapper for an API request. Current implementations use either cURL or file_get_contents
 */
abstract class RequestBase {
	const USER_AGENT = 'Daimona - BotRiconferme ' . Bot::VERSION .
		' (https://github.com/Daimona/BotRiconferme)';
	const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];
	// In seconds
	const MAXLAG = 5;

	/** @var string */
	public static $url = 'https://it.wikipedia.org/w/api.php';
	/** @var array */
	protected static $cookiesToSet;
	/** @var array */
	protected $params;
	/** @var string */
	protected $method;
	/** @var string[] */
	protected $newCookies = [];
	/** @var int */
	private $limit = -1;

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
	 * Set a limit to the amount of returned results. -1 means no limit
	 *
	 * @param int $val
	 */
	public function setResultLimit( int $val ) {
		$this->limit = $val;
	}

	/**
	 * Entry point for an API request
	 *
	 * @return \stdClass
	 */
	public function execute() : \stdClass {
		$curParams = $this->params;
		$sets = [];
		$amount = 0;
		do {
			$res = $this->makeRequestInternal( $curParams );
			$amount += count( $res );

			$this->handleErrorAndWarnings( $res );
			$sets[] = $res;

			$finished = true;
			if ( isset( $res->continue ) && $this->limit > -1 && $amount < $this->limit ) {
				$curParams = array_merge( $curParams, get_object_vars( $res->continue ) );
				$finished = false;
			}
		} while ( !$finished );

		return $this->mergeSets( $sets );
	}

	/**
	 * Process parameters and call the actual request method
	 *
	 * @param array $params
	 * @return \stdClass
	 */
	private function makeRequestInternal( array $params ) : \stdClass {
		if ( $this->method === 'POST' ) {
			$params['maxlag'] = self::MAXLAG;
		}
		$params = http_build_query( $params );

		$body = $this->reallyMakeRequest( $params );

		$this->setCookies( $this->newCookies );
		return json_decode( $body );
	}

	/**
	 * Actual method which will make the request
	 *
	 * @param string $params
	 * @return string
	 */
	abstract protected function reallyMakeRequest( string $params ) : string;

	/**
	 * After a request, set cookies for the next ones
	 *
	 * @param array $cookies
	 */
	protected function setCookies( array $cookies ) {
		foreach ( $cookies as $cookie ) {
			$bits = explode( ';', $cookie );
			list( $name, $value ) = explode( '=', $bits[0] );
			self::$cookiesToSet[ $name ] = $value;
		}
	}

	/**
	 * Handle known warning and errors from an API request
	 *
	 * @param \stdClass $res
	 * @throws APIRequestException
	 */
	protected function handleErrorAndWarnings( $res ) {
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
			$act = $this->params[ 'action' ];
			$warning = $res->warnings->$act;
			throw new APIRequestException( reset( $warning ) );
		}
	}

	/**
	 * Merge results from multiple requests in a single object
	 *
	 * @param \stdClass[] $sets
	 * @return \stdClass
	 */
	private function mergeSets( array $sets ) : \stdClass {
		// Use the first set as template
		$ret = array_shift( $sets );

		foreach ( $sets as $set ) {
			$ret = $this->recursiveMerge( $ret, $set );
		}
		return $ret;
	}

	/**
	 * Recursively merge objects, keeping the structure
	 *
	 * @param array|\stdClass $first
	 * @param array|\stdClass $second
	 * @return array|\stdClass array
	 */
	private function recursiveMerge( $first, $second ) {
		$ret = $first;
		if ( is_array( $second ) ) {
			$ret = is_array( $first ) ? array_merge_recursive( $first, $second ) : $second;
		} elseif ( is_object( $second ) ) {
			foreach ( get_object_vars( $second ) as $key => $val ) {
				$ret->$key = isset( $first->$key ) ? $this->recursiveMerge( $first->$key, $val ) : $val;
			}
		}

		return $ret;
	}

	/**
	 * Get the headers to use for a new request
	 *
	 * @return array
	 */
	protected function getHeaders() :array {
		$ret = self::HEADERS;
		if ( self::$cookiesToSet ) {
			$cookies = [];
			foreach ( self::$cookiesToSet as $cname => $cval ) {
				$cookies[] = trim( "$cname=$cval" );
			}
			$ret[] = 'Cookie: ' . implode( '; ', $cookies );
		}
		return $ret;
	}

	/**
	 * Utility function to implode headers
	 *
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
