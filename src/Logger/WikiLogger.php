<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use BotRiconferme\Message\MessageProvider;
use BotRiconferme\Wiki\Page\Page;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Logger that sends messages on-wiki
 */
class WikiLogger extends AbstractLogger implements IFlushingAwareLogger {
	use LoggerTrait;

	private readonly int $minLevel;

	/** @var string[] */
	private array $buffer = [];

	public function __construct(
		private readonly MessageProvider $messageProvider,
		private readonly Page $logPage,
		string $minlevel = LogLevel::INFO
	) {
		$this->minLevel = $this->levelToInt( $minlevel );
	}

	/**
	 * @inheritDoc
	 * @phan-param mixed[] $context
	 */
	public function log( $level, string|Stringable $message, array $context = [] ): void {
		if ( $this->levelToInt( $level ) >= $this->minLevel ) {
			$this->buffer[] = $this->getFormattedMessage( $level, $message );
		}
	}

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
