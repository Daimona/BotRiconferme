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
 * which will look for a $PWFILE file in the current directory containing only the plain password
 *
 * --task=update-list
 * OR
 * --subtask=user-notice
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

		'error-title:',

		'task:',
		'subtask:'
	];

	public const REQUIRED_OPTS = [
		'username',
		'list-title',
		'config-title',
		'msg-title',
	];

	/** @todo Make it customizable? */
	public const PASSWORD_FILE = __DIR__ . '/../password.txt';

	/** @var array */
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
		$opts = getopt( self::SHORT_OPTS, self::LONG_OPTS );
		$this->checkRequiredOpts( $opts );
		$this->checkConflictingOpts( $opts );
		$this->canonicalize( $opts );
		$this->opts = $opts;
	}

	/**
	 * @param array $opts
	 */
	private function checkRequiredOpts( array $opts ) : void {
		$missingOpts = array_diff( self::REQUIRED_OPTS, array_keys( $opts ) );
		if ( $missingOpts ) {
			exit( 'Required options missing: ' . implode( ', ', $missingOpts ) );
		}

		$hasPw = array_key_exists( 'password', $opts );
		$hasPwFile = array_key_exists( 'use-password-file', $opts );
		if ( !$hasPw && !$hasPwFile ) {
			exit( 'Please provide a password or use a password file' );
		}
	}

	/**
	 * @param array $opts
	 */
	private function checkConflictingOpts( array $opts ) : void {
		$hasPw = array_key_exists( 'password', $opts );
		$hasPwFile = array_key_exists( 'use-password-file', $opts );
		if ( $hasPw && $hasPwFile ) {
			exit( 'Can only use one of "password" and "use-password-file"' );
		} elseif ( $hasPwFile && !file_exists( self::PASSWORD_FILE ) ) {
			exit( 'Please create the password file (' . self::PASSWORD_FILE . ')' );
		}

		if ( count( array_intersect_key( $opts, [ 'task' => 1, 'subtask' => 1 ] ) ) === 2 ) {
			exit( 'Cannot specify both task and subtask.' );
		}
	}

	/**
	 * @param array &$opts
	 */
	private function canonicalize( array &$opts ) : void {
		if ( array_key_exists( 'use-password-file', $opts ) ) {
			$pw = trim( file_get_contents( self::PASSWORD_FILE ) );
			$opts['password'] = $pw;
			unset( $opts['use-password-file'] );
		}
	}

	/**
	 * @param string $opt
	 * @param mixed|null $default
	 * @return mixed
	 */
	public function getOpt( string $opt, $default = null ) {
		return $this->opts[$opt] ?? $default;
	}

	/**
	 * @return array Either [ 'task' => taskname ] or [ 'subtask' => subtaskname ]
	 */
	public function getTaskOpt() : array {
		return array_intersect_key(
			$this->opts,
			[ 'task' => true, 'subtask' => true ]
		);
	}

	/**
	 * @return string|null
	 */
	public function getURL() : ?string {
		return $this->getOpt( 'force-url' );
	}
}
