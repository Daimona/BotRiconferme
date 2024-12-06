<?php declare( strict_types=1 );

namespace BotRiconferme\Task\Exception;

use Exception;

/**
 * Thrown when a confirmation page has already been created today.
 */
class PageCreatedTodayException extends Exception {
}
