<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\TaskResult;

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
	protected function getSubtasksMap(): array {
		// Everything is done here.
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$this->actualList = $this->getActualAdmins();
		$this->botList = PageBotList::get()->getAdminsList();

		$missing = $this->getMissingGroups();
		$extra = $this->getExtraGroups();

		$newContent = $this->getNewContent( $missing, $extra );

		if ( $newContent === $this->botList ) {
			return TaskResult::STATUS_NOTHING;
		}

		$this->getLogger()->info( 'Updating admin list' );

		PageBotList::get()->edit( [
			'text' => json_encode( $newContent ),
			'summary' => $this->msg( 'list-update-summary' )->text()
		] );

		return $this->errors ? TaskResult::STATUS_ERROR : TaskResult::STATUS_GOOD;
	}

	/**
	 * @return array
	 */
	protected function getActualAdmins() : array {
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
		$blacklist = $this->getOpt( 'exclude-admins' );
		foreach ( $data->query->allusers as $u ) {
			if ( in_array( $u->name, $blacklist ) ) {
				continue;
			}
			$interestingGroups = array_intersect( $u->groups, [ 'sysop', 'bureaucrat', 'checkuser' ] );
			$ret[ $u->name ] = array_values( $interestingGroups );
		}
		return $ret;
	}

	/**
	 * Populate a list of new admins missing from the JSON list and their groups
	 *
	 * @return array[]
	 */
	protected function getMissingGroups() : array {
		$missing = [];
		foreach ( $this->actualList as $adm => $groups ) {
			$curMissing = array_diff( $groups, array_keys( $this->botList[$adm] ?? [] ) );

			foreach ( $curMissing as $group ) {
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

		$url = DEFAULT_URL;
		if ( $group === 'checkuser' ) {
			$url = 'https://meta.wikimedia.org/w/api.php';
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

		$data = RequestBase::newFromParams( $params )->setUrl( $url )->execute();
		$ts = $this->extractTimestamp( $data, $group );

		if ( $ts === null ) {
			throw new TaskException( "$group flag date unavailable for $admin" );
		}

		return date( 'd/m/Y', strtotime( $ts ) );
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
			if ( isset( $entry->params ) ) {
				$addedGroups = array_diff( $entry->params->newgroups, $entry->params->oldgroups );
				if ( in_array( $group, $addedGroups ) ) {
					$ts = $entry->timestamp;
					break;
				}
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
			// These are not groups
			unset( $groups[ 'override' ], $groups[ 'override-perm' ] );
			if ( !isset( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = $groups;
			} elseif ( count( $groups ) > count( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = array_diff_key( $groups, $this->actualList[ $name ] );
			}
		}
		return $extra;
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
		// Add users which don't have an entry at all, and remove empty users
		$newContent = array_filter( array_merge( $newContent, $missing ) );
		$newContent = $this->removeOverrides( $newContent );
		ksort( $newContent, SORT_STRING | SORT_FLAG_CASE );
		return $newContent;
	}

	/**
	 * Remove expired overrides. This must happen after the override date has been used AND
	 * after the "normal" date has passed. We do it 3 days later to be sure.
	 *
	 * @param array[] $newContent
	 * @return array[]
	 */
	protected function removeOverrides( array $newContent ) : array {
		$removed = [];
		foreach ( $newContent as $user => $groups ) {
			if ( !isset( $groups['override'] ) ) {
				continue;
			}

			$flagTS = PageBotList::getValidFlagTimestamp( $groups );
			$usualTS = strtotime( date( 'Y' ) . '-' . date( 'm-d', $flagTS ) );
			$overrideTS = \DateTime::createFromFormat( 'd/m/Y', $groups['override'] )->getTimestamp();
			$delay = 60 * 60 * 24 * 3;

			if ( time() > $usualTS + $delay && time() > $overrideTS + $delay ) {
				unset( $newContent[ $user ][ 'override' ] );
				$removed[] = $user;
			}
		}

		if ( $removed ) {
			$this->getLogger()->info( 'Removing overrides for users: ' . implode( ', ', $removed ) );
		}

		return $newContent;
	}
}
