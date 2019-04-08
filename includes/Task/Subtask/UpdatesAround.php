<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
use BotRiconferme\Exception\TaskException;

/**
 * Do some updates around to notify people of the newly created pages
 */
class UpdatesAround extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
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

		return self::STATUS_OK;
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
		$votePage = new Page( $this->getConfig()->get( 'ric-vote-page' ) );

		$content = $votePage->getContent();

		$time = Message::getTimeWithArticle( time() + ( 60 * 60 * 24 * 7 ) );
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
			try {
				$matches = $votePage->getMatch( $introReg );
			} catch ( \Exception $e ) {
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
			'text' => $newContent,
			'summary' => $summary
		];

		$votePage->edit( $params );
	}

	/**
	 * Update the counter on Template:VotazioniRCnews
	 *
	 * @param int $amount
	 */
	protected function addNews( int $amount ) {
		$this->getLogger()->info( "Increasing the news counter by $amount" );
		$newsPage = new Page( $this->getConfig()->get( 'ric-news-page' ) );

		$content = $newsPage->getContent();
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';

		try {
			$matches = $newsPage->getMatch( $reg );
		} catch ( \Exception $e ) {
			throw new TaskException( 'Param not found in news page' );
		}

		$newNum = (int)$matches[2] + $amount;
		$newContent = preg_replace( $reg, '${1}' . $newNum, $content );

		$summary = $this->msg( 'ric-news-page-summary' )
			->params( [ '$num' => $amount ] )
			->text();

		$newsPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}
}
