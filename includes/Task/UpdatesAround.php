<?php declare( strict_types=1 );

namespace BotRiconferme\Task;

use BotRiconferme\TaskResult;
use BotRiconferme\Exception\TaskException;

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
		// Wikipedia:Amministratori/Riconferma annuale
		$this->addToMainPage( $pages );
		// WP:Wikipediano/Votazioni
		$this->addVote( $pages );
		// Template:VotazioniRCnews
		$this->addNews( count( $pages ) );

		$this->getLogger()->info( 'Task UpdatesAround completed successfully' );
		return new TaskResult( self::STATUS_OK );
	}

	/**
	 * Add created pages to Wikipedia:Amministratori/Riconferma annuale
	 *
	 * @param string[] $pages
	 */
	protected function addToMainPage( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to main: ' . implode( ', ', $pages )
		);

		$append = '';
		foreach ( $pages as $page ) {
			$append .= '{{' . $page . "}}\n";
		}

		$params = [
			'title' => $this->getConfig()->get( 'ric-main-page' ),
			'appendtext' => $append,
			'summary' => $this->getConfig()->get( 'ric-main-page-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Add a line in Wikipedia:Wikipediano/Votazioni
	 *
	 * @param string[] $pages
	 */
	protected function addVote( array $pages ) {
		$this->getLogger()->info(
			'Adding the following to votes: ' . implode( ', ', $pages )
		);
		$votePage = $this->getConfig()->get( 'ric-vote-page' );

		$content = $this->getController()->getPageContent( $votePage );

		$time = $this->getTimeWithArticle();
		$newLines = '';
		foreach ( $pages as $page ) {
			$user = explode( '/', $page )[2];
			$newLines .= "*[[Utente:$user|]]. La [[$page|procedura]] termina $time;\n";
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

		$params = [
			'title' => $votePage,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'ric-vote-page-summary' )
		];

		$this->getController()->editPage( $params );
	}

	/**
	 * Get a localized version of article + day + time
	 *
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

		$params = [
			'title' => $newsPage,
			'text' => $newContent,
			'summary' => $this->getConfig()->get( 'ric-news-page-summary' )
		];

		$this->getController()->editPage( $params );
	}
}
