<?php declare( strict_types=1 );

namespace BotRiconferme\TaskHelper;

use BotRiconferme\Clock;
use BotRiconferme\ContextSource;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Wiki\User;
use BotRiconferme\Wiki\UserInfo;

/**
 * Object holding data to be shared between different tasks.
 */
class TaskDataProvider extends ContextSource {
	/** @var User[]|null */
	private ?array $processUsers = null;
	/** @var PageRiconferma[] */
	private array $createdPages = [];
	/** @var PageRiconferma[]|null */
	private ?array $openPages = null;
	/** @var PageRiconferma[]|null */
	private ?array $pagesToClose = null;

	/**
	 * Get a list of users to execute tasks on.
	 *
	 * @return User[]
	 */
	public function getUsersToProcess(): array {
		if ( $this->processUsers === null ) {
			$this->processUsers = [];
			foreach ( $this->getBotList()->getAdminsList() as $name => $userInfo ) {
				if ( $this->shouldAddUser( $userInfo ) ) {
					$this->processUsers[ $name ] = new User( $userInfo, $this->getWiki() );
				}
			}
		}

		return $this->processUsers;
	}

	/**
	 * Whether the given user should be processed
	 */
	private function shouldAddUser( UserInfo $ui ): bool {
		$flagDate = PageBotList::getValidFlagTimestamp( $ui );
		$flaggedToday = Clock::getDate( 'd/m/Y', $flagDate ) === Clock::getDate( 'd/m/Y' );
		if ( $flaggedToday ) {
			// Ignore everything else if the user got flagged today.
			return false;
		}

		$timestamp = PageBotList::getOverrideTimestamp( $ui ) ?? $flagDate;

		return Clock::getDate( 'd/m', $timestamp ) === Clock::getDate( 'd/m' );
	}

	/**
	 * Get a list of all open procedures
	 *
	 * @return PageRiconferma[]
	 */
	public function getOpenPages(): array {
		if ( $this->openPages === null ) {
			$this->openPages = [];
			$mainTitle = $this->getOpt( 'main-page-title' );
			$params = [
				'action' => 'query',
				'prop' => 'templates',
				'titles' => $mainTitle,
				'tlnamespace' => 4,
				'tllimit' => 'max'
			];

			$titleReg = $this->getPage( $mainTitle )->getRegex( '!' );
			$pages = $this->getWiki()->getRequestFactory()->createStandaloneRequest( $params )->executeAsQuery();
			foreach ( $pages->current()->templates as $page ) {
				if ( preg_match( "!$titleReg/[^/]+/\d!", $page->title ) ) {
					$this->openPages[] = new PageRiconferma( $page->title, $this->getWiki() );
				}
			}
		}

		return $this->openPages;
	}

	/**
	 * Get a list of all procedures to be closed
	 *
	 * @return PageRiconferma[]
	 */
	public function getPagesToClose(): array {
		if ( $this->pagesToClose === null ) {
			$this->pagesToClose = [];
			foreach ( $this->getOpenPages() as $page ) {
				if ( Clock::now() > $page->getEndTimestamp() ) {
					$this->pagesToClose[] = $page;
				}
			}
		}
		return $this->pagesToClose;
	}

	/**
	 * Discard an user from the current list
	 */
	public function removeUser( string $name ): void {
		unset( $this->processUsers[ $name ] );
	}

	/**
	 * @return PageRiconferma[]
	 */
	public function getCreatedPages(): array {
		return $this->createdPages;
	}

	public function addCreatedPage( PageRiconferma $page ): void {
		$this->createdPages[] = $page;
	}
}
