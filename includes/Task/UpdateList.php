<?php

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;

class UpdateList extends Task {
	/** @var array[] */
	private $botList;
	/** @var array[] */
	private $actualList;

	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UpdateList' );
		$this->actualList = $this->getActualAdmins();
		$this->botList = $this->getList();

		$missing = $this->getMissingGroups();
		$extra = $this->getExtraGroups();

		if ( $missing || $extra ) {
			$this->doUpdateList( $this->getNewContent( $missing, $extra ) );
		}

		if ( $this->errors ) {
			// We're fine with it, but don't run other tasks
			$msg = 'Task UpdateList completed with warnings.';
			$status = self::STATUS_ERROR;
		} else {
			$msg = 'Task UpdateList completed successfully';
			$status = self::STATUS_OK;
		}

		$this->getLogger()->info( $msg );
		return new TaskResult( $status, $this->errors );
	}

	/**
	 * @return array
	 */
	protected function getActualAdmins() : array {
		$this->getLogger()->debug( 'Retrieving admins - API' );
		$params = [
			'action' => 'query',
			'list' => 'allusers',
			'augroup' => 'sysop',
			'auprop' => 'groups',
			'aulimit' => 'max',
		];

		$req = RequestBase::newFromParams( $params );
		return $this->extractAdmins( $req->execute() );
	}

	/**
	 * @param array $data
	 * @return array
	 */
	protected function extractAdmins( array $data ) : array {
		$ret = [];
		$blacklist = $this->getConfig()->get( 'exclude-admins' );
		foreach ( $data as $set ) {
			foreach ( $set->query->allusers as $u ) {
				if ( in_array( $u->name, $blacklist ) ) {
					continue;
				}
				$interestingGroups = array_intersect( $u->groups, [ 'sysop', 'bureaucrat', 'checkuser' ] );
				$ret[ $u->name ] = $interestingGroups;
			}
		}
		return $ret;
	}

	/**
	 * @return array
	 */
	protected function getList() : array {
		$this->getLogger()->debug( 'Retrieving admins - JSON list' );
		$content = $this->getController()->getPageContent( $this->getConfig()->get( 'list-title' ) );

		return json_decode( $content, true );
	}

	/**
	 * @return array[]
	 */
	protected function getMissingGroups() : array {
		$missing = [];
		foreach ( $this->actualList as $adm => $groups ) {
			$groupsList = [];
			if ( !isset( $this->botList[ $adm ] ) ) {
				$groupsList = $groups;
			} elseif ( count( $groups ) > count( $this->botList[$adm] ) ) {
				// Only some groups are missing
				$groupsList = array_diff_key( $groups, $this->botList[$adm] );
			}

			if ( !$groupsList ) {
				continue;
			}

			$val = [];
			foreach ( $groupsList as $group ) {
				try {
					if ( $group === 'checkuser' ) {
						$val[ $group ] = $this->getCUFlagDate( $adm );
					} else {
						$val[ $group ] = $this->getFlagDate( $adm, $group );
					}
				} catch ( TaskException $e ) {
					$this->errors[] = $e->getMessage();
				}
			}
			if ( $val ) {
				// Only add it if we managed to retrieve at least a date
				$missing[ $adm ] = $val;
			}
		}
		return $missing;
	}

	/**
	 * @param string $admin
	 * @return string
	 * @throws TaskException
	 * @todo This is hacky... At least, merge it with getFlagDate
	 */
	private function getCUFlagDate( string $admin ) : string {
		$this->getLogger()->info( "Retrieving checkuser flag date for $admin" );

		$oldUrl = $this->getConfig()->get( 'url' );
		$this->getConfig()->set( 'url', 'https://meta.wikimedia.org/w/api.php' );

		$params = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'timestamp|details',
			'leaction' => 'rights/rights',
			'letitle' => "User:$admin@itwiki",
			'lelimit' => 'max'
		];

		$req = RequestBase::newFromParams( $params );
		$data = $req->execute();

		$ts = null;
		foreach ( $data as $set ) {
			foreach ( $set->query->logevents as $entry ) {
				if ( !isset( $entry->params ) ) {
					// Old entries
					continue;
				}
				if ( in_array( 'checkuser', $entry->params->newgroups ) &&
					!in_array( 'checkuser', $entry->params->oldgroups )
				) {
					$ts = $entry->timestamp;
					break 2;
				}
			}
		}

		$this->getConfig()->set( 'url', $oldUrl );

		if ( $ts === null ) {
			throw new TaskException( "Checkuser flag date unavailable for $admin" );
		}

		return date( "d/m/Y", strtotime( $ts ) );
	}

	/**
	 * @param string $admin
	 * @param string $group
	 * @return string
	 * @throws TaskException
	 */
	protected function getFlagDate( string $admin, string $group ) : string {
		$this->getLogger()->info( "Retrieving $group flag date for $admin" );

		$params = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'timestamp|details',
			'leaction' => 'rights/rights',
			'letitle' => "User:$admin",
			'lelimit' => 'max'
		];

		$req = RequestBase::newFromParams( $params );
		$data = $req->execute();

		$ts = null;
		foreach ( $data as $set ) {
			foreach ( $set->query->logevents as $entry ) {
				if ( !isset( $entry->params ) ) {
					// Old entries
					continue;
				}
				if ( in_array( $group, $entry->params->newgroups ) &&
					!in_array( $group, $entry->params->oldgroups )
				) {
					$ts = $entry->timestamp;
					break 2;
				}
			}
		}

		if ( $ts === null ) {
			throw new TaskException( "$group flag date unavailable for $admin" );
		}

		return date( "d/m/Y", strtotime( $ts ) );
	}

	/**
	 * @return array[]
	 */
	protected function getExtraGroups() : array {
		$extra = [];
		foreach ( $this->botList as $name => $groups ) {
			if ( !isset( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = true;
			} elseif ( count( $groups ) > count( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = array_diff_key( $groups, $this->actualList[ $name ] );
			}
		}
		return $extra;
	}

	/**
	 * @param array $newContent
	 */
	protected function doUpdateList( array $newContent ) {
		$this->getLogger()->info( 'Updating admin list' );
		ksort( $newContent );
		$stringified = json_encode( $newContent );

		$params = [
			'title' => $this->getConfig()->get( 'list-title' ),
			'text' => $stringified,
			'summary' => $this->getConfig()->get( 'list-update-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param array[] $missing
	 * @param array[] $extra
	 * @return array[]
	 */
	protected function getNewContent( array $missing, array $extra ) : array {
		$newContent = $this->botList;
		foreach ( $newContent as $user => $groups ) {
			if ( isset( $missing[ $user ] ) ) {
				$newContent[ $user ] = array_merge( $groups, $missing[ $user ] );
				unset( $missing[ $user ] );
			} elseif ( isset( $extra[ $user ] ) ) {
				if ( $extra[ $user ] === true ) {
					unset( $newContent[ $user ] );
				} else {
					$newContent[ $user ] = array_diff_key( $groups, $extra[ $user ] );
				}
			}
		}
		// Add users which don't have an entry at all
		return array_merge( $newContent, $missing );
	}
}
