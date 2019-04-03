<?php

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Request;
use BotRiconferme\Exception\TaskException;

class UpdateList extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UpdateList' );
		$actual = $this->getActualAdmins();
		$list = $this->getList();

		$errors = [];

		$missing = [];
		foreach ( $actual as $adm => $groups ) {
			$groupsList = [];
			if ( !isset( $list[ $adm ] ) ) {
				$groupsList = $groups;
			} elseif ( count( $groups ) > count( $list[$adm] ) ) {
				// Only some groups are missing
				$groupsList = array_diff_key( $groups, $list[$adm] );
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
					$errors[] = $e->getMessage();
				}
			}
			if ( $val ) {
				// Only add it if we managed to retrieve at least a date
				$missing[ $adm ] = $val;
			}
		}

		$extra = [];
		foreach ( $list as $name => $groups ) {
			if ( !isset( $actual[ $name ] ) ) {
				$extra[ $name ] = true;
			} elseif ( count( $groups ) > count( $actual[ $name ] ) ) {
				$extra[ $name ] = array_diff_key( $groups, $actual[ $name ] );
			}
		}

		if ( $missing || $extra ) {
			$newContent = $list;
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
			$newContent = array_merge( $newContent, $missing );
			$this->doUpdateList( $newContent );
		}

		if ( $errors ) {
			// We're fine with it, but don't run other tasks
			$msg = 'Task UpdateList completed with warnings.';
			$status = self::STATUS_ERROR;
		} else {
			$msg = 'Task UpdateList completed successfully';
			$status = self::STATUS_OK;
		}

		$this->getLogger()->info( $msg );
		return new TaskResult( $status, $errors );
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

		$req = new Request( $params );
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

		$req = new Request( $params );
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

		$req = new Request( $params );
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
	 * @inheritDoc
	 * Throw everything
	 */
	public function handleException( \Throwable $ex ) {
		$this->getLogger()->error(
			get_class( $ex ) . ': ' .
			$ex->getMessage() . "\nTrace:\n" .
			$ex->getTraceAsString()
		);
	}

	/**
	 * @inheritDoc
	 * Abort on anything
	 */
	public function handleError( $errno, $errstr, $errfile, $errline ) {
		throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
}
