<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use BotRiconferme\Config;
use BotRiconferme\Wiki\Page\Page;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Logger that sends messages on-wiki
 */
class WikiLogger extends AbstractLogger {
	use LoggerTrait;

	/** @var int */
	private $minLevel;

	/** @var Page */
	private $logPage;

	/** @var string[] */
	private $buffer;

	/**
	 * @param Page $logPage
	 * @param string $minlevel
	 */
	public function __construct( Page $logPage, $minlevel = LogLevel::INFO ) {
		$this->logPage = $logPage;
		$this->minLevel = $this->levelToInt( $minlevel );
	}

	/**
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, $message, array $context = [] ) {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			$this->buffer[] = $this->getFormattedMessage( $level, $message );
		}
	}

	/**
	 * Actually writes data to the wiki
	 */
	public function doOutput() {
		if ( $this->buffer ) {
			$this->logPage->edit( [
				'appendtext' => implode( "\n", $this->buffer ),
				'summary' => Config::getInstance()->getWikiMessage( 'error-page-summary' )
			] );
		}
	}

	/**
	 * @todo Can we move this?
	 */
	public function __destruct() {
		$this->doOutput();
	}
}
