<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Wiki\Controller;

/**
 * Singleton class holding user-defined config
 */
class Config {
	/** @var self */
	private static $instance;
	/** @var array */
	private $opts = [];

	/**
	 * Use self::init() and self::getInstance()
	 */
	private function __construct() {
	}

	/**
	 * Initialize a new self instance with CLI params set and retrieve on-wiki config.
	 *
	 * @param array $defaults
	 * @throws ConfigException
	 */
	public static function init( array $defaults ) {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self;
		$inst->set( 'list-title', $defaults['list-title'] );
		$inst->set( 'msg-title', $defaults['msg-title'] );
		$inst->set( 'username', $defaults['username'] );
		$inst->set( 'password', $defaults['password'] );
		self::$instance = $inst;

		// On-wiki values
		try {
			$conf = ( new Controller )->getPageContent( $defaults[ 'config-title' ] );
		} catch ( MissingPageException $e ) {
			throw new ConfigException( 'Please create a config page.' );
		}

		foreach ( json_decode( $conf, true ) as $key => $val ) {
			self::$instance->set( $key, $val );
		}
	}

	/**
	 * @param string $key
	 * @return string
	 * @throws ConfigException
	 */
	public function getWikiMessage( string $key ) : string {
		static $messages = null;
		if ( $messages === null ) {
			try {
				$cont = ( new Controller )->getPageContent( $this->opts[ 'msg-title' ] );
				$messages = json_decode( $cont, true );
			} catch ( MissingPageException $e ) {
				throw new ConfigException( 'Please create a messages page.' );
			}
		}
		if ( !isset( $messages[ $key ] ) ) {
			throw new ConfigException( "Message '$key' does not exist." );
		}
		return $messages[$key];
	}

	/**
	 * Set a config value.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	protected function set( string $key, $value ) {
		$this->opts[ $key ] = $value;
	}

	/**
	 * Generic instance getter
	 *
	 * @return self
	 * @throws ConfigException
	 */
	public static function getInstance() : self {
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
