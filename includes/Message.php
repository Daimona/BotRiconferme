<?php declare( strict_types=1 );

namespace BotRiconferme;

/**
 *
 */
class Message {
	/** @var string */
	private $key;
	/** @var string */
	private $value;

	/**
	 * @param string $key
	 */
	public function __construct( string $key ) {
		$this->key = $key;
		$this->value = Config::getInstance()->getWikiMessage( $key );
	}

	/**
	 * @param array $args
	 * @return self
	 */
	public function params( array $args ) : self {
		$this->value = strtr( $this->value, $args );
		return $this;
	}

	/**
	 * @return string
	 */
	public function text() : string {
		$this->parsePlurals();
		return $this->value;
	}

	/**
	 * Replace {{$plur|<amount>|sing|plur}}
	 */
	protected function parsePlurals() {
		$this->value = preg_replace_callback(
			'!\{\{$plur|(?P<amount>\d+)|(?P<sing>[^}|]+)|(?P<plur>[^|}]+)}}!',
			function ( $matches ) {
				return intval( $matches['amount'] ) > 1 ? trim( $matches['plur'] ) : trim( $matches['sing'] );
			},
			$this->value
		);
	}
}
