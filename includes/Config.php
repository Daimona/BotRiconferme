<?php

namespace BotRiconferme;

use BotRiconferme\Exceptions\ConfigException;
use BotRiconferme\Exceptions\MissingPageException;

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
	 * @param array $defaults
	 */
	public static function init( array $defaults ) {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self;
		$inst->set( 'url', $defaults['url'] );
		$inst->set( 'list-title', $defaults['list-title'] );
		$inst->set( 'username', $defaults['username'] );
		$inst->set( 'password', $defaults['password'] );
		self::$instance = $inst;

		// On-wiki values
		try {
			$conf = ( new WikiController )->getPageContent( $defaults[ 'config-title' ] );
		} catch ( MissingPageException $e ) {
			throw new ConfigException( 'Please create a config page.' );
		}

		foreach ( json_decode( $conf, true ) as $key => $val ) {
			self::$instance->set( $key, $val );
		}
	}

	/**
	 * Generic instance getter
	 *
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
	 * @return mixed
	 */
	public function get( string $opt ) {
		if ( !isset( $this->opts[ $opt ] ) ) {
			throw new ConfigException( "Config option '$opt' not set." );
		}
		return $this->opts[ $opt ];
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set( string $key, $value ) {
		$this->opts[ $key ] = $value;
	}
}
