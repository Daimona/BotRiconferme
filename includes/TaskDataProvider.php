<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Wiki\User;

/**
 * Object holding data to be shared between different tasks.
 */
class TaskDataProvider extends ContextSource {
	/** @var User[]|null */
	private $processUsers;
	/** @var PageRiconferma[] */
	private $createdPages = [];
	/** @var PageRiconferma[]|null */
	private $openPages;
	/** @var PageRiconferma[]|null */
	private $pagesToClose;

	/**
	 * Get a list of users to execute tasks on.
	 *
	 * @return User[]
	 */
	public function getUsersToProcess() : array {
		if ( $this->processUsers === null ) {
			$this->processUsers = [];
			foreach ( PageBotList::get( $this->getWiki() )->getAdminsList() as $name => $user ) {
				if ( $this->shouldAddUser( $user ) ) {
					$this->processUsers[ $name ] = $user;
				}
			}
		}

		return $this->processUsers;
	}

	/**
	 * Whether the given user should be processed
	 *
	 * @param User $user
	 * @return bool
	 */
	private function shouldAddUser( User $user ) : bool {
		$timestamp = PageBotList::getOverrideTimestamp( $user->getUserInfo() );
		$override = $timestamp !== null;

		if ( $timestamp === null ) {
			$timestamp = PageBotList::getValidFlagTimestamp( $user->getGroupsWithDates() );
		}

		$datesMatch = date( 'd/m', $timestamp ) === date( 'd/m' );
		$dateIsToday = date( 'd/m/Y', $timestamp ) === date( 'd/m/Y' );
		return ( $datesMatch && ( $override || !$dateIsToday ) );
	}

	/**
	 * Get a list of all open procedures
	 *
	 * @return PageRiconferma[]
	 */
	public function getOpenPages() : array {
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

			$titleReg = $this->getPage( $mainTitle )->getRegex();
			$pages = RequestBase::newFromParams( $params )->execute()->query->pages;
			foreach ( reset( $pages )->templates as $page ) {
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
	public function getPagesToClose() : array {
		if ( $this->pagesToClose === null ) {
			$this->pagesToClose = [];
			foreach ( $this->getOpenPages() as $page ) {
				if ( time() > $page->getEndTimestamp() ) {
					$this->pagesToClose[] = $page;
				}
			}
		}
		return $this->pagesToClose;
	}

	/**
	 * Discard an user from the current list
	 *
	 * @param string $name
	 */
	public function removeUser( string $name ) {
		unset( $this->processUsers[ $name ] );
	}

	/**
	 * @return PageRiconferma[]
	 */
	public function getCreatedPages() : array {
		return $this->createdPages;
	}

	/**
	 * @param PageRiconferma $page
	 */
	public function addCreatedPage( PageRiconferma $page ) {
		$this->createdPages[] = $page;
	}
}
