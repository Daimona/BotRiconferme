<?php

class Request {
	const USER_AGENT = 'Daimona - BotRiconferme 1.0 (https://github.com/Daimona/BotRiconferme)';

	const HEADERS = [
		'Content-Type: application/x-www-form-urlencoded',
		'User-Agent: ' . self::USER_AGENT
	];

	/** @var array */
	private $params;

	/** @var string */
	private $method;

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
				throw new APIRequestException( $res->error->info );
			} elseif ( isset( $res->warnings ) ) {
				$warnings = reset( $res->warnings );
				throw new APIRequestException( reset( $warnings ) );
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
	 * Perform an API request, either via cURL (if available) or file_get_contents
	 *
	 * @param array $params
	 * @return mixed
	 */
	private function reallyMakeRequest( array $params ) {
		$url = Config::getInstance()->get( 'url' );

		if ( extension_loaded( 'curl' ) ) {
			$curl = curl_init();
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_HEADER, false );
			curl_setopt( $curl, CURLOPT_HTTPHEADER, self::HEADERS );

			if ( $this->method === 'POST' ) {
				curl_setopt( $curl, CURLOPT_URL, $url );
				curl_setopt( $curl, CURLOPT_POST, true );
				curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $params ) );
			} else {
				curl_setopt( $curl, CURLOPT_URL, "$url?" . http_build_query( $params ) );
			}

			$result = curl_exec( $curl );
			if ( $result === false ) {
				throw new APIRequestException( curl_error( $curl ) );
			}
			curl_close( $curl );
			return json_decode( $result );
		} else {
			$query = "$url?" . http_build_query( $params );
			$context = [
				'http' => [
					'method' => $this->method,
					'header' => $this->buildHeaders()
				]
			];
			$context = stream_context_create( $context );
			return json_decode( file_get_contents( $query, false, $context ) );
		}
	}

	/**
	 * @return string
	 */
	private function buildHeaders() : string {
		$ret = '';
		foreach ( self::HEADERS as $header ) {
			$ret .= "$header\r\n";
		}
		return $ret;
	}
}
