<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;

/**
 * Updates the JSON list, adding and removing dates according to the API list of privileged people
 */
class UpdateList extends Task {
	/** @var array[] The JSON list */
	private $botList;
	/** @var array[] The list from the API request */
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
	 * @param \stdClass $data
	 * @return array
	 */
	protected function extractAdmins( \stdClass $data ) : array {
		$ret = [];
		$blacklist = $this->getConfig()->get( 'exclude-admins' );
		foreach ( $data->query->allusers as $u ) {
			if ( in_array( $u->name, $blacklist ) ) {
				continue;
			}
			$interestingGroups = array_intersect( $u->groups, [ 'sysop', 'bureaucrat', 'checkuser' ] );
			$ret[ $u->name ] = $interestingGroups;
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
	 * Populate a list of new admins missing from the JSON list and their groups
	 *
	 * @return array[]
	 */
	protected function getMissingGroups() : array {
		$missing = [];
		foreach ( $this->actualList as $adm => $groups ) {
			if ( !isset( $this->botList[ $adm ] ) ) {
				$groupsList = $groups;
			} elseif ( count( $groups ) > count( $this->botList[$adm] ) ) {
				// Only some groups are missing
				$groupsList = array_diff_key( $groups, $this->botList[$adm] );
			} else {
				continue;
			}

			foreach ( $groupsList as $group ) {
				try {
					$missing[ $adm ][ $group ] = $this->getFlagDate( $adm, $group );
				} catch ( TaskException $e ) {
					$this->errors[] = $e->getMessage();
				}
			}
		}
		return $missing;
	}

	/**
	 * Get the flag date for the given admin and group.
	 *
	 * @param string $admin
	 * @param string $group
	 * @return string
	 * @throws TaskException
	 */
	protected function getFlagDate( string $admin, string $group ) : string {
		$this->getLogger()->info( "Retrieving $group flag date for $admin" );

		if ( $group === 'checkuser' ) {
			// Little hack
			$oldUrl = RequestBase::$url;
			RequestBase::$url = 'https://meta.wikimedia.org/w/api.php';
			$admin .= '@itwiki';
		}

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
		$ts = $this->extractTimestamp( $data, $group );

		if ( isset( $oldUrl ) ) {
			RequestBase::$url = $oldUrl;
		}

		if ( $ts === null ) {
			throw new TaskException( "$group flag date unavailable for $admin" );
		}

		return date( "d/m/Y", strtotime( $ts ) );
	}

	/**
	 * Find the actual timestamp when the user was given the searched group
	 *
	 * @param \stdClass $data
	 * @param string $group
	 * @return string|null
	 */
	private function extractTimestamp( \stdClass $data, string $group ) : ?string {
		$ts = null;
		foreach ( $data->query->logevents as $entry ) {
			if ( !isset( $entry->params ) ) {
				// Old entries
				continue;
			}
			if ( in_array( $group, $entry->params->newgroups ) &&
				!in_array( $group, $entry->params->oldgroups )
			) {
				$ts = $entry->timestamp;
				break;
			}
		}
		return $ts;
	}

	/**
	 * Get a list of admins who are in the JSON page but don't have the listed privileges anymore
	 *
	 * @return array[]
	 */
	protected function getExtraGroups() : array {
		$extra = [];
		foreach ( $this->botList as $name => $groups ) {
			if ( !isset( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = $groups;
			} elseif ( count( $groups ) > count( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = array_diff_key( $groups, $this->actualList[ $name ] );
			}
		}
		return $extra;
	}

	/**
	 * Really edit the list with the new content, if it's not already up-to-date
	 *
	 * @param array $newContent
	 */
	protected function doUpdateList( array $newContent ) {
		ksort( $newContent );

		if ( $newContent !== $this->botList ) {
			$this->getLogger()->info( 'Updating admin list' );
		} else {
			$this->getLogger()->info( 'Admin list already up-to-date' );
			return;
		}

		$stringified = json_encode( $newContent );

		$params = [
			'title' => $this->getConfig()->get( 'list-title' ),
			'text' => $stringified,
			'summary' => $this->getConfig()->get( 'list-update-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Get the new content for the list
	 *
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
				$newContent[ $user ] = array_diff_key( $groups, $extra[ $user ] );
			}
		}
		// Add users which don't have an entry at all
		$newContent = array_merge( $newContent, $missing );
		// And remove empty users
		return array_filter( $newContent );
	}
}
