<?php

namespace BotRiconferme;

class Config {
	/** @var array */
	private $opts = [];

	/** @var self */
	private static $instance;
	
	const ALLOWED_CONFIGS = [
		'itwiki' => ConfigItwiki::class
	];
	
	/**
	 * Use self::getInstanceFor() or self::getInstance()
	 */
	protected function __construct() {
	}

	/**
	 * Specific instance getter
	 *
	 * @param string $name
	 */
	public static function getInstanceFor( string $name ) {
		if ( self::$instance ) {
			throw new ConfigException( 'Can only have a config instance at a time' );
		}
		if ( isset( self::ALLOWED_CONFIGS[ $name ] ) ) {
			$class = self::ALLOWED_CONFIGS[ $name ];
			self::$instance = new $class;
		} else {
			throw new ConfigException( "The requested config '$name' is not valid." );
		}
	}
	
	/**
	 * Generic instance getter
	 *
	 * @param string $name
	 * @return self
	 */
	public static function getInstance() : self {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * @param string $opt
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function get( string $opt, $default = null ) {
		return $this->opts[ $opt ] ?? $default;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set( string $key, $value ) {
		$this->opts[ $key ] = $value;
	}
}
