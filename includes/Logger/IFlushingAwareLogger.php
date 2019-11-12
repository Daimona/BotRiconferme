<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

use Psr\Log\LoggerInterface;

/**
 * Logger aware of flushing. Can be declared empty if the buffer is flushed immediately.
 */
interface IFlushingAwareLogger extends LoggerInterface {
	public function flush() : void;
}
