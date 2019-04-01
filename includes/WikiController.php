<?php

namespace BotRiconferme;

class WikiController extends ContextSource {
	/**
	 * @throws LoginException
	 */
	public function login() {
		$this->getLogger()->debug( 'Logging in' );

		$params = [
			'action' => 'login',
			'lgname' => $this->getConfig()->get( 'username' ),
			'lgpassword' => $this->getConfig()->get( 'password' ),
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

		$this->getLogger()->debug( 'Login succeeded' );
	}

	/**
	 * @param string $type
	 * @return string
	 */
	public function getToken( string $type ) : string {
		static $tokens = [];

		if ( !isset( $tokens[ $type ] ) ) {
			$params = [
				'action' => 'query',
				'meta'   => 'tokens',
				'type'   => $type
			];

			$req = new Request( $params );
			$res = $req->execute()[0];

			$tokens[ $type ] = $res->query->tokens->{ "{$type}token" };
		}

		return $tokens[ $type ];
	}

	/**
	 * @param string $title
	 * @return string
	 * @throws MissingPageException
	 */
	public function getPageContent( string $title ) : string {
		$this->getLogger()->debug( "Retrieving page $title" );
		$params = [
			'action' => 'query',
			'titles' => $title,
			'prop' => 'revisions',
			'rvslots' => 'main',
			'rvprop' => 'content'
		];

		$req = new Request( $params );
		$data = $req->execute()[0];
		$page = reset( $data->query->pages );
		if ( isset( $page->missing ) ) {
			throw new MissingPageException( $title );
		}

		return $page->revisions[0]->slots->main->{ '*' };
	}
}
