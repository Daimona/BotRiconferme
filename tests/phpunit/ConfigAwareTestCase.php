<?php

namespace BotRiconferme\Tests;

use BotRiconferme\Clock;
use BotRiconferme\Config;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Clock::class )]
class ConfigAwareTestCase extends TestCase {
	#[BeforeClass]
	public static function initConfig() {
		Config::init( [] );
	}

	#[AfterClass]
	public static function clearConfig() {
		Config::clearInstance();
	}
}
