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
				$date = max( strtotime( $groups['checkuser'] ) ?? 0, strtotime( $groups['bureaucrat'] ) ?? 0 );
				if ( $date === 0 ) {
					$date = strtotime( $groups['sysop'] );
				}
				// Don't trigger if the date is actually today
				if ( date( 'd/m', $date ) === date( 'd/m' ) && date( 'd/m/Y', $date ) !== date( 'd/m/Y' ) ) {
					$this->users[ $user ] = $groups;
				}
			}
		}

		return $this->users;
	}

	/**
	 * @param string[] $titles
	 */
	public function setCreatedPages( array $titles ) {
		$this->createdPages = $titles;
	}

	/**
	 * @return string[]
	 */
	public function getCreatedPages() : array {
		return $this->createdPages;
	}
}
