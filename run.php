<?php declare( strict_types=1 );

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

if ( PHP_SAPI !== 'cli' ) {
	exit( 'CLI only!' );
}

/** MAIN PARAMS */

/*
	Example

	'username' => 'BotRiconferme'
	'list-title' => 'Utente:BotRiconferme/List.json',
	'config-title' => 'Utente:BotRiconferme/Config.json',
*/

$params = [
	'username:',
	'list-title:',
	'config-title:'
];

$vals = getopt( '', $params );
if ( count( $vals ) !== count( $params ) ) {
	exit( 'Not enough params!' );
}

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
