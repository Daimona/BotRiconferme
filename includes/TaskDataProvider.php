<?php declare( strict_types=1 );

namespace BotRiconferme;

use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Request\RequestBase;

/**
 * Object holding data to be shared between different tasks.
 */
class TaskDataProvider extends ContextSource {
	/** @var array[] */
	private $processUsers;
	/** @var array[] */
	private $allUsers;
	/** @var PageRiconferma[] */
	private $createdPages = [];

	/**
	 * Get the full content of the JSON users list
	 *
	 * @return array[]
	 */
	public function getUsersList() : array {
		if ( $this->allUsers === null ) {
			$this->getLogger()->debug( 'Retrieving users list' );
			$this->allUsers = PageBotList::get()->getAdminsList();
		}

		return $this->allUsers;
	}

	/**
	 * Get a list of users to execute tasks on.
	 *
	 * @return array[]
	 */
	public function getUsersToProcess() : array {
		if ( $this->processUsers === null ) {
			$this->processUsers = [];
			foreach ( $this->getUsersList() as $user => $groups ) {
				$timestamp = $this->getValidTimestamp( $groups );

				if ( date( 'd/m', $timestamp ) === date( 'd/m' ) &&
					// Don't trigger if the date is actually today
					date( 'd/m/Y', $timestamp ) !== date( 'd/m/Y' )
				) {
					$this->processUsers[ $user ] = $groups;
				}
			}
		}

		return $this->processUsers;
	}

	/**
	 * Get the valid timestamp for the given groups
	 *
	 * @param array $groups
	 * @return int
	 */
	private function getValidTimestamp( array $groups ) : int {
		$checkuser = isset( $groups[ 'checkuser' ] ) ?
			\DateTime::createFromFormat( 'd/m/Y', $groups[ 'checkuser' ] )->getTimestamp() :
			0;
		$bureaucrat = isset( $groups[ 'bureaucrat' ] ) ?
			\DateTime::createFromFormat( 'd/m/Y', $groups[ 'bureaucrat' ] )->getTimestamp() :
			0;

		$timestamp = max( $bureaucrat, $checkuser );
		if ( $timestamp === 0 ) {
			$timestamp = \DateTime::createFromFormat( 'd/m/Y', $groups[ 'sysop' ] )->getTimestamp();
		}
		return $timestamp;
	}

	/**
	 * Get a list of all open procedures
	 *
	 * @return PageRiconferma[]
	 */
	public function getOpenPages() : array {
		static $list = null;
		if ( $list === null ) {
			$mainTitle = $this->getConfig()->get( 'main-page-title' );
			$params = [
				'action' => 'query',
				'prop' => 'templates',
				'titles' => $mainTitle,
				'tlnamespace' => 4,
				'tllimit' => 'max'
			];

			$res = RequestBase::newFromParams( $params )->execute();
			$pages = $res->query->pages;
			$list = [];
			foreach ( reset( $pages )->templates as $page ) {
				if ( preg_match( "!$mainTitle\/[^\/]+\/\d!", $page->title ) ) {
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
			$allPages = $this->getOpenPages();
			$list = [];
			foreach ( $allPages as $page ) {
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
