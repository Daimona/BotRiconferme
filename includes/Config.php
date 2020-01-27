<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\ConfigException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Wiki\Wiki;

/**
 * Singleton class holding user-defined config
 */
class Config {
	/** @var self */
	private static $instance;
	/** @var array */
	private $opts = [];
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
	 * @param string $configTitle
	 * @param Wiki $wiki
	 * @throws ConfigException
	 */
	public static function init( string $configTitle, Wiki $wiki ) : void {
		if ( self::$instance ) {
			throw new ConfigException( 'Config was already initialized' );
		}

		$inst = new self( $wiki );

		// On-wiki values
		try {
			$conf = $inst->wiki->getPageContent( $configTitle );
		} catch ( MissingPageException $_ ) {
			throw new ConfigException( 'Please create a config page.' );
		}

		foreach ( json_decode( $conf, true ) as $key => $val ) {
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
	protected function set( string $key, $value ) : void {
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
