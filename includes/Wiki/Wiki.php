<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Exception\APIRequestException;
use BotRiconferme\Exception\CannotLoginException;
use BotRiconferme\Exception\EditException;
use BotRiconferme\Exception\LoginException;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\Exception\MissingSectionException;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Request\RequestFactory;
use Psr\Log\LoggerInterface;

/**
 * Class for wiki interaction, contains some requests shorthands
 */
class Wiki {
	/** @var bool */
	private static $loggedIn = false;
	/** @var LoggerInterface */
	private $logger;
	/** @var string[] */
	private $tokens;
	/** @var LoginInfo|null */
	private $loginInfo;
	/** @var bool Whether our edits are bot edits */
	private $botEdits;
	/** @var RequestFactory */
	private $requestFactory;
	/** @var string */
	private $localUserIdentifier = '';

	/**
	 * @param LoginInfo $li
	 * @param LoggerInterface $logger
	 * @param RequestFactory $requestFactory
	 */
	public function __construct(
		LoginInfo $li,
		LoggerInterface $logger,
		RequestFactory $requestFactory
	) {
		$this->loginInfo = $li;
		$this->logger = $logger;
		$this->requestFactory = $requestFactory;
	}

	/**
	 * @return LoginInfo
	 */
	public function getLoginInfo() : LoginInfo {
		return $this->loginInfo;
	}

	/**
	 * @param bool $bot
	 */
	public function setEditsAsBot( bool $bot ) : void {
		// FIXME same as setLoginInfo
		$this->botEdits = $bot;
	}

	/**
	 * @return bool
	 */
	public function getEditsAsBot() : bool {
		return $this->botEdits;
	}

	/**
	 * @return RequestFactory
	 */
	public function getRequestFactory() : RequestFactory {
		return $this->requestFactory;
	}

	/**
	 * @param string $ident
	 */
	public function setLocalUserIdentifier( string $ident ) : void {
		$this->localUserIdentifier = $ident;
	}

	/**
	 * @return string
	 */
	public function getLocalUserIdentifier() : string {
		return $this->localUserIdentifier;
	}

	/**
	 * Gets the content of a wiki page
	 *
	 * @param string $title
	 * @param int|null $section
	 * @return string
	 * @throws MissingPageException
	 * @throws MissingSectionException
	 */
	public function getPageContent( string $title, int $section = null ) : string {
		$msg = "Retrieving content of $title" . ( $section !== null ? ", section $section" : '' );
		$this->logger->debug( $msg );
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

		$req = $this->buildRequest( $params );
		$data = $req->execute();
		$page = reset( $data->query->pages );
		if ( isset( $page->missing ) ) {
			throw new MissingPageException( $title );
		}

		$mainSlot = $page->revisions[0]->slots->main;

		if ( $section !== null && isset( $mainSlot->nosuchsection ) ) {
			throw new MissingSectionException( $title, $section );
		}
		return $mainSlot->{ '*' };
	}

	/**
	 * Basically a wrapper for action=edit
	 *
	 * @param array $params
	 * @throws EditException
	 */
	public function editPage( array $params ) : void {
		$this->login();

		$params = [
			'action' => 'edit',
			'token' => $this->getToken( 'csrf' ),
		] + $params;

		if ( $this->getEditsAsBot() ) {
			$params['bot'] = 1;
		}

		$res = $this->buildRequest( $params )->setPost()->execute();

		$editData = $res->edit;
		if ( $editData->result !== 'Success' ) {
			if ( isset( $editData->captcha ) ) {
				throw new EditException( 'Got captcha!' );
			}
			throw new EditException( $editData->info ?? reset( $editData ) );
		}
	}

	/**
	 * Login wrapper. Checks if we're already logged in and clears tokens cache
	 * @throws LoginException
	 */
	public function login() : void {
		if ( $this->loginInfo === null ) {
			throw new CannotLoginException( 'Missing login data' );
		}
		if ( self::$loggedIn ) {
			return;
		}

		// Yes, this is an easter egg.
		$this->logger->info( 'Logging in. Username: BotRiconferme, password: correctHorseBatteryStaple' );

		$params = [
			'action' => 'login',
			'lgname' => $this->getLoginInfo()->getUsername(),
			'lgpassword' => $this->getLoginInfo()->getPassword(),
			'lgtoken' => $this->getToken( 'login' )
		];

		try {
			$res = $this->buildRequest( $params )->setPost()->execute();
		} catch ( APIRequestException $e ) {
			throw new LoginException( $e->getMessage() );
		}

		if ( !isset( $res->login->result ) || $res->login->result !== 'Success' ) {
			throw new LoginException( $res->login->reason ?? 'Unknown error' );
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

			$req = $this->buildRequest( $params );
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

		$res = $this->buildRequest( $params )->execute();
		$data = $res->query->pages;
		return strtotime( reset( $data )->revisions[0]->timestamp );
	}

	/**
	 * Sysop-level inifinite protection for a given page
	 *
	 * @param string $title
	 * @param string $reason
	 */
	public function protectPage( string $title, string $reason ) : void {
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

		$this->buildRequest( $params )->setPost()->execute();
	}

	/**
	 * Shorthand
	 * @param array $params
	 * @return RequestBase
	 */
	private function buildRequest( array $params ) : RequestBase {
		return $this->requestFactory->newFromParams( $params );
	}
}
