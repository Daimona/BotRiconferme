<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\ConfigException;

/**
 * Singleton class holding user-defined config
 */
class Config {
	private static ?self $instance = null;
	/** @phan-var array<mixed> */
	private array $opts = [];

	/**
	 * Use self::init() and self::getInstance()
	 */
	private function __construct() {
	}

	/**
	 * Initialize a new self instance with CLI params set and retrieve on-wiki config.
	 *
	 * @param array $confValues
	 * @phan-param array<string,mixed> $confValues
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
	protected function set( string $key, mixed $value ): void {
		$this->opts[ $key ] = $value;
	}

	/**
	 * Generic instance getter
	 *
	 * @return self
	 */
	public static function getInstance(): self {
		return self::$instance ?? throw new ConfigException( 'Config not yet initialized' );
	}

	/** @suppress PhanUnreferencedPublicMethod */
	public static function clearInstance(): void {
		self::$instance = null;
	}

	/**
	 * Get the requested option, or fail if it doesn't exist
	 *
	 * @param string $opt
	 * @return mixed
	 */
	public function get( string $opt ): mixed {
		return $this->opts[ $opt ] ?? throw new ConfigException( "Config option '$opt' not set." );
	}
}
