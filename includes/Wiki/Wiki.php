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
	private $loggedIn = false;
	/** @var LoggerInterface */
	private $logger;
	/** @var string[] */
	private $tokens;
	/** @var LoginInfo */
	private $loginInfo;
	/** @var RequestFactory */
	private $requestFactory;
	/** @var string */
	private $localUserIdentifier = '';
	/** @var string Used for logging */
	private $pagePrefix = '';

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
	 * @return RequestFactory
	 */
	public function getRequestFactory() : RequestFactory {
		return $this->requestFactory;
	}

	/**
	 * @param string $prefix
	 */
	public function setPagePrefix( string $prefix ) : void {
		$this->pagePrefix = $prefix;
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
	 * @param string $title
	 * @param int|null $section
	 */
	private function logRead( string $title, int $section = null ) : void {
		$fullTitle = $this->pagePrefix . $title;
		$msg = "Retrieving content of $fullTitle" . ( $section !== null ? ", section $section" : '' );
		$this->logger->info( $msg );
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
		$this->logRead( $title, $section );
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
		$page = $req->executeAsQuery()->current();
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
	 * @phan-param array<int|string|bool> $params
	 * @throws EditException
	 */
	public function editPage( array $params ) : void {
		$this->login();

		$params = [
			'action' => 'edit',
			'token' => $this->getToken( 'csrf' ),
		] + $params;

		if ( BOT_EDITS === true ) {
			$params['bot'] = 1;
		}

		$res = $this->buildRequest( $params )->setPost()->executeSingle();

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
		if ( $this->loggedIn ) {
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
			$res = $this->buildRequest( $params )->setPost()->executeSingle();
		} catch ( APIRequestException $e ) {
			throw new LoginException( $e->getMessage() );
		}

		if ( !isset( $res->login->result ) || $res->login->result !== 'Success' ) {
			throw new LoginException( $res->login->reason ?? 'Unknown error' );
		}

		$this->loggedIn = true;
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
			$res = $this->buildRequest( $params )->executeSingle();
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

		$page = $this->buildRequest( $params )->executeAsQuery()->current();
		return strtotime( $page->revisions[0]->timestamp );
	}

	/**
	 * Sysop-level inifinite protection for a given page
	 *
	 * @param string $title
	 * @param string $reason
	 */
	public function protectPage( string $title, string $reason ) : void {
		$fullTitle = $this->pagePrefix . $title;
		$this->logger->info( "Protecting page $fullTitle" );
		$this->login();

		$params = [
			'action' => 'protect',
			'title' => $title,
			'protections' => 'edit=sysop|move=sysop',
			'expiry' => 'infinite',
			'reason' => $reason,
			'token' => $this->getToken( 'csrf' )
		];

		$this->buildRequest( $params )->setPost()->executeSingle();
	}

	/**
	 * Block a user, infinite expiry
	 *
	 * @param string $username
	 * @param string $reason
	 */
	public function blockUser( string $username, string $reason ) : void {
		$this->logger->info( "Blocking user $username" );
		$this->login();

		$params = [
			'action' => 'block',
			// Don't allow talk page edit 'allowusertalk' => 1,
			'autoblock' => 1,
			'nocreate' => 1,
			'expiry' => 'indefinite',
			// No anononly
			'noemail' => 1,
			// No reblock
			'reason' => $reason,
			'user' => $username,
			'token' => $this->getToken( 'csrf' )
		];

		$this->buildRequest( $params )->setPost()->executeSingle();
	}

	/**
	 * Shorthand
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @return RequestBase
	 */
	private function buildRequest( array $params ) : RequestBase {
		return $this->requestFactory->newFromParams( $params );
	}
}
