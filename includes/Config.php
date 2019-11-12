<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Wiki\Wiki;

/**
 * Singleton class holding user-defined config
 */
class Config {
	public const REQUIRED_OPTS = [
		'username',
		'list-title',
		'config-title',
		'msg-title',
		'password'
	];

	/** @var self */
	private static $instance;
	/** @var array */
	private $opts = [];
	/** @var array|null Lazy-loaded to avoid unnecessary requests */
	private $messages;
	/** @var Wiki */
	private $wiki;

	/**
	 * Use self::init() and self::getInstance()
	 *
	 * @param Wiki $wiki
	 */
	private function __construct( Wiki $wiki ) {
		$this->wiki = $wiki;
	}

	/**
	 * Initialize a new self instance with CLI params set and retrieve on-wiki config.
	 *
	 * @param array $defaults
	 * @param Wiki $wiki
	 * @throws ConfigException
	 */
	public static function init( array $defaults, Wiki $wiki ) {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self( $wiki );
		$inst->set( 'list-title', $defaults['list-title'] );
		$inst->set( 'msg-title', $defaults['msg-title'] );
		$inst->set( 'username', $defaults['username'] );
		$inst->set( 'password', $defaults['password'] );

		// On-wiki values
		try {
			$conf = $inst->wiki->getPageContent( $defaults[ 'config-title' ] );
		} catch ( MissingPageException $_ ) {
			throw new ConfigException( 'Please create a config page.' );
		}

		foreach ( json_decode( $conf, true ) as $key => $val ) {
			$inst->set( $key, $val );
		}
		self::$instance = $inst;
	}

	/**
	 * @param string $key
	 * @return string
	 * @throws ConfigException
	 */
	public function getWikiMessage( string $key ) : string {
		if ( $this->messages === null ) {
			try {
				$cont = $this->wiki->getPageContent( $this->opts[ 'msg-title' ] );
				$this->messages = json_decode( $cont, true );
			} catch ( MissingPageException $_ ) {
				throw new ConfigException( 'Please create a messages page.' );
			}
		}
		if ( !isset( $this->messages[ $key ] ) ) {
			throw new ConfigException( "Message '$key' does not exist." );
		}
		return $this->messages[$key];
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
