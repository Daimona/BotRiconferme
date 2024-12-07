<?php

declare( strict_types = 1 );

namespace BotRiconferme\Tests;

use BotRiconferme\Config;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\TestCase;

abstract class ConfigAwareTestCase extends TestCase {
	#[BeforeClass]
	public static function initConfig(): void {
		Config::init( [] );
	}

	#[AfterClass]
	public static function clearConfig(): void {
		Config::clearInstance();
	}
}
