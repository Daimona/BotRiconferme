<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Clock;
use BotRiconferme\TaskHelper\Status;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\UserInfo;
use Generator;
use JsonException;
use RuntimeException;

/**
 * Updates the JSON list, adding and removing dates according to the API list of privileged people
 */
class UpdateList extends Task {
	private const RELEVANT_GROUPS = [ 'sysop', 'bureaucrat', 'checkuser' ];
	private const NON_GROUP_KEYS = [ 'override' => 1, 'override-perm' => 1, 'aliases' => 1 ];

	/**
	 * @var array[] The list from the API request, mapping [ user => group[] ]
	 * @phan-var array<string,list<string>>
	 */
	private array $actualList = [];

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
	public function runInternal(): Status {
		$this->actualList = $this->computeActualList();
		$pageBotList = $this->getBotList();
		$currentList = $pageBotList->getAdminsList();

		$newList = $this->computeNewList( $currentList );

		if ( !$this->listsAreDifferent( $currentList, $newList ) ) {
			return Status::NOTHING;
		}

		$this->getLogger()->info( 'Updating admin list' );

		$plainList = array_map(
			/** @return array<string,string|string[]> */
			static fn ( UserInfo $ui ): array => $ui->getInfoArray(),
			$newList
		);

		try {
			$encodedList = json_encode( $plainList, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new RuntimeException( 'Unable to encode admin list' );
		}
		$pageBotList->edit( [
			'text' => $encodedList,
			'summary' => $this->msg( 'list-update-summary' )->text()
		] );

		return $this->errors ? Status::ERROR : Status::GOOD;
	}

	/**
	 * @param array<string,UserInfo> $oldList
	 * @param array<string,UserInfo> $newList
	 */
	private function listsAreDifferent( array $oldList, array $newList ): bool {
		if ( count( $newList ) !== count( $oldList ) ) {
			return true;
		}
		if ( array_diff_key( $newList, $oldList ) ) {
			return true;
		}

		foreach ( $newList as $user => $newInfo ) {
			$oldInfo = $oldList[$user];
			if ( !$newInfo->equals( $oldInfo ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string,string[]>
	 */
	private function computeActualList(): array {
		$params = [
			'action' => 'query',
			'list' => 'allusers',
			'augroup' => 'sysop',
			'auprop' => 'groups',
			'aulimit' => 'max',
		];

		$req = $this->getWiki()->buildRequest( $params );
		return $this->extractAdminsData( $req->executeAsQuery() );
	}

	/**
	 * @return array<string,string[]>
	 */
	private function extractAdminsData( Generator $data ): array {
		$ret = [];
		$blacklist = $this->getOpt( 'exclude-admins' );
		foreach ( $data as $user ) {
			if ( in_array( $user->name, $blacklist, true ) ) {
				continue;
			}
			$interestingGroups = array_intersect( $user->groups, self::RELEVANT_GROUPS );
			$ret[ $user->name ] = array_values( $interestingGroups );
		}
		return $ret;
	}

	/**
	 * Get the new content for the list
	 *
	 * @param array<string,UserInfo> $curList
	 * @return array<string,UserInfo>
	 */
	private function computeNewList( array $curList ): array {
		$newList = unserialize( serialize( $curList ), [ 'allowed_classes' => [ UserInfo::class ] ] );

		$extra = $this->getExtraAdminGroups( $curList );
		if ( $extra ) {
			$renamed = $this->handleRenames( $newList, $extra );
			$extra = array_diff_key( $extra, $renamed );
		}
		$this->handleExtraAndMissing( $newList, $extra );
		$this->removeOverrides( $newList );

		ksort( $newList, SORT_STRING | SORT_FLAG_CASE );
		return $newList;
	}

	/**
	 * @param array<string,UserInfo> &$newList
	 * @param array<string,array<string,string>> $extra
	 */
	private function handleExtraAndMissing( array &$newList, array $extra ): void {
		$missing = $this->getMissingAdminGroups( $newList );

		$removed = [];
		foreach ( $newList as $user => $info ) {
			if ( isset( $missing[$user] ) ) {
				$updatedInfo = array_merge( $info->getInfoArray(), $missing[$user] );
				$newList[$user] = new UserInfo( $user, $updatedInfo );
				unset( $missing[$user] );
			} elseif ( isset( $extra[$user] ) ) {
				$updatedInfo = array_diff_key( $info->getInfoArray(), $extra[$user] );
				if ( array_diff_key( $updatedInfo, self::NON_GROUP_KEYS ) ) {
					$newList[$user] = new UserInfo( $user, $updatedInfo );
				} else {
					$removed[] = $user;
					unset( $newList[$user] );
				}
			}
		}
		// Add users which don't have an entry at all
		foreach ( $missing as $user => $data ) {
			$newList[$user] = new UserInfo( $user, $data );
		}
		if ( $removed ) {
			$this->getLogger()->info( 'The following admins were removed: ' . implode( ', ', $removed ) );
		}
	}

	/**
	 * Populate a list of admins with user groups that are not in the current JSON list.
	 *
	 * @param array<string,UserInfo> $botList
	 * @return array<string,array<string,string>>
	 * @phan-return array<string,array{sysop:string,checkuser?:string,bureaucrat?:string,override?:string}>
	 */
	private function getMissingAdminGroups( array $botList ): array {
		$missing = [];
		foreach ( $this->actualList as $admin => $groups ) {
			$userInfo = $botList[$admin] ?? null;
			$knownGroups = $userInfo ? $userInfo->getGroupNames() : [];
			$knownAliases = $userInfo ? $userInfo->getAliases() : [];
			$missingGroups = array_diff( $groups, $knownGroups );
			foreach ( $missingGroups as $group ) {
				$ts = $this->getFlagDate( $admin, $group, $knownAliases );
				if ( $ts === null ) {
					$this->errors[] = "$group flag date unavailable for $admin";
					continue;
				}
				$missing[$admin][$group] = $ts;
			}
		}
		return $missing;
	}

	/**
	 * Get the flag date for the given admin and group.
	 * @phan-param list<string> $aliases
	 */
	private function getFlagDate( string $username, string $group, array $aliases ): ?string {
		$this->getLogger()->info( "Retrieving $group flag date for $username" );

		$usernamesToTry = [ $username, ...$aliases ];
		$wiki = $this->getWiki();
		if ( $group === 'checkuser' ) {
			$wiki = $this->getWikiGroup()->getCentralWiki();
			$localUserIdentifier = $wiki->getLocalUserIdentifier();
			$usernamesToTry = array_map(
				static fn ( string $name ): string => $name . $localUserIdentifier,
				$usernamesToTry
			);
		}

		$baseParams = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'timestamp|details',
			'leaction' => 'rights/rights',
			'lelimit' => 'max'
		];

		$ts = null;
		foreach ( $usernamesToTry as $curName ) {
			$curParams = $baseParams;
			$curParams['letitle'] = "User:$curName";
			$data = $wiki->buildRequest( $curParams )->executeAsQuery();
			$ts = $this->extractTimestamp( $data, $group );

			if ( $ts !== null ) {
				break;
			}
		}

		if ( $ts === null ) {
			return null;
		}

		$time = strtotime( $ts );
		if ( $time === false ) {
			throw new RuntimeException( "Can't parse time `$ts`" );
		}
		return Clock::getDate( 'd/m/Y', $time );
	}

	/**
	 * Find the actual timestamp when the user was given the searched group
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
	 * @param array<string,UserInfo> $botList
	 * @return array<string,array<string,string>>
	 */
	private function getExtraAdminGroups( array $botList ): array {
		$extra = [];
		foreach ( $botList as $name => $info ) {
			$groups = $info->getGroupsWithDates();
			if ( !isset( $this->actualList[$name] ) ) {
				$extra[$name] = $groups;
			} elseif ( count( $groups ) > count( $this->actualList[$name] ) ) {
				$extra[$name] = array_diff_key( $groups, $this->actualList[$name] );
			}
		}
		return $extra;
	}

	/**
	 * @param string[] $oldNames
	 */
	private function getRenameEntries( array $oldNames ): Generator {
		foreach ( $oldNames as $oldName ) {
			$params = [
				'action' => 'query',
				'list' => 'logevents',
				'leprop' => 'title|details|timestamp',
				'letype' => 'renameuser',
				'letitle' => "Utente:$oldName",
				'lelimit' => 'max',
				// lestart seems to be broken (?)
			];

			yield from $this->getWiki()->buildRequest( $params )->executeAsQuery();
		}
	}

	/**
	 * Given a list of (old) usernames, check if these people have been renamed recently.
	 *
	 * @param string[] $oldNames
	 * @return array<string,string> [ old_name => new_name ]
	 */
	private function getRenamedUsers( array $oldNames ): array {
		if ( !$oldNames ) {
			return [];
		}
		$this->getLogger()->info( 'Checking rename for ' . implode( ', ', $oldNames ) );

		$data = $this->getRenameEntries( $oldNames );
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
	 * Checks whether any user that is on the bot list but is not an admin according to MW
	 * was actually renamed, and updates the list accordingly.
	 *
	 * @param array<string,UserInfo> &$newList
	 * @param array<string,array<string,string>> $extra
	 * @return array<string,string> Map of renamed users
	 */
	private function handleRenames( array &$newList, array $extra ): array {
		$renameMap = $this->getRenamedUsers( array_keys( $extra ) );
		foreach ( $renameMap as $oldName => $newName ) {
			$this->getLogger()->info( "Found rename $oldName -> $newName" );
			$newList[$newName] = $newList[$oldName]->withAddedAlias( $oldName );
			unset( $newList[$oldName] );
		}
		return $renameMap;
	}

	/**
	 * Remove expired overrides.
	 *
	 * @param array<string,UserInfo> &$newList
	 */
	private function removeOverrides( array &$newList ): void {
		$removed = [];
		foreach ( $newList as $user => $info ) {
			if ( PageBotList::isOverrideExpired( $info ) ) {
				$newList[$user] = $info->withoutOverride();
				$removed[] = $user;
			}
		}

		if ( $removed ) {
			$this->getLogger()->info( 'Removing overrides for users: ' . implode( ', ', $removed ) );
		}
	}
}
