<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki;

use BotRiconferme\Request\Exception\APIRequestException;
use BotRiconferme\Request\Exception\EditException;
use BotRiconferme\Request\Exception\LoginException;
use BotRiconferme\Request\Exception\MissingPageException;
use BotRiconferme\Request\Exception\MissingSectionException;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Request\RequestFactory;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Class for wiki interaction, contains some requests shorthands
 */
class Wiki {
	private bool $loggedIn = false;
	private LoggerInterface $logger;
	/** @var string[] */
	private array $tokens = [];
	private LoginInfo $loginInfo;
	private RequestFactory $requestFactory;
	private string $localUserIdentifier = '';
	/** Used for logging */
	private string $pagePrefix = '';
	/** @var string[] */
	private array $cookies = [];

	public function __construct(
		LoginInfo $li,
		LoggerInterface $logger,
		RequestFactory $requestFactory
	) {
		$this->loginInfo = $li;
		$this->logger = $logger;
		$this->requestFactory = $requestFactory;
	}

	public function getLoginInfo(): LoginInfo {
		return $this->loginInfo;
	}

	public function getRequestFactory(): RequestFactory {
		return $this->requestFactory;
	}

	public function setPagePrefix( string $prefix ): void {
		$this->pagePrefix = $prefix;
	}

	public function setLocalUserIdentifier( string $ident ): void {
		$this->localUserIdentifier = $ident;
	}

	public function getLocalUserIdentifier(): string {
		return $this->localUserIdentifier;
	}

	private function logRead( string $title, ?int $section = null ): void {
		$fullTitle = $this->pagePrefix . $title;
		$msg = "Retrieving content of $fullTitle" . ( $section !== null ? ", section $section" : '' );
		$this->logger->info( $msg );
	}

	/**
	 * Gets the content of a wiki page
	 *
	 * @param string $title
	 * @return string
	 * @throws MissingPageException
	 */
	public function getPageContent( string $title ): string {
		return $this->queryPageContent( $title, null )->{ '*' };
	}

	/**
	 * Get the content of a specific section of a wiki page
	 *
	 * @param string $title
	 * @param int $section
	 * @return string
	 * @throws MissingPageException
	 * @throws MissingSectionException
	 */
	public function getPageSectionContent( string $title, int $section ): string {
		$mainSlot = $this->queryPageContent( $title, $section );
		if ( isset( $mainSlot->nosuchsection ) ) {
			throw new MissingSectionException( $title, $section );
		}
		return $mainSlot->{ '*' };
	}

	/**
	 * @param string $title
	 * @param int|null $section
	 * @return stdClass Response object for the main slot
	 * @throws MissingPageException
	 */
	private function queryPageContent( string $title, ?int $section ): stdClass {
		$this->logRead( $title, $section );
		$params = [
			'action' => 'query',
			'titles' => $title,
			'prop' => 'revisions',
			'rvslots' => 'main',
			'rvprop' => 'content',
		];

		if ( $section !== null ) {
			$params['rvsection'] = $section;
		}

		$req = $this->buildRequest( $params );
		$page = $req->executeAsQuery()->current();

		if ( isset( $page->missing ) ) {
			throw new MissingPageException( $title );
		}

		return $page->revisions[0]->slots->main;
	}

	/**
	 * Basically a wrapper for action=edit
	 *
	 * @param array $params
	 * @phan-param array<int|string|bool> $params
	 * @throws EditException
	 */
	public function editPage( array $params ): void {
		$this->login();

		$params = [
			'action' => 'edit',
			'token' => $this->getToken( 'csrf' ),
		] + $params;

		// @phan-suppress-next-line PhanImpossibleCondition,PhanSuspiciousValueComparison
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
	public function login(): void {
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
	public function getToken( string $type ): string {
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
	public function getPageCreationTS( string $title ): int {
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
		$rawTS = $page->revisions[0]->timestamp;
		$ts = strtotime( $rawTS );
		if ( $ts === false ) {
			throw new APIRequestException( "Invalid timestamp in API response: `$rawTS`" );
		}
		return $ts;
	}

	/**
	 * Sysop-level inifinite protection for a given page
	 *
	 * @param string $title
	 * @param string $reason
	 */
	public function protectPage( string $title, string $reason ): void {
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
	public function blockUser( string $username, string $reason ): void {
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
	private function buildRequest( array $params ): RequestBase {
		return $this->requestFactory->createRequest(
			$params,
			$this->cookies,
			/** @param string[] $newCookies */
			function ( array $newCookies ) {
				$this->cookies = $newCookies + $this->cookies;
			}
		);
	}
}
