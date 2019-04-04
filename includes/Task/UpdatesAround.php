<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Exception\TaskException;

class UpdatesAround extends Task {
	/**
	 * @inheritDoc
	 */
	public function run() : TaskResult {
		$this->getLogger()->info( 'Starting task UpdatesAround' );

		foreach ( $this->getDataProvider()->getCreatedPages() as $page ) {
			// Wikipedia:Amministratori/Riconferma annuale
			$this->addToMainPage( $page );
			// WP:Wikipediano/Votazioni
			$this->addVote( $page );
			// Template:VotazioniRCnews
			$this->addNews( $page );
		}

		$this->getLogger()->info( 'Task UpdatesAround completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * @param string $page
	 */
	protected function addToMainPage( string $page ) {
		$this->getLogger()->info( "Adding $page to main" );

		$params = [
			'title' => $this->getConfig()->get( 'ric-main-page' ),
			'appendtext' => '{{' . $page . '}}',
			'summary' => $this->getConfig()->get( 'ric-main-page-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @param string $page
	 */
	protected function addVote( string $page ) {
		$this->getLogger()->info( "Adding $page to votes" );
		$votePage = $this->getConfig()->get( 'ric-vote-page' );

		$content = $this->getController()->getPageContent( $votePage );
		// Remove comments etc.
		$visibleContent = strip_tags( $content );
		$user = explode( '/', $page )[2];
		$time = $this->getTimeWithArticle();

		$newLine = "*[[Utente:$user|]]. La [[$page|procedura]] termina $time";

		$introReg = '!^;È in corso la .*riconferma tacita.* degli .*amministratori.+!m';
		if ( preg_match( $introReg, $visibleContent ) ) {
			$newContent = preg_replace( $introReg, '$0' . "\n$newLine;", $content, 1 );
		} else {
			$matches = [];
			if ( preg_match( $introReg, $content, $matches ) === false ) {
				throw new TaskException( 'Intro not found in vote page' );
			}
			$beforeReg = '!INSERIRE LA NOTIZIA PIÙ NUOVA IN CIMA.+!m';
			$newContent = preg_replace( $beforeReg, "$0\n{$matches[0]}\n$newLine.", $content, 1 );
		}

		$params = [
			'title' => $votePage,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'ric-vote-page-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * @return string
	 */
	private function getTimeWithArticle() : string {
		$oldLoc = setlocale( LC_TIME, 'it_IT', 'Italian_Italy', 'Italian' );
		$endTS = time() + ( 60 * 60 * 24 * 7 );
		$endTime = strftime( '%e %B alle %R', $endTS );
		// Remove the left space if day has a single digit
		$endTime = ltrim( $endTime );
		$artic = in_array( date( 'j', $endTS ), [ 8, 11 ] ) ? "l'" : "il ";
		setlocale( LC_TIME, $oldLoc );

		return $artic . $endTime;
	}

	/**
	 * @param string $page
	 */
	protected function addNews( string $page ) {
		$this->getLogger()->info( "Adding $page to news" );
		$newsPage = $this->getConfig()->get( 'ric-news-page' );

		$content = $this->getController()->getPageContent( $newsPage );
		$reg = '!(\| *riconferme[ _]tacite[ _]amministratori *= *)(\d+)!';

		$matches = [];
		if ( preg_match( $reg, $content, $matches ) === false ) {
			throw new TaskException( 'Param not found in news page' );
		}

		$newNum = (int)$matches[2] + 1;
		$newContent = preg_replace( $reg, '${1}' . $newNum, $content );

		$params = [
			'title' => $newsPage,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'ric-news-page-summary' )
		];

		$this->getController()->editPage( $params );
	}
}
