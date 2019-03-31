<?php

class UpdateList extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : int {
		$this->getLogger()->info( 'Starting task UpdateList' );
		$actual = $this->getActualAdmins();
		$list = $this->getList();

		$missing = [];
		$extra = [];
		foreach ( $actual as $adm ) {
			if ( !isset( $list[ $adm ] ) ) {
				$ts = $this->getFlagTimestamp( $adm );
				if ( $ts !== null ) {
					$missing[ $adm ] = $ts;
				}
			}
		}

		foreach ( $list as $name => $_ ) {
			if ( !in_array( $name, $actual ) ) {
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
		return self::STATUS_OK;
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
			'aulimit' => 'max'
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
		foreach ( $data as $set ) {
			foreach ( $set->query->allusers as $u ) {
				$ret[] = $u->name;
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
	 * @return string|null
	 */
	protected function getFlagTimestamp( string $admin ) : ?string {
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
				if ( in_array( 'sysop', $entry->params->newgroups ) &&
					!in_array( 'sysop', $entry->params->oldgroups )
				) {
					$ts = $entry->timestamp;
					break;
				}
			}
		}

		if ( $ts === null ) {
			$this->getLogger()->warning( "Flag date unavailable for $admin" );
			return null;
		}

		return DateTime::createFromFormat( DateTime::ISO8601, $ts )->getTimestamp();
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
		$req = new Request( $params );
		$req->execute();
	}

	/**
	 * @inheritDoc
	 * Throw everything
	 */
	public function handleException( Throwable $ex ) {
		$this->getLogger()->error( $ex );
	}

	/**
	 * @inheritDoc
	 * Abort on anything
	 */
	public function handleError( $errno, $errstr, $errfile, $errline ) {
		throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
}
