<?php

class Config {
	/** @var array */
	private $opts = [];

	/**
	 * Use self::getInstance()
	 */
	private function __construct() {
	}

	/**
	 * Instance getter
	 *
	 * @return self
	 */
	public static function getInstance() : self {
		static $inst = null;
		if ( !$inst ) {
			$inst = new self;
		}
		return $inst;
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
