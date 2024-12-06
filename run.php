<?php declare( strict_types=1 );

use BotRiconferme\Bot;
use BotRiconferme\CLI;

/**
 * Entry point for the bot, called by CLI
 */

set_error_handler(
	/**
	 * @throws ErrorException
	 */
	static function ( int $errno, string $errstr, string $errfile, int $errline ): never {
		throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
	}
);

require __DIR__ . '/vendor/autoload.php';

if ( !CLI::isCLI() ) {
	exit( 'CLI only!' );
}

const BOT_VERSION = '3.0';
// TODO make this configurable?
const BOT_EDITS = false;

date_default_timezone_set( 'Europe/Rome' );

$cli = new CLI();
$bot = new Bot( $cli );
$bot->run();
