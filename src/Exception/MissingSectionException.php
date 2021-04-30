<?php declare( strict_types=1 );

namespace BotRiconferme\Exception;

/**
 * Error thrown when trying to read a non-existent section
 */
class MissingSectionException extends APIRequestException {
	/**
	 * @param string $title
	 * @param int|string $section Number or title
	 */
	public function __construct( string $title = '[unavailable]', $section = '[unavailable]' ) {
		parent::__construct( "The section $section in the page $title doesn't exist." );
	}
}
