<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;
use BotRiconferme\CLI;

if ( !CLI::isCLI() ) {
	exit( 'CLI only!' );
}

date_default_timezone_set( 'Europe/Rome' );

$cli = new CLI();

/* URL (for debugging purpose) */
$url = $cli->getURL() ?? 'https://it.wikipedia.org/w/api.php';

define( 'DEFAULT_URL', $url );
define( 'META_URL', 'https://meta.wikimedia.org/w/api.php' );

/* START */

$errTitle = $cli->getOpt( 'error-title' );

$simpleLogger = new \BotRiconferme\Logger\SimpleLogger();
// @fixme
$wiki = new \BotRiconferme\Wiki\Wiki( $simpleLogger );
Config::init( $cli->getMainOpts(), $wiki );

if ( $errTitle !== null ) {
	// Use a different Wiki with higher min level.
	$wikiLoggerLogger = new \BotRiconferme\Logger\SimpleLogger( \Psr\Log\LogLevel::ERROR );
	$wikiLoggerWiki = new \BotRiconferme\Wiki\Wiki( $wikiLoggerLogger );
	$errPage = new \BotRiconferme\Wiki\Page\Page( $errTitle, $wikiLoggerWiki );
	$wikiLogger = new \BotRiconferme\Logger\WikiLogger( $errPage, \Psr\Log\LogLevel::ERROR );
	$mainLogger = new \BotRiconferme\Logger\MultiLogger( $simpleLogger, $wikiLogger );
} else {
	$mainLogger = $simpleLogger;
}

$errorHandler = function ( $errno, $errstr, $errfile, $errline ) {
	throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
};
// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
set_error_handler( $errorHandler );

$bot = new Bot( $mainLogger, $wiki );
$taskOpt = $cli->getTaskOpt();
$type = current( array_keys( $taskOpt ) );
try {
	if ( $type === 'task' ) {
		$bot->runTask( $taskOpt['task'] );
	} elseif ( $type === 'subtask' ) {
		$bot->runSubtask( $taskOpt['subtask'] );
	} else {
		$bot->runAll();
	}
} catch ( Throwable $e ) {
	$mainLogger->error( "$e" );
} finally {
	$mainLogger->flush();
}
