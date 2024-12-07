<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Subtask;

use BotRiconferme\TaskHelper\TaskResult;
use BotRiconferme\Wiki\Page\PageRiconferma;

/**
 * For each open page, protect it, add a closing text if it was a vote, and
 * update the text in the base page
 */
class ClosePages extends Subtask {
	/**
	 * @inheritDoc
	 */
	public function runInternal(): int {
		$pages = $this->getDataProvider()->getPagesToClose();

		if ( !$pages ) {
			return TaskResult::STATUS_NOTHING;
		}

		$protectReason = $this->msg( 'close-protect-summary' )->text();
		foreach ( $pages as $page ) {
			if ( $page->isVote() ) {
				$this->addVoteCloseText( $page );
			}
			$this->getWiki()->protectPage( $page->getTitle(), $protectReason );
			$this->updateBasePage( $page );
		}

		return TaskResult::STATUS_GOOD;
	}

	protected function addVoteCloseText( PageRiconferma $page ): void {
		$content = $page->getContent();
		$beforeReg = '!è necessario ottenere una maggioranza .+ votanti\.!u';
		$newContent = preg_replace( $beforeReg, '$0' . "\n" . $page->getOutcomeText(), $content );

		$page->edit( [
			'text' => $newContent,
			'summary' => $this->msg( 'close-result-summary' )->text()
		] );
	}

	/**
	 * @see CreatePages::updateBasePage()
	 */
	protected function updateBasePage( PageRiconferma $page ): void {
		$this->getLogger()->info( "Updating base page for $page" );

		if ( $page->getNum() === 1 ) {
			$basePage = $this->getUser( $page->getUserName() )->getBasePage();
		} else {
			$basePage = $this->getUser( $page->getUserName() )->getExistingBasePage();
		}

		$current = $basePage->getContent();

		$outcomeText = ( $page->getOutcome() & PageRiconferma::OUTCOME_FAIL ) ?
			'non riconfermato' :
			'riconfermato';
		$text = $page->isVote() ? "votazione di riconferma: $outcomeText" : 'riconferma tacita';

		$newContent = preg_replace( '/^(#: *)(votazione di )?riconferma in corso/m', '$1' . $text, $current );

		$basePage->edit( [
			'text' => $newContent,
			'summary' => $this->msg( 'close-base-page-summary-update' )->text()
		] );
	}
}
