<?php

namespace BotRiconferme;

class TaskDataProvider extends ContextSource {
	/** @var array[] */
	private $users;

	/** @var string[] */
	private $createdPages = [];

	/**
	 * @return array[]
	 */
	public function getUsersToProcess() : array {
		if ( $this->users === null ) {
			$this->getLogger()->debug( 'Retrieving users list' );
			$content = $this->getController()->getPageContent( $this->getConfig()->get( 'list-title' ) );
			$listUsers = json_decode( $content, true );

			$this->users = [];
			foreach ( $listUsers as $user => $groups ) {
				$timestamp = $this->getValidTimestamp( $groups );

				if ( date( 'd/m', $timestamp ) === date( 'd/m' ) &&
					// Don't trigger if the date is actually today
					date( 'd/m/Y', $timestamp ) !== date( 'd/m/Y' )
				) {
					$this->users[ $user ] = $groups;
				}
			}
		}

		return $this->users;
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
			// @phan-suppress-next-line PhanTypeArraySuspicious Phan cannot know...
			$timestamp = \DateTime::createFromFormat( 'd/m/Y', $groups[ 'sysop' ] )->getTimestamp();
		}
		return $timestamp;
	}

	/**
	 * @param string $name
	 */
	public function removeUser( string $name ) {
		unset( $this->users[ $name ] );
	}

	/**
	 * @return string[]
	 */
	public function getCreatedPages() : array {
		return $this->createdPages;
	}

	/**
	 * @param string $title
	 */
	public function addCreatedPages( string $title ) {
		$this->createdPages[] = $title;
	}
}
