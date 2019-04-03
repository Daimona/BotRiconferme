<?php

namespace BotRiconferme;

use BotRiconferme\Exception\LoginException;
use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\MissingPageException;

class WikiController {
	/** @var Logger */
	private $logger;

	/** @var string[] */
	private $tokens;

	/** @var bool */
	private static $loggedIn = false;

	public function __construct() {
		$this->logger = new Logger;
	}
	/**
	 * @throws LoginException
	 */
	public function login() {
		if ( self::$loggedIn ) {
			$this->logger->debug( 'Already logged in' );
			return;
		}

		$this->logger->debug( 'Logging in' );

		$params = [
			'action' => 'login',
			'lgname' => Config::getInstance()->get( 'username' ),
			'lgpassword' => Config::getInstance()->get( 'password' ),
			'lgtoken' => $this->getToken( 'login' )
		];

		try {
			$req = new Request( $params, true );
			$res = $req->execute()[0];
		} catch ( APIRequestException $e ) {
			throw new LoginException( $e->getMessage() );
		}

		if ( !isset( $res->login->result ) || $res->login->result !== 'Success' ) {
			throw new LoginException( 'Unknown error' );
		}

		self::$loggedIn = true;
		// Clear tokens cache
		$this->tokens = [];
		$this->logger->debug( 'Login succeeded' );
	}

	/**
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

			$req = new Request( $params );
			$res = $req->execute()[0];

			$this->tokens[ $type ] = $res->query->tokens->{ "{$type}token" };
		}

		return $this->tokens[ $type ];
	}

	/**
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

		$req = new Request( $params );
		$data = $req->execute()[0];
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
	 */
	public function editPage( array $params ) {
		$this->login();

		$params = [
			'action' => 'edit',
			'token' => $this->getToken( 'csrf' ),
			'bot' => Config::getInstance()->get( 'bot-edits' )
		] + $params;

		$req = new Request( $params, true );
		$req->execute();
	}
}
