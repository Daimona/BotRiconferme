<?php

namespace BotRiconferme\Tasks;

use BotRiconferme\TaskResult;
use BotRiconferme\Request;
use BotRiconferme\Exceptions\TaskException;

class UpdateList extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UpdateList' );
		$actual = $this->getActualAdmins();
		$list = $this->getList();

		$missing = [];
		$extra = [];
		foreach ( $actual as $adm => $groups ) {
			if ( !isset( $list[ $adm ] ) ) {
				$val = [];
				foreach ( $groups as $group ) {
					$val[ $group ] = $this->getFlagDate( $adm, $group );
				}
				$missing[ $adm ] = $val;
			}
		}

		foreach ( $list as $name => $_ ) {
			if ( !isset( $actual[ $name ] ) ) {
				$extra[ $name ] = true;
			}
		}

		if ( $missing || $extra ) {
			$newContent = $list;
			$newContent = array_merge( $newContent, $missing );
			$newContent = array_diff_key( $newContent, $extra );
			$this->doUpdateList( $newContent );
		}

		$this->getLogger()->info( 'Task UpdateList completed successfully' );
		return new TaskResult( self::STATUS_OK );
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
	 */
	protected function getFlagDate( string $admin, string $group ) : string {
		$this->getLogger()->info( "Retrieving flag date for $admin" );
		$params = [
			'action' => 'query',
			'list' => 'logevents',
			'leprop' => 'timestamp|details',
			'leaction' => 'rights/rights',
			'letitle' => "User:$admin"
		];

		$req = new Request( $params );
		$data = $req->execute();

		$ts = null;
		foreach ( $data as $set ) {
			foreach ( $set->query->logevents as $entry ) {
				if ( in_array( $group, $entry->params->newgroups ) &&
					!in_array( $group, $entry->params->oldgroups )
				) {
					$ts = $entry->timestamp;
					break;
				}
			}
		}

		if ( $ts === null ) {
			throw new TaskException( "Flag date unavailable for $admin" );
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
			'action' => 'edit',
			'title' => $this->getConfig()->get( 'list-title' ),
			'text' => $stringified,
			'summary' => $this->getConfig()->get( 'list-update-summary' ),
			'bot' => 1,
			'token' => $this->getController()->getToken( 'csrf' )
		];

		$this->getController()->login();
		$req = new Request( $params, true );
		$req->execute();
	}

	/**
	 * @inheritDoc
	 * Throw everything
	 */
	public function handleException( \Throwable $ex ) {
		$this->getLogger()->error( $ex->getMessage() );
	}

	/**
	 * @inheritDoc
	 * Abort on anything
	 */
	public function handleError( $errno, $errstr, $errfile, $errline ) {
		throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
}
