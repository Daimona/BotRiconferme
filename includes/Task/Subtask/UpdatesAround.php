<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Message;
use BotRiconferme\Wiki\Page\Page;
use BotRiconferme\Wiki\Page\PageRiconferma;
use BotRiconferme\Exception\TaskException;
use BotRiconferme\TaskResult;

/**
 * Do some updates around to notify people of the newly created pages
 */
class UpdatesAround extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$pages = $this->getDataProvider()->getCreatedPages();

		if ( !$pages ) {
			return TaskResult::STATUS_NOTHING;
		}

		// Wikipedia:Amministratori/Riconferma annuale
		$this->addToMainPage( $pages );
		// WP:Wikipediano/Votazioni
		$this->addToVotazioni( $pages );
		// Template:VotazioniRCnews
		$this->addToNews( count( $pages ) );

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Add created pages to Wikipedia:Amministratori/Riconferma annuale
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToMainPage( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to main: ' . implode( ', ', $pages )
		);

		$append = "\n";
		foreach ( $pages as $page ) {
			$append .= '{{' . $page->getTitle() . "}}\n";
		}

		$summary = $this->msg( 'main-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$params = [
			'title' => $this->getConfig()->get( 'main-page-title' ),
			'appendtext' => $append,
			'summary' => $summary
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Add a line in Wikipedia:Wikipediano/Votazioni
	 *
	 * @param PageRiconferma[] $pages
	 * @throws TaskException
	 */
	protected function addToVotazioni( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to votes: ' . implode( ', ', $pages )
		);
		$votePage = new Page( $this->getConfig()->get( 'vote-page-title' ) );

		$content = $votePage->getContent();

		$time = Message::getTimeWithArticle( time() + ( 3600 * 24 * PageRiconferma::SIMPLE_DURATION ) );
		$newLines = '';
		foreach ( $pages as $page ) {
			$newLines .= "\n*[[Utente:" . $page->getUser() . '|]]. ' .
				'La [[' . $page->getTitle() . "|procedura]] termina $time;\n";
		}

		$introReg = '!^;È in corso la .*riconferma tacita.* degli .*amministratori.+!m';
		if ( preg_match( $introReg, strip_tags( $content ) ) ) {
			// Put before the existing ones, if they're found outside comments
			$newContent = preg_replace( $introReg, '$0' . $newLines, $content, 1 );
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

		$summary = $this->msg( 'vote-page-summary' )
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
	 * @throws TaskException
	 */
	protected function addToNews( int $amount ) {
		$this->getLogger()->info( "Increasing the news counter by $amount" );
		$newsPage = new Page( $this->getConfig()->get( 'news-page-title' ) );

		$content = $newsPage->getContent();
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d*)(?=\s*[}|])!';

		try {
			$matches = $newsPage->getMatch( $reg );
		} catch ( \Exception $e ) {
			throw new TaskException( 'Param not found in news page' );
		}

		$newNum = (int)$matches[2] + $amount;
		$newContent = preg_replace( $reg, '${1}' . $newNum, $content );

		$summary = $this->msg( 'news-page-summary' )
			->params( [ '$num' => $amount ] )
			->text();

		$newsPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}
}
