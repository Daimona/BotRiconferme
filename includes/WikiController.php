<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Exception\EditException;
use BotRiconferme\Exception\LoginException;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Request\RequestBase;

/**
 * Class for wiki interaction, contains some requests shorthands
 */
class WikiController {
	/** @var bool */
	private static $loggedIn = false;
	/** @var Logger */
	private $logger;
	/** @var string[] */
	private $tokens;

	public function __construct() {
		$this->logger = new Logger;
	}

	/**
	 * Gets the content of a wiki page
	 *
	 * @param string $title
	 * @param int|null $section
	 * @return string
	 * @throws MissingPageException
	 */
	public function getPageContent( string $title, int $section = null ) : string {
		$this->logger->debug( "Retrieving page $title" );
		$params = [
			'action' => 'query',
			'titles' => $title,
			'prop' => 'revisions',
			'rvslots' => 'main',
			'rvprop' => 'content'
		];

		if ( $section !== null ) {
			$params['rvsection'] = $section;
		}

		$req = RequestBase::newFromParams( $params );
		$data = $req->execute();
		$page = reset( $data->query->pages );
		if ( isset( $page->missing ) ) {
			throw new MissingPageException( $title );
		}

		return $page->revisions[0]->slots->main->{ '*' };
	}

	/**
	 * Basically a wrapper for action=edit
	 *
	 * @param array $params
	 * @throws EditException
	 */
	public function editPage( array $params ) {
		$this->login();

		$params = [
			'action' => 'edit',
			'token' => $this->getToken( 'csrf' ),
			'bot' => Config::getInstance()->get( 'bot-edits' )
		] + $params;

		$res = RequestBase::newFromParams( $params, true )->execute();
		if ( $res->edit->result !== 'Success' ) {
			throw new EditException( $res->edit->info );
		}
	}

	/**
	 * Get a localized version of article + day + time
	 *
	 * @param int $timestamp
	 * @return string
	 * @fixme Not the right place for this
	 */
	public static function getTimeWithArticle( int $timestamp ) : string {
		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$timeString = strftime( '%e %B alle %R', $timestamp );
		// Remove the left space if day has a single digit
		$timeString = ltrim( $timeString );
		$artic = in_array( date( 'j', $timestamp ), [ 8, 11 ] ) ? "l'" : "il ";
		setlocale( LC_TIME, $oldLoc );

		return $artic . $timeString;
	}

	/**
	 * Get a timestamp from a localized time string
	 *
	 * @param string $timeString
	 * @return int
	 * @fixme Not the right place for this
	 * @todo Is there a better way?
	 */
	public static function getTimestampFromLocalTime( string $timeString ) : int {
		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$bits = strptime( $timeString, '%e %m %Y alle %H:%M' );
		$timestamp = mktime(
			$bits['tm_hour'],
			$bits['tm_min'],
			0,
			$bits['tm_mon'] + 1,
			$bits['tm_mday'],
			$bits['tm_year'] + 1900
		);
		setlocale( LC_TIME, $oldLoc );

		return $timestamp;
	}
	/**
	 * Login wrapper. Checks if we're already logged in and clears tokens cache
	 * @throws LoginException
	 */
	public function login() {
		if ( self::$loggedIn ) {
			$this->logger->debug( 'Already logged in' );
			return;
		}

		$this->logger->info( 'Logging in' );

		$params = [
			'action' => 'login',
			'lgname' => Config::getInstance()->get( 'username' ),
			'lgpassword' => Config::getInstance()->get( 'password' ),
			'lgtoken' => $this->getToken( 'login' )
		];

		try {
			$res = RequestBase::newFromParams( $params, true )->execute();
		} catch ( APIRequestException $e ) {
			throw new LoginException( $e->getMessage() );
		}

		if ( !isset( $res->login->result ) || $res->login->result !== 'Success' ) {
			throw new LoginException( 'Unknown error' );
		}

		self::$loggedIn = true;
		// Clear tokens cache
		$this->tokens = [];
		$this->logger->info( 'Login succeeded' );
	}

	/**
	 * Get a token, cached.
	 *
	 * @param string $type
	 * @return string
	 */
	public function getToken( string $type ) : string {
		if ( !isset( $this->tokens[ $type ] ) ) {
			$params = [
				'action' => 'query',
				'meta'   => 'tokens',
				'type'   => $type
			];

			$req = RequestBase::newFromParams( $params );
			$res = $req->execute();

			$this->tokens[ $type ] = $res->query->tokens->{ "{$type}token" };
		}

		return $this->tokens[ $type ];
	}

	/**
	 * Get the timestamp of the creation of the given page
	 *
	 * @param string $title
	 * @return int
	 */
	public function getPageCreationTS( string $title ) : int {
		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $title,
			'rvprop' => 'timestamp',
			'rvslots' => 'main',
			'rvlimit' => 1,
			'rvdir' => 'newer'
		];

		$res = ( RequestBase::newFromParams( $params ) )->execute();
		$data = $res->query->pages;
		return strtotime( reset( $data )->revisions[0]->timestamp );
	}

	/**
	 * Sysop-level inifinite protection for a given page
	 *
	 * @param string $title
	 * @param string $reason
	 */
	public function protectPage( string $title, string $reason ) {
		$this->logger->info( "Protecting page $title" );
		$this->login();

		$params = [
			'action' => 'protect',
			'title' => $title,
			'protections' => 'edit=sysop|move=sysop',
			'expiry' => 'infinite',
			'reason' => $reason,
			'token' => $this->getToken( 'csrf' )
		];

		RequestBase::newFromParams( $params, true )->execute();
	}
}
