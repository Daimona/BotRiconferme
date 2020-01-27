<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Bot;
use BotRiconferme\CLI;
use BotRiconferme\Config;
use BotRiconferme\Exception\MissingPageException;
use BotRiconferme\MessageProvider;

if ( !CLI::isCLI() ) {
	exit( 'CLI only!' );
}

define( 'BOT_VERSION', '2.1' );

date_default_timezone_set( 'Europe/Rome' );

$cli = new CLI();

$url = $cli->getURL() ?? 'https://it.wikipedia.org/w/api.php';
$localUserIdentifier = '@itwiki';
$centralURL = 'https://meta.wikimedia.org/w/api.php';

/* START */

$errTitle = $cli->getOpt( 'error-title' );

$simpleLogger = new \BotRiconferme\Logger\SimpleLogger();
$loginInfo = new \BotRiconferme\Wiki\LoginInfo(
	$cli->getOpt( 'username' ),
	$cli->getOpt( 'password' )
);
// @fixme A bit of dependency hell here
$rf = new \BotRiconferme\Request\RequestFactory( $url );
$wiki = new \BotRiconferme\Wiki\Wiki( $loginInfo, $simpleLogger, $rf );
$centralRF = new \BotRiconferme\Request\RequestFactory( $centralURL );
$centralWiki = new \BotRiconferme\Wiki\Wiki( $loginInfo, $simpleLogger, $centralRF );
$centralWiki->setLocalUserIdentifier( $localUserIdentifier );

try {
	$confValues = json_decode( $wiki->getPageContent( $cli->getOpt( 'config-title' ) ), true );
} catch ( MissingPageException $_ ) {
	exit( 'Please create a config page.' );
}

Config::init( $confValues );
$mp = new MessageProvider( $wiki, $cli->getOpt( 'msg-title' ) );

$wiki->setEditsAsBot( Config::getInstance()->get( 'bot-edits' ) );
$centralWiki->setEditsAsBot( Config::getInstance()->get( 'bot-edits' ) );

if ( $errTitle !== null ) {
	// Use a different Wiki with higher min level.
	$wikiLoggerLogger = new \BotRiconferme\Logger\SimpleLogger( \Psr\Log\LogLevel::ERROR );
	$wikiLoggerWiki = new \BotRiconferme\Wiki\Wiki( $loginInfo, $wikiLoggerLogger, $rf );
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

$wikiGroup = new \BotRiconferme\Wiki\WikiGroup( $wiki, $centralWiki );

$bot = new Bot(
	$mainLogger,
	$wikiGroup,
	$mp,
	\BotRiconferme\Wiki\Page\PageBotList::get( $wiki, $cli->getOpt( 'list-title' ) )
);
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
