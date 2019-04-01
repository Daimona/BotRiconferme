<?php

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

if ( php_sapi_name() !== 'cli' ) {
	exit( 1 );
}

$required = [
	'url:',
	'username:',
	'password:',
	'list-title:',
	'config-title:'
];

/*
	'url' => 'https://it.wikipedia.org/w/api.php',
	'username',###################################################
	'password',###################################################
	'list-title' => 'Utente:Daimona_Eaytoy/List.json',
	'config-title' => 'Utente:Daimona Eaytoy/Config.json',
*/

/*
	'list-update-summary' => 'Aggiornamento lista',
	'user-notice-msg' => '{{Avviso riconferma|$1}} ~~~~',###################################################
	'user-notice-title' => 'Riconferma amministratore',
	'user-notice-summary' => 'Aggiungo avviso riconferma',
	'ric-page-text' => "{{subst:Wikipedia:Amministratori/Riconferma annuale/Schema|{$Nome utente}|{$Data di attribuzione}|{{subst:#timel:j F Y}}|{{subst:LOCALTIME}}|{{subst:#timel:j F Y|+7 DAYS}}|${N° voti quorum}}}",###################################################
	'ric-page-summary' => 'Avvio procedura',
	'ric-page-prefix' => 'Wikipedia:Amministratori/Riconferma annuale',
	'ric-news-page' => 'Template:VotazioniRCnews',
	'ric-news-page-summary' => '+1 riconferma',
	'ric-main-page' => 'Wikipedia:Amministratori/Riconferma annuale',
	'ric-main-page-summary' => '+1 riconferma',
	'ric-vote-page' => 'Wikipedia:Wikipediano/Votazioni',
	'ric-vote-page-summary' => '+1 riconferma',
	'ric-base-page-text' => "# [[$title]]\n#: riconferma in corso",###################################################
	'ric-base-page-summary' => 'pagina di riepilogo',
	'ric-base-page-summary-update' => '+1 riconferma',
*/

$vals = getopt( '', $required );
if ( count( $vals ) !== count( $required ) ) {
	exit( 1 );
}
Config::init( $vals );

$given = [];
foreach ( $required as $arg )
	getopt( $arg );
		$defaults = [

		];

Config::init( $defaults );

$bot = new Bot();
$bot->run();
