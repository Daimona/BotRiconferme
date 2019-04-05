<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Exception\TaskException;
use BotRiconferme\TaskResult;
use BotRiconferme\WikiController;

/**
 * Start a vote if there are >= 15 opposing comments
 */
class StartVote extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task StartVote' );

		$pages = $this->getDataProvider()->getOpenPages();

		if ( $pages ) {
			$actualPages = [];
			foreach ( $pages as $page ) {
				if ( WikiController::hasOpposition( $page ) ) {
					try {
						$this->openVote( $page );
						$actualPages[] = $page;
					} catch ( TaskException $e ) {
						$this->getLogger()->warning( $e->getMessage() );
					}
				}
			}

			if ( $actualPages ) {
				$this->updateVotePage( $actualPages );
				$this->updateNews( count( $actualPages ) );
			} else {
				$this->getLogger()->info( 'No votes to open' );
			}
		} else {
			$this->getLogger()->info( 'No open procedures.' );
		}

		$this->getLogger()->info( 'Task StartVote completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Start the vote for the given page
	 *
	 * @param string $title
	 * @throws TaskException
	 */
	protected function openVote( string $title ) {
		$this->getLogger()->info( "Starting vote on $title" );

		$content = $this->getController()->getPageContent( $title );

		if ( preg_match( '/<!-- SEZIONE DA UTILIZZARE PER/', $content ) === false ) {
			throw new TaskException( "Vote already opened in $title" );
		}

		$newContent = preg_replace(
			'!^La procedura di riconferma tacita .+!m',
			'<del>$0</del>',
			$content
		);

		$newContent = preg_replace(
			'/<!-- SEZIONE DA UTILIZZARE PER L\'EVENTUALE VOTAZIONE DI RICONFERMA.*\n/',
			'',
			$newContent
		);

		$newContent = preg_replace(
			'!(==== *Favorevoli alla riconferma *====\n\#[\s.]+)\n-->!',
			'$1',
			$newContent
		);

		$params = [
			'title' => $title,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'vote-start-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Update [[WP:Wikipediano/Votazioni]]
	 *
	 * @param string[] $titles
	 * @see ClosePages::updateVote()
	 * @see UpdatesAround::addVote()
	 */
	protected function updateVotePage( array $titles ) {
		$votePage = $this->getConfig()->get( 'ric-vote-page' );
		$content = $this->getController()->getPageContent( $votePage );

		$titleReg = implode( '|', array_map( 'preg_quote', $titles ) );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

		$newContent = preg_replace( $search, '', $content );
		// Make sure the last line ends with a full stop
		$sectionReg = '!(^;È in corso.+riconferma tacita.+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $sectionReg, '$1.', $newContent );

		$newLines = '';
		$time = WikiController::getTimeWithArticle( time() + ( 60 * 60 * 24 * 14 ) );
		foreach ( $titles as $title ) {
			$user = explode( '/', $title )[2];
			$newLines .= "*[[Utente:$user|]]. La [[$title|votazione]] termina $time;\n";
		}

		$introReg = '!^Si vota per la \[\[Wikipedia:Amministratori/Riconferma annuale.+!m';
		if ( preg_match( $introReg, strip_tags( $content ) ) ) {
			// Put before the existing ones, if they're found outside comments
			$newContent = preg_replace( $introReg, '$0' . "\n$newLines", $newContent, 1 );
		} else {
			// Start section
			$matches = [];
			if ( preg_match( $introReg, $content, $matches ) === false ) {
				throw new TaskException( 'Intro not found in vote page' );
			}
			$beforeReg = '!INSERIRE LA NOTIZIA PIÙ NUOVA IN CIMA.+!m';
			// Replace semicolon with full stop
			$newLines = substr( $newLines, 0, -2 ) . ".\n";
			$newContent = preg_replace( $beforeReg, '$0' . "\n{$matches[0]}\n$newLines", $newContent, 1 );
		}

		$summary = strtr(
			$this->getConfig()->get( 'vote-start-vote-page-summary' ),
			[ '$num' => count( $titles ) ]
		);
		$summary = preg_replace_callback(
			'!\{\{$plur|(\d+)|([^|]+)|([^|]+)}}!',
			function ( $matches ) {
				return intval( $matches[1] ) > 1 ? trim( $matches[3] ) : trim( $matches[2] );
			},
			$summary
		);

		$params = [
			'title' => $votePage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Template:VotazioniRCnews
	 *
	 * @param int $amount Of pages to move
	 * @see UpdatesAround::addNews()
	 * @see ClosePages::updateNews()
	 */
	protected function updateNews( int $amount ) {
		$this->getLogger()->info( "Turning $amount pages into votes" );
		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$regTac = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';
		$regVot = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d+)!';

		$tacMatches = $votMatches = [];
		if ( preg_match( $regTac, $content, $tacMatches ) === false ) {
			throw new TaskException( 'Param "tacite" not found in news page' );
		}
		if ( preg_match( $regVot, $content, $votMatches ) === false ) {
			throw new TaskException( 'Param "voto" not found in news page' );
		}

		$newTac = (int)$tacMatches[2] - $amount;
		$newVot = (int)$votMatches[2] + $amount;

		$newContent = preg_replace( $regTac, '${1}' . $newTac, $content );
		$newContent = preg_replace( $regVot, '${1}' . $newVot, $newContent );

		$summary = strtr(
			$this->getConfig()->get( 'vote-start-news-page-summary' ),
			[ '$num' => $amount ]
		);
		$summary = preg_replace_callback(
			'!\{\{$plur|(\d+)|([^|]+)|([^|]+)}}!',
			function ( $matches ) {
				return intval( $matches[1] ) > 1 ? trim( $matches[3] ) : trim( $matches[2] );
			},
			$summary
		);

		$params = [
			'title' => $newsPage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}
}
