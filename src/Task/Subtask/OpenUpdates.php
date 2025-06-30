<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\TaskHelper\Status;
use BotRiconferme\Wiki\Page\PageRiconferma;
use RuntimeException;

/**
 * Do some updates around to notify people of the newly created pages
 */
class OpenUpdates extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): Status {
		$pages = $this->getDataProvider()->getCreatedPages();

		if ( !$pages ) {
			return Status::NOTHING;
		}

		// Wikipedia:Amministratori/Riconferma annuale
		$this->addToMainPage( $pages );
		// WP:Wikipediano/Votazioni
		$this->addToVotazioni( $pages );
		// Template:VotazioniRCnews
		$this->addToNews( count( $pages ) );

		return Status::GOOD;
	}

	/**
	 * Add created pages to Wikipedia:Amministratori/Riconferma annuale
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToMainPage( array $pages ): void {
		$this->getLogger()->info(
			'Adding the following to main: ' . implode( ', ', $pages )
		);

		$mainPage = $this->getPage( $this->getOpt( 'main-page-title' ) );

		$append = "\n";
		foreach ( $pages as $page ) {
			$append .= '{{' . $page->getTitle() . "}}\n";
		}

		$newContent = $mainPage->getContent() . $append;
		$newContent = preg_replace(
			"/^:''Nessuna riconferma in corso\.''/m",
			'<!-- $0 -->',
			$newContent
		);

		$summary = $this->msg( 'main-page-summary' )
			->params( [ '$num' => count( $pages ) ] )->text();

		$mainPage->edit( [
			'text' => $newContent,
			'summary' => $summary
		] );
	}

	/**
	 * Add a line in Wikipedia:Wikipediano/Votazioni
	 *
	 * @param PageRiconferma[] $pages
	 */
	protected function addToVotazioni( array $pages ): void {
		$this->getLogger()->info(
			'Adding the following to votes: ' . implode( ', ', $pages )
		);
		$votePage = $this->getPage( $this->getOpt( 'vote-page-title' ) );

		$endDays = PageRiconferma::SIMPLE_DURATION;
		$newLines = '';
		foreach ( $pages as $page ) {
			$newLines .= '{{subst:Wikipedia:Wikipediano/Votazioni/RigaCompleta|riconferma tacita' .
				'|utente=' . $page->getUserName() . '|numero=' . $page->getNum() . '|giorno=' .
				"{{subst:#timel:j F|+ $endDays days}}|ore={{subst:#timel:H:i|+$endDays DAYS}}}}\n";
		}

		$newContent = preg_replace(
			'!\|riconferme[ _]tacite *= *\n!',
			'$0' . $newLines,
			$votePage->getContent()
		);

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
	 */
	protected function addToNews( int $amount ): void {
		$this->getLogger()->info( "Increasing the news counter by $amount" );
		$newsPage = $this->getPage( $this->getOpt( 'news-page-title' ) );

		$content = $newsPage->getContent();
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d*)(?=\s*[}|])!';

		if ( !$newsPage->matches( $reg ) ) {
			throw new RuntimeException( 'Param not found in news page' );
		}

		$matches = $newsPage->getMatch( $reg );

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
