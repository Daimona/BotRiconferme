<?php declare( strict_types=1 );

namespace BotRiconferme;

use DateTime;

/**
 * Lightweight class that allows mocking date functions.
 */
class Clock {
	private static ?int $fakeTime = null;

	public static function getDate( string $format, ?int $timestamp = null ): string {
		$timestamp ??= self::$fakeTime ?? time();
		return date( $format, $timestamp );
	}

	public static function dateTimeNow(): DateTime {
		$curTime = self::$fakeTime ?? time();
		return ( new DateTime() )->setTimestamp( $curTime );
	}

	public static function now(): int {
		return self::$fakeTime ?? time();
	}

	/** @suppress PhanUnreferencedPublicMethod */
	public static function setFakeTime( int $time ): void {
		self::$fakeTime = $time;
	}

	/** @suppress PhanUnreferencedPublicMethod */
	public static function clearFakeTime(): void {
		self::$fakeTime = null;
	}
}
