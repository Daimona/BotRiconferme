<?php

namespace BotRiconferme;

class TaskDataProvider extends ContextSource {
	/** @var string[] */
	private $users;

	/** @var string[] */
	private $createdPages = [];

	/**
	 * @return string[]
	 */
	public function getUsersToProcess() : array {
		if ( $this->users === null ) {
			$this->getLogger()->debug( 'Retrieving users list' );
			$content = $this->getController()->getPageContent( $this->getConfig()->get( 'list-title' ) );
			$users = json_decode( $content, true );
			$now = date( 'd/m' );

			$this->users = [];
			foreach ( $users as $user => $groups ) {
				$date = max( strtotime( $groups['checkuser'] ) ?? 0, strtotime( $groups['bureaucrat'] ) ?? 0 );
				if ( $date === 0 ) {
					$date = strtotime( $groups['sysop'] );
				}
				if ( date( 'd/m', $date ) === $now ) {
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
