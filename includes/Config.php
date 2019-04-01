<?php

namespace BotRiconferme;

class Config {
	/** @var array */
	private $opts = [];

	/** @var self */
	private static $instance;

	/**
	 * Use self::getInstance()
	 */
	private function __construct() {
	}

	/**
	 * Specific instance getter
	 *
	 * @param string $name
	 */
	public static function init( array $defaults ) {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self;
		$inst->set( 'url', $defaults['url'] );
		$inst->set( 'list-title', $defaults['list-title'] );
		self::$instance = $inst;

		// On-wiki values
		$conf = ( new WikiController )->getPageContent( $defaults[ 'config-title' ] );

		foreach ( json_decode( $conf ) as $key => $val ) {
			self::$instance->set( $key, $val );
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
			throw new ConfigException( 'Config not yet initialized' );
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
