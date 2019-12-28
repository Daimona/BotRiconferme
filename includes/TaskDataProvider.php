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
		$override = true;
		$timestamp = PageBotList::getOverrideTimestamp( $user->getUserInfo() );

		if ( $timestamp === null ) {
			$timestamp = PageBotList::getValidFlagTimestamp( $user->getGroupsWithDates() );
			$override = false;
		}

		return ( date( 'd/m', $timestamp ) === date( 'd/m' ) &&
			// Don't add it if the date is actually today and it's not an override
			( $override || date( 'd/m/Y', $timestamp ) !== date( 'd/m/Y' ) ) );
	}

	/**
	 * Get a list of all open procedures
	 *
	 * @return PageRiconferma[]
	 */
	public function getOpenPages() : array {
		static $list = null;
		if ( $list === null ) {
			$list = [];
			$mainTitle = $this->getOpt( 'main-page-title' );
			$params = [
				'action' => 'query',
				'prop' => 'templates',
				'titles' => $mainTitle,
				'tlnamespace' => 4,
				'tllimit' => 'max'
			];

			$titleReg = ( $this->getPage( $mainTitle ) )->getRegex();
			$pages = RequestBase::newFromParams( $params )->execute()->query->pages;
			foreach ( reset( $pages )->templates as $page ) {
				if ( preg_match( "!$titleReg\/[^\/]+\/\d!", $page->title ) ) {
					$list[] = new PageRiconferma( $page->title, $this->getWiki() );
				}
			}
		}

		return $list;
	}

	/**
	 * Get a list of all procedures to be closed
	 *
	 * @return PageRiconferma[]
	 */
	public function getPagesToClose() : array {
		static $list = null;
		if ( $list === null ) {
			$list = [];
			foreach ( $this->getOpenPages() as $page ) {
				if ( time() > $page->getEndTimestamp() ) {
					$list[] = $page;
				}
			}
		}
		return $list;
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
