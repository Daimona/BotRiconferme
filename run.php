<?php

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

$cfg = Config::getInstance();

$cfg->set( 'time-period', 60 * 60 * 24 * 365 );
$cfg->set( 'url', 'localhost/pedia/api.php' );
$cfg->set( 'list-title', 'Utente:Daimona_Eaytoy/List.json' );
$cfg->set( 'list-update-summary', 'Aggiornamento lista' );
$cfg->set( 'username', 'Tizio_Caio' );
$cfg->set( 'password', '12345' );
$cfg->set( 'user-notice-msg', 'Hello world!' ),
$cfg->set( 'user-notice-title', 'Messaggino' ),
$cfg->set( 'user-notice-summary', 'Nuovo messaggio' ),

$bot = new Bot();

$bot->runTask( 'update-list' );
$bot->runTask( 'user-notice' );
