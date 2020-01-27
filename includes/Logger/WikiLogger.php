<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use BotRiconferme\Wiki\Page\Page;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Logger that sends messages on-wiki
 */
class WikiLogger extends AbstractLogger implements IFlushingAwareLogger {
	use LoggerTrait;

	/** @var Page */
	private $logPage;

	/** @var string */
	private $summary;

	/** @var int */
	private $minLevel;

	/** @var string[] */
	private $buffer;

	/**
	 * @param Page $logPage
	 * @param string $summary
	 * @param string $minlevel
	 */
	public function __construct( Page $logPage, string $summary, $minlevel = LogLevel::INFO ) {
		$this->minLevel = $this->levelToInt( $minlevel );
		$this->logPage = $logPage;
		$this->summary = $summary;
	}

	/**
	 * @inheritDoc
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, $message, array $context = [] ) :void {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			$this->buffer[] = $this->getFormattedMessage( $level, $message );
		}
	}

	/**
	 * @return string
	 */
	protected function getOutput() : string {
		$line = str_repeat( '-', 80 );
		return "\n\n" . implode( "\n", $this->buffer ) . "\n$line\n\n";
	}

	/**
	 * @inheritDoc
	 */
	public function flush() : void {
		if ( $this->buffer ) {
			$this->logPage->edit( [
				'appendtext' => $this->getOutput(),
				'summary' => $this->summary
			] );
		}
	}
}
