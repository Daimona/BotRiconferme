<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Exception\TaskException;
use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
use BotRiconferme\TaskResult;
use BotRiconferme\WikiController;

/**
 * Start a vote if there are >= 15 opposing comments
 */
class StartVote extends Task {
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
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task StartVote' );

		$pages = $this->getDataProvider()->getOpenPages();

		if ( $pages ) {
			$this->processPages( $pages );
		} else {
			$this->getLogger()->info( 'No open procedures.' );
		}

		$this->getLogger()->info( 'Task StartVote completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param PageRiconferma[] $pages
	 */
	protected function processPages( array $pages ) {
		$actualPages = [];
		foreach ( $pages as $page ) {
			if ( $page->hasOpposition() && !$page->isVote() ) {
				$this->openVote( $page );
				$actualPages[] = $page;
			}
		}

		if ( $actualPages ) {
			$this->updateVotePage( $actualPages );
			$this->updateNews( count( $actualPages ) );
		} else {
			$this->getLogger()->info( 'No votes to open' );
		}
	}

	/**
	 * Start the vote for the given page
	 *
	 * @param PageRiconferma $page
	 */
	protected function openVote( PageRiconferma $page ) {
		$this->getLogger()->info( "Starting vote on $page" );

		$content = $page->getContent();

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
			'!(==== *Favorevoli alla riconferma *====\n\#[\s.]+|maggioranza di.+ dei votanti\.)\n-->!',
			'$1',
			$newContent,
			2
		);

		$params = [
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'vote-start-summary' )
		];

		$page->edit( $params );
	}

	/**
	 * Update [[WP:Wikipediano/Votazioni]]
	 *
	 * @param PageRiconferma[] $pages
	 * @see SimpleUpdates::updateVote()
	 * @see UpdatesAround::addVote()
	 */
	protected function updateVotePage( array $pages ) {
		$votePage = new Page( $this->getConfig()->get( 'ric-vote-page' ) );
		$content = $votePage->getContent();

		$titles = [];
		foreach ( $pages as $page ) {
			$titles[] = preg_quote( $page->getTitle() );
		}
		$titleReg = implode( '|', $titles );
		$search = "!^\*.+ La \[\[($titleReg)\|procedura]] termina.+\n!gm";

		$newContent = preg_replace( $search, '', $content );
		// Make sure the last line ends with a full stop
		$sectionReg = '!(^;È in corso.+riconferma tacita.+amministratori.+\n(?:\*.+[;\.]\n)+\*.+)[\.;]!m';
		$newContent = preg_replace( $sectionReg, '$1.', $newContent );

		$newLines = '';
		$time = WikiController::getTimeWithArticle( time() + ( 60 * 60 * 24 * 14 ) );
		foreach ( $pages as $page ) {
			$newLines .= '*[[Utente:' . $page->getUser() . '|]]. ' .
				'La [[' . $page->getTitle() . "|votazione]] termina $time;\n";
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

		$summary = $this->msg( 'vote-start-vote-page-summary' )
			->params( [ '$num' => count( $pages ) ] )
			->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];

		$votePage->edit( $params );
	}

	/**
	 * Template:VotazioniRCnews
	 *
	 * @param int $amount Of pages to move
	 * @see UpdatesAround::addNews()
	 * @see SimpleUpdates::updateNews()
	 */
	protected function updateNews( int $amount ) {
		$this->getLogger()->info( "Turning $amount pages into votes" );
		$newsPage = new Page( $this->getConfig()->get( 'ric-news-page' ) );

		$content = $newsPage->getContent();
		$regTac = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';
		$regVot = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d+)!';

		$tacMatches = $votMatches = [];
		if ( preg_match( $regTac, $content, $tacMatches ) === false ) {
			throw new TaskException( 'Param "tacite" not found in news page' );
		}
		if ( preg_match( $regVot, $content, $votMatches ) === false ) {
			throw new TaskException( 'Param "voto" not found in news page' );
		}

		$newTac = (int)$tacMatches[2] - $amount ?: '';
		$newVot = (int)$votMatches[2] + $amount ?: '';

		$newContent = preg_replace( $regTac, '${1}' . $newTac, $content );
		$newContent = preg_replace( $regVot, '${1}' . $newVot, $newContent );

		$summary = $this->msg( 'vote-start-news-page-summary' )
			->params( [ '$num' => $amount ] )
			->text();

		$params = [
			'text' => $newContent,
			'summary' => $summary
		];

		$newsPage->edit( $params );
	}
}
