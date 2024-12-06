<?php declare( strict_types=1 );

namespace BotRiconferme\Wiki\Page\Exception;

use RuntimeException;

/**
 * Exception thrown when a page doesn't match the given content
 */
class MissingMatchException extends RuntimeException {
}
