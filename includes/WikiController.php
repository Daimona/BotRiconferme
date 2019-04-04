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
	 * @return string
	 * @throws MissingPageException
	 */
	public function getPageContent( string $title ) : string {
		$this->logger->debug( "Retrieving page $title" );
		$params = [
			'action' => 'query',
			'titles' => $title,
			'prop' => 'revisions',
			'rvslots' => 'main',
			'rvprop' => 'content',
			'rvlimit' => 1
		];

		$req = RequestBase::newFromParams( $params );
		$req->setResultLimit( 1 );
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
}
