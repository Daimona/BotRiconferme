<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BotRiconferme\Bot;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\ProtectedPageException;

abstract class RequestBase {
	const USER_AGENT = 'Daimona - BotRiconferme ' . Bot::VERSION .
		' (https://github.com/Daimona/BotRiconferme)';
	const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];
	// In seconds
	const MAXLAG = 5;

	/** @var string  */
	public static $url = 'https://it.wikipedia.org/w/api.php';
	/** @var array */
	protected static $cookiesToSet;
	/** @var array */
	protected $params;
	/** @var string */
	protected $method;
	/** @var string[] */
	protected $newCookies = [];

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
	 */
	public function execute() : array {
		$curParams = $this->params;
		$sets = [];
		do {
			$res = $this->makeRequestInternal( $curParams );

			$this->handleErrorAndWarnings( $res );
			$sets[] = $res;

			$finished = true;
			if ( isset( $res->continue ) ) {
				$curParams = array_merge( $curParams, get_object_vars( $res->continue ) );
				$finished = false;
			}
		} while ( !$finished );

		return $this->mergeSets( $sets );
	}

	/**
	 * Perform an API request, either via cURL (if available) or file_get_contents
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
	 * @return array
	 */
	private function mergeSets( array $sets ) : array {
		$sets = $this->objectToArray( $sets );
		// Use the first set as template
		$ret = array_shift( $sets );
		$act = $this->params['action'];

		foreach ( $sets as $set ) {
			$ret[$act] = array_merge_recursive(
				$this->objectToArray( $ret[$act] ),
				$this->objectToArray( $set[$act] )
			);
		}
		return $ret;
	}

	/**
	 * Taken by MediaWiki's wfObjectToArray https://gerrit.wikimedia.org/g/mediawiki/core/+/
	 *    846d970e6e02ebc0a284f32968e1681201706270/includes/GlobalFunctions.php#254
	 *
	 * @param \stdClass|array $objOrArray
	 * @return array
	 */
	private function objectToArray( $objOrArray ) : array {
		$array = [];
		if ( is_object( $objOrArray ) ) {
			$objOrArray = get_object_vars( $objOrArray );
		}
		foreach ( $objOrArray as $key => $value ) {
			if ( is_object( $value ) || is_array( $value ) ) {
				$value = $this->objectToArray( $value );
			}
			$array[$key] = $value;
		}
		return $array;
	}

	/**
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
