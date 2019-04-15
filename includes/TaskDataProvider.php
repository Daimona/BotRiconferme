<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Request\RequestBase;

/**
 * Object holding data to be shared between different tasks.
 */
class TaskDataProvider extends ContextSource {
	/** @var array[] */
	private $processUsers;
	/** @var PageRiconferma[] */
	private $createdPages = [];

	/**
	 * Get a list of users to execute tasks on.
	 *
	 * @return array[]
	 */
	public function getUsersToProcess() : array {
		if ( $this->processUsers === null ) {
			$this->processUsers = [];
			foreach ( PageBotList::get()->getAdminsList() as $user => $groups ) {
				if ( $this->shouldAddUser( $groups ) ) {
					$this->processUsers[ $user ] = $groups;
				}
			}
		}

		return $this->processUsers;
	}

	/**
	 * Whether a user with the given groups should be processed
	 *
	 * @param string[] $groups
	 * @return bool
	 */
	private function shouldAddUser( array $groups ) : bool {
		$override = true;
		$timestamp = PageBotList::getOverrideTimestamp( $groups );

		if ( $timestamp === null ) {
			$timestamp = PageBotList::getValidFlagTimestamp( $groups );
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

			$titleReg = ( new Page( $mainTitle ) )->getRegex();
			$pages = RequestBase::newFromParams( $params )->execute()->query->pages;
			foreach ( reset( $pages )->templates as $page ) {
				if ( preg_match( "!$titleReg\/[^\/]+\/\d!", $page->title ) ) {
					$list[] = new PageRiconferma( $page->title );
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
	public function addCreatedPages( PageRiconferma $page ) {
		$this->createdPages[] = $page;
	}
}
