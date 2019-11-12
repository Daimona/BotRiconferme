<?php declare( strict_types=1 );

namespace BotRiconferme\Logger;

/**
 * Logger aware of flushing. Can be declared empty if the buffer is flushed immediately.
 */
interface IFlushingAwareLogger {
	public function flush() : void;
}
