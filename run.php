<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only!' );
}

date_default_timezone_set( 'Europe/Rome' );

/** MAIN PARAMS */

/*
	Example

	'username' => 'BotRiconferme'
	'list-title' => 'Utente:BotRiconferme/List.json',
	'config-title' => 'Utente:BotRiconferme/Config.json',
	'msg-title' => 'Utente:BotRiconferme/Messages.json"
*/

$params = [
	'username:',
	'list-title:',
	'config-title:',
	'msg-title:'
];

$vals = getopt( '', $params );
if ( count( $vals ) !== count( $params ) ) {
	exit( 'Not enough params!' );
}

/* URL (for debugging purpose) */
$urlParam = getopt( '', [ 'force-url:' ] );
$url = $urlParam['force-url'] ?? 'https://it.wikipedia.org/w/api.php';

define( 'DEFAULT_URL', $url );
define( 'META_URL', 'https://meta.wikimedia.org/w/api.php' );

/* PASSWORD */

$PWFILE = __DIR__ . '/password.txt';
/*
 * Either
 * --password=(BotPassword)
 * or
 * --use-password-file
 * which will look for a $PWFILE file in the current directory containing only the plain password
 */
$pwParams = getopt( '', [
	'password:',
	'use-password-file'
] );

if ( isset( $pwParams[ 'password' ] ) ) {
	$pw = $pwParams[ 'password' ];
} elseif ( isset( $pwParams[ 'use-password-file' ] ) ) {
	if ( file_exists( $PWFILE ) ) {
		$pw = trim( file_get_contents( $PWFILE ) );
	} else {
		exit( 'Please create a password.txt file to use with use-password-file' );
	}
} else {
	exit( 'Please provide a password or use a password file' );
}

$vals[ 'password' ] = $pw;

/* START */

$logger = new \BotRiconferme\Logger\SimpleLogger();
$controller = new \BotRiconferme\Wiki\Wiki( $logger );

Config::init( $vals, $controller );

$bot = new Bot( $logger, $controller );

/*
 * E.g.
 *
 * --task=update-list
 * or
 * --subtask=user-notice
 */
$taskOpts = getopt( '', [ 'task:', 'subtask:' ] );

if ( count( $taskOpts ) === 2 ) {
	throw new InvalidArgumentException( 'Cannot specify both task and subtask.' );
} elseif ( isset( $taskOpts['task'] ) ) {
	$bot->runTask( $taskOpts[ 'task' ] );
} elseif ( isset( $taskOpts['subtask'] ) ) {
	$bot->runSubtask( $taskOpts[ 'subtask' ] );
} else {
	$bot->runAll();
}
