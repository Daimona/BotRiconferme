<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\Page\PageBotList;
use Generator;

/**
 * Updates the JSON list, adding and removing dates according to the API list of privileged people
 */
class UpdateList extends Task {
	private const NON_GROUP_KEYS = [ 'override', 'override-perm', 'aliases' ];

	/**
	 * @var array The JSON list
	 * @phan-var array<string,array<string,string|string[]>>
	 */
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
	public function runInternal(): int {
		$this->actualList = $this->getActualAdmins();
		$pageBotList = $this->getBotList();
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
	 * @return string[][]
	 * @phan-return array<string,string[]>
	 */
	protected function getActualAdmins(): array {
		$params = [
			'action' => 'query',
			'list' => 'allusers',
			'augroup' => 'sysop',
			'auprop' => 'groups',
			'aulimit' => 'max',
		];

		$req = $this->getWiki()->getRequestFactory()->createStandaloneRequest( $params );
		return $this->extractAdmins( $req->executeAsQuery() );
	}

	/**
	 * @param Generator $data
	 * @return string[][]
	 * @phan-return array<string,string[]>
	 */
	protected function extractAdmins( Generator $data ): array {
		$ret = [];
		$blacklist = $this->getOpt( 'exclude-admins' );
		foreach ( $data as $u ) {
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
	 * @return string[][]
	 */
	protected function getMissingGroups(): array {
		$missing = [];
		foreach ( $this->actualList as $admin => $data ) {
			$missingProps = array_diff( $data, array_keys( $this->botList[$admin] ?? [] ) );
			$missingGroups = array_diff( $missingProps, self::NON_GROUP_KEYS );

			foreach ( $missingGroups as $group ) {
				$ts = $this->getFlagDate( $admin, $group );
				if ( $ts === null ) {
					$aliases = $data['aliases'] ?? [];
					if ( $aliases ) {
						$this->getLogger()->info( "No $group flag date for $admin, trying aliases" );
						foreach ( $aliases as $alias ) {
							$ts = $this->getFlagDate( $alias, $group );
							if ( $ts !== null ) {
								break;
							}
						}
					}
					if ( $ts === null ) {
						$this->errors[] = "$group flag date unavailable for $admin";
						continue;
					}
				}
				$missing[ $admin ][ $group ] = $ts;
			}
		}
		return $missing;
	}

	/**
	 * Get the flag date for the given admin and group.
	 *
	 * @param string $admin
	 * @param string $group
	 * @return string|null
	 */
	protected function getFlagDate( string $admin, string $group ): ?string {
		$this->getLogger()->info( "Retrieving $group flag date for $admin" );

		$wiki = $this->getWiki();
		if ( $group === 'checkuser' ) {
			$wiki = $this->getWikiGroup()->getCentralWiki();
			$admin .= $wiki->getLocalUserIdentifier();
		}

		$params = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'timestamp|details',
			'leaction' => 'rights/rights',
			'letitle' => "User:$admin",
			'lelimit' => 'max'
		];

		$data = $wiki->getRequestFactory()->createStandaloneRequest( $params )->executeAsQuery();
		$ts = $this->extractTimestamp( $data, $group );

		return $ts !== null ? date( 'd/m/Y', strtotime( $ts ) ) : null;
	}

	/**
	 * Find the actual timestamp when the user was given the searched group
	 *
	 * @param Generator $data
	 * @param string $group
	 * @return string|null
	 */
	private function extractTimestamp( Generator $data, string $group ): ?string {
		$ts = null;
		foreach ( $data as $entry ) {
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
	 * @return string[][]
	 */
	protected function getExtraGroups(): array {
		$extra = [];
		foreach ( $this->botList as $name => $groups ) {
			$groups = array_diff_key( $groups, array_fill_keys( self::NON_GROUP_KEYS, 1 ) );
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
	 * @return Generator
	 */
	private function getRenameEntries( array $names ): Generator {
		$titles = array_map( static function ( string $x ): string {
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

		return $this->getWiki()->getRequestFactory()->createStandaloneRequest( $params )->executeAsQuery();
	}

	/**
	 * Given a list of (old) usernames, check if these people have been renamed recently.
	 *
	 * @param string[] $names
	 * @return string[] [ old_name => new_name ]
	 */
	protected function getRenamedUsers( array $names ): array {
		if ( !$names ) {
			return [];
		}
		$this->getLogger()->info( 'Checking rename for ' . implode( ', ', $names ) );

		$data = $this->getRenameEntries( $names );
		$ret = [];
		foreach ( $data as $entry ) {
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
	 * @phan-param array<string,array<string,string|string[]>> $newContent
	 * @param string[][] $removed
	 */
	private function handleRenames( array &$newContent, array $removed ): void {
		$renameMap = $this->getRenamedUsers( array_keys( $removed ) );
		foreach ( $removed as $oldName => $info ) {
			if (
				array_key_exists( $oldName, $renameMap ) &&
				array_key_exists( $renameMap[$oldName], $newContent )
			) {
				// This user was renamed! Add this name as alias, if they're still listed
				$newName = $renameMap[ $oldName ];
				$this->getLogger()->info( "Found rename $oldName -> $newName" );
				$aliases = array_unique( array_merge( $newContent[ $newName ]['aliases'] ?? [], [ $oldName ] ) );
				$newContent[ $newName ]['aliases'] = $aliases;
				// Transfer overrides to the new name.
				$overrides = array_diff_key( $info, [ 'override' => 1, 'override-perm' => 1 ] );
				$newContent[ $newName ] = array_merge( $newContent[ $newName ], $overrides );
			}
		}
	}

	/**
	 * @param array &$newContent
	 * @phan-param array<string,array<string,string|string[]>> $newContent
	 * @param string[][] $missing
	 * @param string[][] $extra
	 * @return string[][] Removed users
	 */
	private function handleExtraAndMissing(
		array &$newContent,
		array $missing,
		array $extra
	): array {
		$removed = [];
		foreach ( $newContent as $user => $data ) {
			if ( isset( $missing[ $user ] ) ) {
				$newContent[ $user ] = array_merge( $data, $missing[ $user ] );
				unset( $missing[ $user ] );
			} elseif ( isset( $extra[ $user ] ) ) {
				$newGroups = array_diff_key( $data, $extra[ $user ] );
				if ( array_diff_key( $newGroups, array_fill_keys( self::NON_GROUP_KEYS, 1 ) ) ) {
					$newContent[ $user ] = $newGroups;
				} else {
					$removed[$user] = $data;
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
	 * @param string[][] $missing
	 * @param string[][] $extra
	 * @return array[]
	 */
	protected function getNewContent( array $missing, array $extra ): array {
		$newContent = $this->botList;

		$removed = $this->handleExtraAndMissing( $newContent, $missing, $extra );

		$this->handleRenames( $newContent, $removed );

		$this->removeOverrides( $newContent );

		ksort( $newContent, SORT_STRING | SORT_FLAG_CASE );

		return $newContent;
	}

	/**
	 * Remove expired overrides.
	 *
	 * @param array[] &$newContent
	 */
	protected function removeOverrides( array &$newContent ): void {
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
