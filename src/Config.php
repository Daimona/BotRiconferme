<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\ConfigException;

/**
 * Singleton class holding user-defined config
 */
class Config {
	/** @var self */
	private static $instance;
	/** @var string[] */
	private $opts = [];

	/**
	 * Use self::init() and self::getInstance()
	 */
	private function __construct() {
	}

	/**
	 * Initialize a new self instance with CLI params set and retrieve on-wiki config.
	 *
	 * @param string[] $confValues
	 * @throws ConfigException
	 */
	public static function init( array $confValues ): void {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self();

		foreach ( $confValues as $key => $val ) {
			$inst->set( $key, $val );
		}
		self::$instance = $inst;
	}

	/**
	 * Set a config value.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	protected function set( string $key, $value ): void {
		$this->opts[ $key ] = $value;
	}

	/**
	 * Generic instance getter
	 *
	 * @return self
	 * @throws ConfigException
	 */
	public static function getInstance(): self {
		if ( !self::$instance ) {
			throw new ConfigException( 'Config not yet initialized' );
		}
		return self::$instance;
	}

	/**
	 * Get the requested option, or fail if it doesn't exist
	 *
	 * @param string $opt
	 * @return mixed
	 * @throws ConfigException
	 */
	public function get( string $opt ) {
		if ( !isset( $this->opts[ $opt ] ) ) {
			throw new ConfigException( "Config option '$opt' not set." );
		}
		return $this->opts[ $opt ];
	}
}
