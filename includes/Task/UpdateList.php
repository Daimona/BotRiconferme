<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Exception\TaskException;
use BotRiconferme\Request\RequestBase;
use BotRiconferme\TaskResult;
use BotRiconferme\Wiki\Page\PageBotList;

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
		$pageBotList = PageBotList::get( $this->getWiki() );
		$this->botList = $pageBotList->getDecodedContent();

		$missing = $this->getMissingGroups();
		$extra = $this->getExtraGroups();

		$newContent = $this->getNewContent( $missing, $extra );

		if ( $newContent === $this->botList ) {
			return TaskResult::STATUS_NOTHING;
		}

		$this->getLogger()->info( 'Updating admin list' );

		$pageBotList->edit( [
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
			if ( in_array( $u->name, $blacklist, true ) ) {
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

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable $url is never null
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
			if (
				isset( $entry->params ) &&
				in_array( $group, array_diff( $entry->params->newgroups, $entry->params->oldgroups ), true )
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
			$groups = array_diff_key( $groups, array_fill_keys( PageBotList::NON_GROUP_KEYS, 1 ) );
			if ( !isset( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = $groups;
			} elseif ( count( $groups ) > count( $this->actualList[ $name ] ) ) {
				$extra[ $name ] = array_diff_key( $groups, $this->actualList[ $name ] );
			}
		}
		return $extra;
	}

	/**
	 * @param string[] $names
	 * @return \stdClass
	 */
	private function getRenameEntries( array $names ) : \stdClass {
		$titles = array_map( static function ( $x ) {
			return "Utente:$x";
		}, $names );

		$params = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'title|details|timestamp',
			'letype' => 'renameuser',
			'letitle' => implode( '|', $titles ),
			'lelimit' => 'max',
			// lestart seems to be broken (?)
		];

		return RequestBase::newFromParams( $params )->execute();
	}

	/**
	 * Given a list of (old) usernames, check if these people have been renamed recently.
	 *
	 * @param string[] $names
	 * @return string[] [ old_name => new_name ]
	 */
	protected function getRenamedUsers( array $names ) : array {
		if ( !$names ) {
			return [];
		}
		$this->getLogger()->info( 'Checking rename for ' . implode( ', ', $names ) );

		$data = $this->getRenameEntries( $names );
		$ret = [];
		foreach ( $data->query->logevents as $entry ) {
			// 1 month is arbitrary
			if ( strtotime( $entry->timestamp ) > strtotime( '-1 month' ) ) {
				$par = $entry->params;
				$ret[ $par->olduser ] = $par->newuser;
			}
		}
		$this->getLogger()->info( 'Renames found: ' . var_export( $ret, true ) );
		return $ret;
	}

	/**
	 * Update aliases and overrides for renamed users
	 *
	 * @param array &$newContent
	 * @param array $removed
	 */
	private function handleRenames( array &$newContent, array $removed ) : void {
		$renameMap = $this->getRenamedUsers( array_keys( $removed ) );
		foreach ( $removed as $oldName => $info ) {
			if (
				array_key_exists( $oldName, $renameMap ) &&
				array_key_exists( $renameMap[$oldName], $newContent )
			) {
				// This user was renamed! Add this name as alias, if they're still listed
				$newName = $renameMap[ $oldName ];
				$this->getLogger()->info( "Found rename $oldName -> $newName" );
				$aliases = array_unique( array_merge( $newContent[ $newName ]['aliases'], [ $oldName ] ) );
				$newContent[ $newName ]['aliases'] = $aliases;
				// Transfer overrides to the new name.
				$overrides = array_diff_key( $info, [ 'override' => 1, 'override-perm' => 1 ] );
				$newContent[ $newName ] = array_merge( $newContent[ $newName ], $overrides );
			}
		}
	}

	/**
	 * @param array[] &$newContent
	 * @param array[] $missing
	 * @param array[] $extra
	 * @return string[] Removed users
	 */
	private function handleExtraAndMissing(
		array &$newContent,
		array $missing,
		array $extra
	) : array {
		$removed = [];
		foreach ( $newContent as $user => $groups ) {
			if ( isset( $missing[ $user ] ) ) {
				$newContent[ $user ] = array_merge( $groups, $missing[ $user ] );
				unset( $missing[ $user ] );
			} elseif ( isset( $extra[ $user ] ) ) {
				$newGroups = array_diff_key( $groups, $extra[ $user ] );
				if ( array_diff_key( $newGroups, array_fill_keys( PageBotList::NON_GROUP_KEYS, 1 ) ) ) {
					$newContent[ $user ] = $newGroups;
				} else {
					$removed[$user] = $newContent[$user];
					unset( $newContent[ $user ] );
				}
			}
		}
		// Add users which don't have an entry at all
		$newContent = array_merge( $newContent, $missing );
		return $removed;
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

		$removed = $this->handleExtraAndMissing( $newContent, $missing, $extra );

		$this->handleRenames( $newContent, $removed );

		$this->removeOverrides( $newContent );

		ksort( $newContent, SORT_STRING | SORT_FLAG_CASE );

		return $newContent;
	}

	/**
	 * Remove expired overrides. This must happen after the override date has been used AND
	 * after the "normal" date has passed. We do it 3 days later to be sure.
	 *
	 * @param array[] &$newContent
	 */
	protected function removeOverrides( array &$newContent ) : void {
		$removed = [];
		foreach ( $newContent as $user => $groups ) {
			if ( PageBotList::isOverrideExpired( $groups ) ) {
				unset( $newContent[ $user ][ 'override' ] );
				$removed[] = $user;
			}
		}

		if ( $removed ) {
			$this->getLogger()->info( 'Removing overrides for users: ' . implode( ', ', $removed ) );
		}
	}
}
