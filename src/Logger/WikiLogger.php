<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use BotRiconferme\MessageProvider;
use BotRiconferme\Wiki\Page\Page;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Logger that sends messages on-wiki
 */
class WikiLogger extends AbstractLogger implements IFlushingAwareLogger {
	use LoggerTrait;

	private MessageProvider $messageProvider;
	private int $minLevel;
	private Page $logPage;

	/** @var string[] */
	private array $buffer = [];

	public function __construct( MessageProvider $messageProvider, Page $logPage, string $minlevel = LogLevel::INFO ) {
		$this->messageProvider = $messageProvider;
		$this->minLevel = $this->levelToInt( $minlevel );
		$this->logPage = $logPage;
	}

	/**
	 * @inheritDoc
	 * @param mixed[] $context
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function log( $level, string|Stringable $message, array $context = [] ): void {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			$this->buffer[] = $this->getFormattedMessage( $level, $message );
		}
	}

	/**
	 * @return string
	 */
	protected function getOutput(): string {
		$line = str_repeat( '-', 80 );
		return "\n\n" . implode( "\n", $this->buffer ) . "\n$line\n\n";
	}

	/**
	 * @inheritDoc
	 */
	public function flush(): void {
		if ( $this->buffer ) {
			$this->logPage->edit( [
				'appendtext' => $this->getOutput(),
				'summary' => $this->messageProvider->getMessage( 'error-page-summary' )->text()
			] );
		}
	}
}
