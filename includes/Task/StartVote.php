<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\Exception\TaskException;
use BotRiconferme\Task\Subtask\ClosePages;
use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Utils\RegexUtils;
use BotRiconferme\Wiki\Page\PageRiconferma;

/**
 * Start a vote if there are >= PageRiconferma::REQUIRED_OPPOSE opposing comments
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
	public function runInternal() : int {
		$pages = $this->getDataProvider()->getOpenPages();

		if ( !$pages ) {
			return TaskResult::STATUS_NOTHING;
		}

		return $this->processPages( $pages );
	}

	/**
	 * @param PageRiconferma[] $pages
	 * @return int a STATUS_* constant
	 */
	protected function processPages( array $pages ) : int {
		$donePages = [];
		foreach ( $pages as $page ) {
			if ( $page->hasOpposition() && !$page->isVote() ) {
				$this->openVote( $page );
				$donePages[] = $page;
			}
		}

		if ( !$donePages ) {
			return TaskResult::STATUS_NOTHING;
		}

		$this->updateVotazioni( $donePages );
		$this->updateNews( count( $donePages ) );
		return TaskResult::STATUS_GOOD;
	}

	/**
	 * Start the vote for the given page
	 *
	 * @param PageRiconferma $page
	 */
	protected function openVote( PageRiconferma $page ) : void {
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
			'!(==== *Favorevoli alla riconferma *====\n#[\s.]+|maggioranza di.+ dei votanti\.)\n-->!',
			'$1',
			$newContent,
			2
		);

		$params = [
			'text' => $newContent,
			'summary' => $this->msg( 'vote-start-summary' )->text()
		];

		$page->edit( $params );
	}

	/**
	 * Update [[WP:Wikipediano/Votazioni]]
	 *
	 * @param PageRiconferma[] $pages
	 * @see SimpleUpdates::updateVotazioni()
	 * @see OpenUpdates::addToVotazioni()
	 */
	protected function updateVotazioni( array $pages ) : void {
		$votePage = $this->getPage( $this->getOpt( 'vote-page-title' ) );

		$users = [];
		foreach ( $pages as $page ) {
			$users[] = $this->getUser( $page->getUserName() );
		}
		$usersReg = RegexUtils::regexFromArray( '!', ...$users );

		$search = "!^.+\{\{[^|}]*\/Riga\|riconferma tacita\|utente=$usersReg\|.+\n!m";

		$newContent = preg_replace( $search, '', $votePage->getContent() );

		$newLines = '';
		$endDays = PageRiconferma::VOTE_DURATION;
		foreach ( $pages as $page ) {
			$newLines .= '{{subst:Wikipedia:Wikipediano/Votazioni/RigaCompleta|votazione riconferma' .
				'|utente=' . $page->getUserName() . '|numero=' . $page->getNum() . '|giorno=' .
				"{{subst:#timel:j F|+ $endDays days}}|ore={{subst:LOCALTIME}}}}\n";
		}

		$newContent = preg_replace( '!\|votazioni[ _]riconferma *= *\n!', '$0' . $newLines, $newContent );

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
	 * @param PageRiconferma $page
	 * @see ClosePages::updateBasePage()
	 */
	protected function updateBasePage( PageRiconferma $page ) : void {
		$this->getLogger()->info( "Updating base page for $page" );

		if ( $page->getNum() === 1 ) {
			$basePage = $this->getUser( $page->getUserName() )->getBasePage();
		} else {
			$basePage = $this->getUser( $page->getUserName() )->getExistingBasePage();
		}

		$current = $basePage->getContent();

		$newContent = preg_replace( '/^riconferma in corso/m', 'votazione di riconferma in corso', $current );

		$basePage->edit( [
			'text' => $newContent,
			'summary' => $this->msg( 'close-base-page-summary-update' )->text()
		] );
	}

	/**
	 * Template:VotazioniRCnews
	 *
	 * @param int $amount Of pages to move
	 * @throws TaskException
	 * @see SimpleUpdates::updateNews()
	 * @see OpenUpdates::addToNews()
	 */
	protected function updateNews( int $amount ) : void {
		$this->getLogger()->info( "Turning $amount pages into votes" );
		$newsPage = $this->getPage( $this->getOpt( 'news-page-title' ) );

		$content = $newsPage->getContent();
		$regTac = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d*)(?=\s*[}|])!';
		$regVot = '!(\| *riconferme[ _]voto[ _]amministratori *= *)(\d*)(?=\s*[}|])!';

		if ( !$newsPage->matches( $regTac ) ) {
			throw new TaskException( 'Param "tacite" not found in news page' );
		}
		if ( !$newsPage->matches( $regVot ) ) {
			throw new TaskException( 'Param "voto" not found in news page' );
		}

		$newTac = ( (int)$newsPage->getMatch( $regTac )[2] - $amount ) ?: '';
		$newVot = ( (int)$newsPage->getMatch( $regVot )[2] + $amount ) ?: '';

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
