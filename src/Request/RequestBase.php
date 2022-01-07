<?php declare( strict_types=1 );

namespace BotRiconferme\Request;

use BadMethodCallException;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\BlockedException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\PermissionDeniedException;
use BotRiconferme\Exception\ProtectedPageException;
use BotRiconferme\Exception\TimeoutException;
use Generator;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Core wrapper for an API request. Current implementations use either cURL or file_get_contents
 */
abstract class RequestBase {
	protected const USER_AGENT = 'Daimona - BotRiconferme ' . BOT_VERSION .
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
	/** @var string[] */
	protected static $cookiesToSet;
	/**
	 * @var array
	 * @phan-var array<int|string|bool>
	 */
	protected $params;
	/** @var string */
	protected $method = self::METHOD_GET;
	/** @var string[] */
	protected $newCookies = [];

	/** @var LoggerInterface */
	protected $logger;

	/**
	 * @private Use RequestFactory
	 *
	 * @param LoggerInterface $logger
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @param string $domain
	 */
	public function __construct( LoggerInterface $logger, array $params, string $domain ) {
		$this->logger = $logger;
		$this->params = [ 'format' => 'json' ] + $params;
		$this->url = $domain;
	}

	/**
	 * Set the method to POST
	 *
	 * @return self For chaining
	 */
	public function setPost(): self {
		$this->method = self::METHOD_POST;
		return $this;
	}

	/**
	 * Execute a query request
	 * @return Generator
	 */
	public function executeAsQuery(): Generator {
		if ( ( $this->params['action'] ?? false ) !== 'query' ) {
			throw new BadMethodCallException( 'Not an ApiQuery!' );
		}
		// TODO Is this always correct?
		$key = $this->params['list'] ?? 'pages';
		$curParams = $this->params;
		$lim = $this->parseLimit();
		do {
			$res = $this->makeRequestInternal( $curParams );
			$this->handleErrorAndWarnings( $res );
			yield from $key === 'pages' ? get_object_vars( $res->query->pages ) : $res->query->$key;

			// Assume that we have finished
			$finished = true;
			if ( isset( $res->continue ) ) {
				// This may indicate that we're not done...
				$curParams = get_object_vars( $res->continue ) + $curParams;
				$finished = false;
			}
			if ( $lim !== -1 ) {
				$count = $this->countQueryResults( $res, $key );
				if ( $count !== null && $count >= $lim ) {
					// Unless we're able to use a limit, and that limit was passed.
					$finished = true;
				}
			}
		} while ( !$finished );
	}

	/**
	 * Execute a request that doesn't need any continuation.
	 * @return stdClass
	 */
	public function executeSingle(): stdClass {
		$curParams = $this->params;
		$res = $this->makeRequestInternal( $curParams );
		$this->handleErrorAndWarnings( $res );
		return $res;
	}

	/**
	 * @return int
	 */
	private function parseLimit(): int {
		foreach ( $this->params as $name => $val ) {
			if ( substr( $name, -strlen( 'limit' ) ) === 'limit' ) {
				return $val === 'max' ? -1 : (int)$val;
			}
		}
		// Assume no limit
		return -1;
	}

	/**
	 * Try to count the amount of entries in a result.
	 *
	 * @param stdClass $res
	 * @param string $resKey
	 * @return int|null
	 */
	private function countQueryResults( stdClass $res, string $resKey ): ?int {
		if ( !isset( $res->query->$resKey ) ) {
			return null;
		}
		if ( $resKey === 'pages' ) {
			if ( count( get_object_vars( $res->query->pages ) ) !== 1 ) {
				return null;
			}
			$pages = $res->query->pages;
			$firstPage = reset( $pages );
			// TODO Avoid special-casing this.
			if ( !isset( $firstPage->revisions ) ) {
				return null;
			}
			$actualList = $firstPage->revisions;
		} else {
			$actualList = $res->query->$resKey;
		}
		return count( $actualList );
	}

	/**
	 * Process parameters and call the actual request method
	 *
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @return stdClass
	 */
	private function makeRequestInternal( array $params ): stdClass {
		if ( $this->method === self::METHOD_POST ) {
			$params['maxlag'] = self::MAXLAG;
		}
		if ( !isset( $params['assert'] ) ) {
			$params['assert'] = 'user';
		}
		$query = http_build_query( $params );

		try {
			$body = $this->reallyMakeRequest( $query );
		} catch ( TimeoutException $_ ) {
			$this->logger->warning( 'Retrying request after timeout' );
			$body = $this->reallyMakeRequest( $query );
		}

		$this->setCookies( $this->newCookies );
		return json_decode( $body );
	}

	/**
	 * Actual method which will make the request
	 *
	 * @param string $params
	 * @return string
	 */
	abstract protected function reallyMakeRequest( string $params ): string;

	/**
	 * After a request, set cookies for the next ones
	 *
	 * @param string[] $cookies
	 */
	protected function setCookies( array $cookies ): void {
		foreach ( $cookies as $cookie ) {
			/** @var string[] $bits */
			$bits = explode( ';', $cookie );
			[ $name, $value ] = explode( '=', $bits[0] );
			self::$cookiesToSet[ $name ] = $value;
		}
	}

	/**
	 * Get a specific exception class depending on the error code
	 *
	 * @param stdClass $res
	 * @return APIRequestException
	 */
	private function getException( stdClass $res ): APIRequestException {
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
			case 'blocked':
				$ex = new BlockedException( $res->error->info );
				break;
			default:
				$ex = new APIRequestException( $res->error->code . ' - ' . $res->error->info );
		}
		return $ex;
	}

	/**
	 * Handle known warning and errors from an API request
	 *
	 * @param stdClass $res
	 * @throws APIRequestException
	 */
	protected function handleErrorAndWarnings( stdClass $res ): void {
		if ( isset( $res->error ) ) {
			throw $this->getException( $res );
		}
		if ( isset( $res->warnings ) ) {
			$act = $this->params[ 'action' ];
			$warning = $res->warnings->$act ?? $res->warnings->main;
			throw new APIRequestException( reset( $warning ) );
		}
	}

	/**
	 * Get the headers to use for a new request
	 *
	 * @return string[]
	 */
	protected function getHeaders(): array {
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
	 * @param string[] $headers
	 * @return string
	 */
	protected function buildHeadersString( array $headers ): string {
		$ret = '';
		foreach ( $headers as $header ) {
			$ret .= "$header\r\n";
		}
		return $ret;
	}

	/**
	 * @param string $actualParams
	 * @return string
	 */
	protected function getDebugURL( string $actualParams ): string {
		return strpos( $this->url, 'login' ) !== false
			? '[Login request]'
			: "{$this->url}?$actualParams";
	}
}
