<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\Page\Page;
use BotRiconferme\Page\PageRiconferma;
use BotRiconferme\TaskResult;

/**
 * For each open page, protect it, add a closing text if it was a vote, and
 * update the text in the base page
 */
class ClosePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal() : int {
		$pages = $this->getDataProvider()->getPagesToClose();
		$protectReason = $this->msg( 'close-protect-summary' )->text();
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$this->addVoteCloseText( $page );
			}
			$this->getController()->protectPage( $page->getTitle(), $protectReason );
			$this->updateBasePage( $page );
		}

		return TaskResult::STATUS_GOOD;
	}

	/**
	 * @param PageRiconferma $page
	 */
	protected function addVoteCloseText( PageRiconferma $page ) {
		$content = $page->getContent();
		$beforeReg = '!è necessario ottenere una maggioranza .+ votanti\.!';
		$newContent = preg_replace( $beforeReg, '$0' . "\n" . $page->getOutcomeText(), $content );

		$page->edit( [
			'text' => $newContent,
			'summary' => $this->msg( 'close-result-summary' )->text()
		] );
	}

	/**
	 * @param PageRiconferma $page
	 * @see CreatePages::updateBasePage()
	 */
	protected function updateBasePage( PageRiconferma $page ) {
		$this->getLogger()->info( "Updating base page for $page" );

		$basePage = new Page( $page->getBaseTitle() );
		$current = $basePage->getContent();

		$outcomeText = $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ?
			'non riconfermato' :
			'riconfermato';
		$text = $page->isVote() ? "votazione: $outcomeText" : 'riconferma tacita';

		$newContent = str_replace( 'riconferma in corso', $text, $current );

		$basePage->edit( [
			'text' => $newContent,
			'summary' => $this->msg( 'close-base-page-summary-update' )->text()
		] );
	}
}
