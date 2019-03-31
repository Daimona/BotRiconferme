<?php

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

$cfg = Config::getInstance();

$cfg->set( 'url', 'localhost/pedia/api.php' );
$cfg->set( 'list-title', 'Utente:Daimona_Eaytoy/List.json' );
$cfg->set( 'list-update-summary', 'Aggiornamento lista' );
$cfg->set( 'username', 'Tizio_Caio' );
$cfg->set( 'password', '12345' );

$bot = new Bot();

$bot->runTask( 'update-list' );
