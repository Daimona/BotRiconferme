<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\PageRiconferma;
use BotRiconferme\TaskResult;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\WikiController;

/**
 * Do some updates around to notify people of the newly created pages
 */
class UpdatesAround extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UpdatesAround' );

		$pages = $this->getDataProvider()->getCreatedPages();
		if ( $pages ) {
			// Wikipedia:Amministratori/Riconferma annuale
			$this->addToMainPage( $pages );
			// WP:Wikipediano/Votazioni
			$this->addVote( $pages );
			// Template:VotazioniRCnews
			$this->addNews( count( $pages ) );
		} else {
			$this->getLogger()->info( 'No updates to do.' );
		}

		$this->getLogger()->info( 'Task UpdatesAround completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Add created pages to Wikipedia:Amministratori/Riconferma annuale
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToMainPage( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to main: ' . implode( ', ', array_map( 'strval', $pages ) )
		);

		$append = '';
		foreach ( $pages as $page ) {
			$append .= '{{' . $page->getTitle() . "}}\n";
		}

		$summary = $this->msg( 'ric-main-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$params = [
			'title' => $this->getConfig()->get( 'ric-main-page' ),
			'appendtext' => $append,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Add a line in Wikipedia:Wikipediano/Votazioni
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addVote( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to votes: ' . implode( ', ', array_map( 'strval', $pages ) )
		);
		$votePage = $this->getConfig()->get( 'ric-vote-page' );

		$content = $this->getController()->getPageContent( $votePage );

		$time = WikiController::getTimeWithArticle( time() + ( 60 * 60 * 24 * 7 ) );
		$newLines = '';
		foreach ( $pages as $page ) {
			$newLines .= '*[[Utente:' . $page->getUser() . '|]]. ' .
				'La [[' . $page->getTitle() . "|procedura]] termina $time;\n";
		}

		$introReg = '!^;È in corso la .*riconferma tacita.* degli .*amministratori.+!m';
		if ( preg_match( $introReg, strip_tags( $content ) ) ) {
			// Put before the existing ones, if they're found outside comments
			$newContent = preg_replace( $introReg, '$0' . "\n$newLines", $content, 1 );
		} else {
			// Start section
			$matches = [];
			if ( preg_match( $introReg, $content, $matches ) === false ) {
				throw new TaskException( 'Intro not found in vote page' );
			}
			$beforeReg = '!INSERIRE LA NOTIZIA PIÙ NUOVA IN CIMA.+!m';
			// Replace semicolon with full stop
			$newLines = substr( $newLines, 0, -2 ) . ".\n";
			$newContent = preg_replace( $beforeReg, '$0' . "\n{$matches[0]}\n$newLines", $content, 1 );
		}

		$summary = $this->msg( 'ric-vote-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$params = [
			'title' => $votePage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Update the counter on Template:VotazioniRCnews
	 *
	 * @param int $amount
	 */
	protected function addNews( int $amount ) {
		$this->getLogger()->info( "Increasing the news counter by $amount" );
		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';

		$matches = [];
		if ( preg_match( $reg, $content, $matches ) === false ) {
			throw new TaskException( 'Param not found in news page' );
		}

		$newNum = (int)$matches[2] + $amount;
		$newContent = preg_replace( $reg, '${1}' . $newNum, $content );

		$summary = $this->msg( 'ric-news-page-summary' )
			->params( [ '$num' => $amount ] )
			->text();

		$params = [
			'title' => $newsPage,
			'text' => $newContent,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}
}
