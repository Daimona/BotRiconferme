<?php declare( strict_types=1 );
/**
 * Entry point for the bot, called by CLI
 */

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;
use BotRiconferme\Request\RequestBase;

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only!' );
}

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
$url = isset( $urlParam['force-url'] ) ?
	$urlParam['force-url'] :
	'https://it.wikipedia.org/w/api.php';

define( 'DEFAULT_URL', $url );

/* PASSWORD */

$PWFILE = './password.txt';
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

Config::init( $vals );

$bot = new Bot();

/*
 * E.g. --task=update-list
 */
$taskOpts = getopt( '', [ 'task:' ] );

if ( $taskOpts ) {
	$bot->runSingle( $taskOpts[ 'task' ] );
} else {
	$bot->run();
}
