<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
set_error_handler( static function ( $errno, $errstr, $errfile, $errline ) {
	throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
} );

require __DIR__ . '/vendor/autoload.php';

if ( !\BotRiconferme\CLI::isCLI() ) {
	exit( 'CLI only!' );
}

define( 'BOT_VERSION', '2.2' );
// TODO make this configurable?
define( 'BOT_EDITS', false );

date_default_timezone_set( 'Europe/Rome' );

$cli = new \BotRiconferme\CLI();
$bot = new \BotRiconferme\Bot( $cli );
$bot->run();
