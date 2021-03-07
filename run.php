<?php declare( strict_types=1 );

use BotRiconferme\Bot;
use BotRiconferme\CLI;

/**
 * Entry point for the bot, called by CLI
 */

// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
set_error_handler( static function ( int $errno, string $errstr, string $errfile, int $errline ) {
	throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
} );

require __DIR__ . '/vendor/autoload.php';

if ( !CLI::isCLI() ) {
	exit( 'CLI only!' );
}

define( 'BOT_VERSION', '2.2' );
// TODO make this configurable?
define( 'BOT_EDITS', false );

date_default_timezone_set( 'Europe/Rome' );

$cli = new CLI();
$bot = new Bot( $cli );
$bot->run();
