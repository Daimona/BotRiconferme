<?php declare( strict_types=1 );

namespace BotRiconferme\Request\Exception;

/**
 * Used by action=block when the target is already blocked
 */
class AlreadyBlockedException extends APIRequestException {
}
