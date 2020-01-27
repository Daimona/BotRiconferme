<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Bot;
use BotRiconferme\CLI;
use BotRiconferme\Config;
use BotRiconferme\MessageProvider;

if ( !CLI::isCLI() ) {
	exit( 'CLI only!' );
}

define( 'BOT_VERSION', '2.1' );

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
$mp = new MessageProvider( $wiki, Config::getInstance()->get( 'msg-title' ) );
$loginInfo = new \BotRiconferme\Wiki\LoginInfo(
	Config::getInstance()->get( 'username' ),
	Config::getInstance()->get( 'password' )
);
$wiki->setLoginInfo( $loginInfo );
$wiki->setEditsAsBot( Config::getInstance()->get( 'bot-edits' ) );

if ( $errTitle !== null ) {
	// Use a different Wiki with higher min level.
	$wikiLoggerLogger = new \BotRiconferme\Logger\SimpleLogger( \Psr\Log\LogLevel::ERROR );
	$wikiLoggerWiki = new \BotRiconferme\Wiki\Wiki( $wikiLoggerLogger );
	$wikiLoggerWiki->setLoginInfo( $loginInfo );
	$wikiLoggerWiki->setEditsAsBot( Config::getInstance()->get( 'bot-edits' ) );
	$errPage = new \BotRiconferme\Wiki\Page\Page( $errTitle, $wikiLoggerWiki );
	$wikiLogger = new \BotRiconferme\Logger\WikiLogger(
		$errPage,
		$mp->getMessage( 'error-page-summary' )->text(),
		\Psr\Log\LogLevel::ERROR
	);
	$mainLogger = new \BotRiconferme\Logger\MultiLogger( $simpleLogger, $wikiLogger );
} else {
	$mainLogger = $simpleLogger;
}

$errorHandler = function ( $errno, $errstr, $errfile, $errline ) {
	throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
};
// @phan-suppress-next-line PhanTypeMismatchArgumentInternal
set_error_handler( $errorHandler );

$bot = new Bot( $mainLogger, $wiki, $mp );
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
