<?php

require __DIR__ . '/vendor/autoload.php';

use BotRiconferme\Config;
use BotRiconferme\Bot;

Config::getInstanceFor( 'itwiki' );

$bot = new Bot();
$bot->run();
