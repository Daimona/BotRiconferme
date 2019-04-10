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
				if ( array_intersect_key( $groups, [ 'override-perm', 'override' ] ) ) {
					// A one-time override takes precedence
					$timestamp = $groups[ 'override' ] ?? $groups[ 'override-perm' ];
					$override = true;
				} else {
					$timestamp = PageBotList::getValidTimestamp( $groups );
					$override = false;
				}

				if ( date( 'd/m', $timestamp ) === date( 'd/m' ) &&
					// Don't trigger if the date is actually today and it's not an override
					( $override || date( 'd/m/Y', $timestamp ) !== date( 'd/m/Y' ) )
				) {
					$this->processUsers[ $user ] = $groups;
				}
			}
		}

		return $this->processUsers;
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

			$pages = RequestBase::newFromParams( $params )->execute()->query->pages;
			$reg = ( new Page( $mainTitle ) )->getRegex();
			$list = [];
			foreach ( reset( $pages )->templates as $page ) {
				if ( preg_match( "!$reg\/[^\/]+\/\d!", $page->title ) ) {
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
