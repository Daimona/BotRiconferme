<?php declare( strict_types=1 );

namespace BotRiconferme;

/**
 * CLI helper
 *
 * Example options:
 *
 * 'username' => 'BotRiconferme'
 * 'list-title' => 'Utente:BotRiconferme/List.json',
 * 'config-title' => 'Utente:BotRiconferme/Config.json',
 * 'msg-title' => 'Utente:BotRiconferme/Messages.json"
 *
 * --password=(BotPassword)
 * OR
 * --use-password-file
 * which will look for a PASSWORD_FILE file in the current directory containing only the plain password
 *
 * --private-password=(BotPassword)
 * OR
 * --use-private-password-file
 * which will look for a PRIVATE_PASSWORD_FILE file in the current directory containing only the plain password
 *
 * --tasks=update-list
 * OR
 * --subtasks=user-notice
 * (or comma-separated list, for both)
 */
class CLI {
	public const SHORT_OPTS = '';

	public const LONG_OPTS = [
		'username:',
		'list-title:',
		'config-title:',
		'msg-title:',

		'force-url:',

		'password:',
		'use-password-file',
		'private-password:',
		'use-private-password-file',

		'error-title:',

		'tasks:',
		'subtasks:'
	];

	public const REQUIRED_OPTS = [
		'username',
		'list-title',
		'config-title',
		'msg-title',
	];

	/** @todo Make it customizable? */
	public const PASSWORD_FILE = __DIR__ . '/../password.txt';
	public const PRIVATE_PASSWORD_FILE = __DIR__ . '/../private-password.txt';

	/** @var string[] */
	private $opts;

	/**
	 * @return bool
	 */
	public static function isCLI() : bool {
		return PHP_SAPI === 'cli';
	}

	/**
	 * Populate options and check for required ones
	 */
	public function __construct() {
		/** @var string[] $opts */
		$opts = getopt( self::SHORT_OPTS, self::LONG_OPTS );
		$this->checkRequiredOpts( $opts );
		$this->checkConflictingOpts( $opts );
		$this->canonicalize( $opts );
		$this->opts = $opts;
	}

	/**
	 * @param string[] $opts
	 */
	private function checkRequiredOpts( array $opts ) : void {
		$missingOpts = array_diff( self::REQUIRED_OPTS, array_keys( $opts ) );
		if ( $missingOpts ) {
			$this->fatal( 'Required options missing: ' . implode( ', ', $missingOpts ) );
		}

		$hasPw = array_key_exists( 'password', $opts );
		$hasPwFile = array_key_exists( 'use-password-file', $opts );
		if ( !$hasPw && !$hasPwFile ) {
			$this->fatal( 'Please provide a password or use a password file' );
		}

		$hasPrivatePw = array_key_exists( 'private-password', $opts );
		$hasPrivatePwFile = array_key_exists( 'use-private-password-file', $opts );
		if ( !$hasPrivatePw && !$hasPrivatePwFile ) {
			$this->fatal( 'Please provide a private password or use a private-password file' );
		}
	}

	/**
	 * @param string[] $opts
	 */
	private function checkConflictingOpts( array $opts ) : void {
		$this->checkNotBothSet( $opts, 'password', 'use-password-file' );
		if ( array_key_exists( 'use-password-file', $opts ) && !file_exists( self::PASSWORD_FILE ) ) {
			$this->fatal( 'Please create the password file (' . self::PASSWORD_FILE . ')' );
		}

		$this->checkNotBothSet( $opts, 'private-password', 'use-private-password-file' );
		if ( array_key_exists( 'use-private-password-file', $opts ) && !file_exists( self::PRIVATE_PASSWORD_FILE ) ) {
			$this->fatal( 'Please create the private-password file (' . self::PRIVATE_PASSWORD_FILE . ')' );
		}

		if ( count( array_intersect_key( $opts, [ 'tasks' => 1, 'subtasks' => 1 ] ) ) === 2 ) {
			$this->fatal( 'Cannot specify both tasks and subtasks.' );
		}
	}

	/**
	 * @param string[] $opts
	 * @param string $first
	 * @param string $second
	 */
	private function checkNotBothSet( array $opts, string $first, string $second ) : void {
		if ( array_key_exists( $first, $opts ) && array_key_exists( $second, $opts ) ) {
			$this->fatal( "Can only use one of '$first' and '$second'" );
		}
	}

	/**
	 * @param string[] &$opts
	 */
	private function canonicalize( array &$opts ) : void {
		if ( array_key_exists( 'use-password-file', $opts ) ) {
			$pw = trim( file_get_contents( self::PASSWORD_FILE ) );
			$opts['password'] = $pw;
			unset( $opts['use-password-file'] );
		}
		if ( array_key_exists( 'use-private-password-file', $opts ) ) {
			$pw = trim( file_get_contents( self::PRIVATE_PASSWORD_FILE ) );
			$opts['private-password'] = $pw;
			unset( $opts['use-private-password-file'] );
		}
	}

	/**
	 * @param string $msg
	 */
	private function fatal( string $msg ) : void {
		exit( $msg . "\n" );
	}

	/**
	 * @param string $opt
	 * @param mixed|null $default
	 * @return string
	 */
	public function getOpt( string $opt, $default = null ) : string {
		return $this->opts[$opt] ?? $default;
	}

	/**
	 * @return string[] Either [ 'tasks' => name1,... ] or [ 'subtasks' => name1,... ]
	 */
	public function getTaskOpt() : array {
		return array_intersect_key(
			$this->opts,
			[ 'tasks' => true, 'subtasks' => true ]
		);
	}

	/**
	 * @return string|null
	 */
	public function getURL() : ?string {
		return $this->getOpt( 'force-url' );
	}
}
