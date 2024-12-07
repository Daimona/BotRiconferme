<?php

declare( strict_types = 1 );

namespace BotRiconferme\Tests;

use BotRiconferme\Clock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Clock::class )]
class ClockTest extends TestCase {
	public function testGetDate(): void {
		$format = 'Ymd H:i:s';
		$ts = time();
		$this->assertSame( date( $format, $ts ), Clock::getDate( $format, $ts ) );
	}

	public function testFakeTime(): void {
		$fakeTime = 1733440000;
		Clock::setFakeTime( $fakeTime );
		$this->assertSame( (string)$fakeTime, Clock::getDate( 'U' ) );
		Clock::clearFakeTime();
		$this->assertNotSame( (string)$fakeTime, Clock::getDate( 'U' ) );
	}
}
