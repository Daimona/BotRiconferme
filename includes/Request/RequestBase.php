<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Bot;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\PermissionDeniedException;
use BotRiconferme\Exception\ProtectedPageException;

/**
 * Core wrapper for an API request. Current implementations use either cURL or file_get_contents
 */
abstract class RequestBase {
	protected const USER_AGENT = 'Daimona - BotRiconferme ' . Bot::VERSION .
		' (https://github.com/Daimona/BotRiconferme)';
	protected const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];
	// In seconds
	protected const MAXLAG = 5;

	protected const METHOD_GET = 'GET';
	protected const METHOD_POST = 'POST';

	/** @var string */
	protected $url;
	/** @var array */
	protected static $cookiesToSet;
	/** @var array */
	protected $params;
	/** @var string */
	protected $method = self::METHOD_GET;
	/** @var string[] */
	protected $newCookies = [];

	/**
	 * Use self::newFromParams, which will provide the right class to use
	 *
	 * @param array $params
	 */
	protected function __construct( array $params ) {
		$this->params = [ 'format' => 'json' ] + $params;
		$this->url = DEFAULT_URL;
	}

	/**
	 * Instance getter, will instantiate the proper subclass
	 *
	 * @param array $params
	 * @return self
	 */
	public static function newFromParams( array $params ) : self {
		if ( extension_loaded( 'curl' ) ) {
			$ret = new CurlRequest( $params );
		} else {
			$ret = new NativeRequest( $params );
		}
		return $ret;
	}

	/**
	 * Set the method to POST
	 *
	 * @return self For chaining
	 */
	public function setPost() : self {
		$this->method = self::METHOD_POST;
		return $this;
	}

	/**
	 * Set a different URL for this request
	 *
	 * @param string $url
	 * @return self for chaining
	 */
	public function setUrl( string $url ) : self {
		$this->url = $url;
		return $this;
	}

	/**
	 * Entry point for an API request
	 *
	 * @return \stdClass
	 * @todo Return an iterable object which automatically continues the query only if the last
	 *   entry available is reached.
	 */
	public function execute() : \stdClass {
		$curParams = $this->params;
		$sets = [];
		do {
			$res = $this->makeRequestInternal( $curParams );

			$this->handleErrorAndWarnings( $res );
			$sets[] = $res;

			$finished = true;
			if ( isset( $res->continue ) ) {
				$curParams = get_object_vars( $res->continue ) + $curParams;
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
		if ( $this->method === self::METHOD_POST ) {
			$params['maxlag'] = self::MAXLAG;
		}
		$query = http_build_query( $params );

		$body = $this->reallyMakeRequest( $query );

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
	protected function setCookies( array $cookies ) : void {
		foreach ( $cookies as $cookie ) {
			$bits = explode( ';', $cookie );
			[ $name, $value ] = explode( '=', $bits[0] );
			self::$cookiesToSet[ $name ] = $value;
		}
	}

	/**
	 * Get a specific exception class depending on the error code
	 *
	 * @param \stdClass $res
	 * @return APIRequestException
	 */
	private function getException( \stdClass $res ) : APIRequestException {
		switch ( $res->error->code ) {
			case 'missingtitle':
				$ex = new MissingPageException;
				break;
			case 'protectedpage':
				$ex = new ProtectedPageException;
				break;
			case 'permissiondenied':
				$ex = new PermissionDeniedException( $res->error->info );
				break;
			default:
				$ex = new APIRequestException( $res->error->code . ' - ' . $res->error->info );
		}
		return $ex;
	}

	/**
	 * Handle known warning and errors from an API request
	 *
	 * @param \stdClass $res
	 * @throws APIRequestException
	 */
	protected function handleErrorAndWarnings( \stdClass $res ) : void {
		if ( isset( $res->error ) ) {
			throw $this->getException( $res );
		} elseif ( isset( $res->warnings ) ) {
			$act = $this->params[ 'action' ];
			$warning = $res->warnings->$act ?? $res->warnings->main;
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
