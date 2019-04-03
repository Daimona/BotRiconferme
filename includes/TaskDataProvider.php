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
			$users = json_decode( $content, true );

			$this->users = [];
			foreach ( $users as $user => $groups ) {
				$c = isset( $groups[ 'checkuser' ] ) ?
					\DateTime::createFromFormat( 'd/m/Y', $groups[ 'checkuser' ] )->getTimestamp() :
					0;
				$b = isset( $groups[ 'bureaucrat' ] ) ?
					\DateTime::createFromFormat( 'd/m/Y', $groups[ 'bureaucrat' ] )->getTimestamp() :
					0;

				$timestamp = max( $b, $c );
				if ( $timestamp === 0 ) {
					// @phan-suppress-next-line PhanTypeArraySuspicious Phan cannot know...
					$timestamp = \DateTime::createFromFormat( 'd/m/Y', $groups[ 'sysop' ] )->getTimestamp();
				}

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
	 * @param string[] $titles
	 */
	public function setCreatedPages( array $titles ) {
		$this->createdPages = $titles;
	}
}
