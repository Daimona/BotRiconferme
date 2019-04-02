<?php

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

/*
	Example

	'url' => 'https://it.wikipedia.org/w/api.php',
	'username' => 'BotRiconferme'
	'password' => ...BotPassword...,
	'list-title' => 'Utente:BotRiconferme/List.json',
	'config-title' => 'Utente:BotRiconferme/Config.json',
*/

$required = [
	'url:',
	'username:',
	'password:',
	'list-title:',
	'config-title:'
];

$vals = getopt( '', $required );
if ( count( $vals ) !== count( $required ) ) {
	exit( 1 );
}
Config::init( $vals );

$bot = new Bot();

/*
 * E.g. --task update-list
 */
$task = getopt( '', [ 'task:' ] );

if ( $task ) {
	$bot->runSingle( $task );
} else {
	$bot->run();
}
