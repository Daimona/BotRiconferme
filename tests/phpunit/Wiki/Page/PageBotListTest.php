<?php

namespace BotRiconferme\Tests\Wiki\Page;

use BotRiconferme\Clock;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\Wiki;
use DateTime;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( PageBotList::class )]
class PageBotListTest extends TestCase {
	private const FAKE_TIME = 1733500000;

	public static function setUpBeforeClass(): void {
		Clock::setFakeTime( self::FAKE_TIME );
	}

	public static function tearDownAfterClass(): void {
		Clock::clearFakeTime();
	}

	public function tearDown(): void {
		PageBotList::clearInstance();
	}

	private function getPageBotList( array $rawList ): PageBotList {
		$pageName = 'My bot list';
		$wiki = $this->createMock( Wiki::class );
		$wiki->method( 'getPageContent' )
			->with( $pageName )
			->willReturn( json_encode( $rawList, JSON_THROW_ON_ERROR ) );
		return PageBotList::get( $wiki, $pageName );
	}

	#[DataProvider( 'provideGetNextTimestamp' )]
	public function testGetNextTimestamp( array $userInfo, int $expected ) {
		$username = 'Margarita';
		$pbl = $this->getPageBotList( [ $username => $userInfo ] );
		$normalizedExpected = DateTime::createFromFormat( 'U', $expected )
			->setTime( 0, 0 )
			->getTimestamp();
		$this->assertSame( $normalizedExpected, $pbl->getNextTimestamp( $username ) );
	}

	public static function provideGetNextTimestamp(): Generator {
		$today = self::FAKE_TIME;
		$thisDayLastYear = DateTime::createFromFormat( 'U', $today )->modify( '-1 year' )->getTimestamp();
		$thisDayNextYear = DateTime::createFromFormat( 'U', $today )->modify( '+1 year' )->getTimestamp();
		$tenDaysAgo = $today - 60 * 60 * 24 * 10;
		$tenDaysAgoLastYear = DateTime::createFromFormat( 'U', $tenDaysAgo )->modify( '-1 year' )->getTimestamp();
		$tenDaysAgoNextYear = DateTime::createFromFormat( 'U', $tenDaysAgo )->modify( '+1 year' )->getTimestamp();
		$inTenDays = $today + 60 * 60 * 24 * 10;
		$inTenDaysLastYear = DateTime::createFromFormat( 'U', $inTenDays )->modify( '-1 year' )->getTimestamp();
		$inTenDaysNextYear = DateTime::createFromFormat( 'U', $inTenDays )->modify( '+1 year' )->getTimestamp();
		$notTodayDate = $today - 60 * 60 * 24 * 50;

		yield 'No overrides, flagged today' => [
			[ 'sysop' => date( 'd/m/Y', $today ) ],
			$thisDayNextYear
		];
		yield 'No overrides, flagged on this day last year' => [
			[ 'sysop' => date( 'd/m/Y', $thisDayLastYear ) ],
			$thisDayNextYear
		];
		yield 'No overrides, flagged in 10 days last year' => [
			[ 'sysop' => date( 'd/m/Y', $inTenDaysLastYear ) ],
			$inTenDays
		];
		yield 'No overrides, flagged 10 days ago last year' => [
			[ 'sysop' => date( 'd/m/Y', $inTenDaysLastYear ) ],
			$inTenDays
		];

		yield 'Permanent override today\'s date, flag not today' => [
			[
				'sysop' => date( 'd/m/Y', $notTodayDate ),
				'override-perm' => date( 'd/m', $today ),
			],
			$thisDayNextYear
		];
		yield 'Permanent override today\'s date, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override-perm' => date( 'd/m', $today ),
			],
			$thisDayNextYear
		];
		yield 'Permanent override in 10 days, flag 10 days ago last year' => [
			[
				'sysop' => date( 'd/m/Y', $tenDaysAgoLastYear ),
				'override-perm' => date( 'd/m', $inTenDays ),
			],
			$inTenDays
		];
		yield 'Permanent override 10 days ago, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override-perm' => date( 'd/m', $tenDaysAgo ),
			],
			$tenDaysAgoNextYear
		];

		yield 'Override this day last year, flag in 10 days last year' => [
			[
				'sysop' => date( 'd/m/Y', $inTenDaysLastYear ),
				'override' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$inTenDays
		];
		yield 'Override this day last year, flag today' => [
			[
				'sysop' => date( 'd/m/Y', $today ),
				'override' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$thisDayNextYear
		];
		yield 'Override this day last year, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$thisDayNextYear
		];

		yield 'Override 10 days ago, flag in 10 days last year' => [
			[
				'sysop' => date( 'd/m/Y', $inTenDaysLastYear ),
				'override' => date( 'd/m/Y', $tenDaysAgo ),
			],
			$inTenDays
		];
		yield 'Override 10 days ago, flag today' => [
			[
				'sysop' => date( 'd/m/Y', $today ),
				'override' => date( 'd/m/Y', $tenDaysAgo ),
			],
			$thisDayNextYear
		];
		yield 'Override 10 days ago, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override' => date( 'd/m/Y', $tenDaysAgo ),
			],
			$thisDayNextYear
		];

		yield 'Override today, flag in 10 days last year' => [
			[
				'sysop' => date( 'd/m/Y', $inTenDaysLastYear ),
				'override' => date( 'd/m/Y', $today ),
			],
			$inTenDaysNextYear
		];
		yield 'Override today, flag today' => [
			[
				'sysop' => date( 'd/m/Y', $today ),
				'override' => date( 'd/m/Y', $today ),
			],
			$thisDayNextYear
		];
		yield 'Override today, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override' => date( 'd/m/Y', $today ),
			],
			$thisDayNextYear
		];

		yield 'Override in 10 days, flag 10 days ago last year' => [
			[
				'sysop' => date( 'd/m/Y', $tenDaysAgoLastYear ),
				'override' => date( 'd/m/Y', $inTenDays ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, flag today' => [
			[
				'sysop' => date( 'd/m/Y', $today ),
				'override' => date( 'd/m/Y', $inTenDays ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, flag this day last year' => [
			[
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				'override' => date( 'd/m/Y', $inTenDays ),
			],
			$inTenDays
		];

		yield 'Override today, permanent override in 10 days, flag 10 days ago last year' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $tenDaysAgoLastYear ),
			],
			$inTenDaysNextYear
		];
		yield 'Override today, permanent override in 10 days, flag today' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$inTenDaysNextYear
		];
		yield 'Override today, permanent override in 10 days, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$inTenDaysNextYear
		];
		yield 'Override today, permanent override today, flag not today' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $notTodayDate ),
			],
			$thisDayNextYear
		];
		yield 'Override today, permanent override today, flag today' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$thisDayNextYear
		];
		yield 'Override today, permanent override today, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $today ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$thisDayNextYear
		];

		yield 'Override 10 days ago, permanent override in 10 days, flag not today' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $notTodayDate ),
			],
			$inTenDaysNextYear
		];
		yield 'Override 10 days ago, permanent override in 10 days, flag today' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$inTenDaysNextYear
		];
		yield 'Override 10 days ago, permanent override in 10 days, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $inTenDays ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$inTenDaysNextYear
		];
		yield 'Override 10 days ago, permanent override today, flag not today' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $notTodayDate ),
			],
			$thisDayNextYear
		];
		yield 'Override 10 days ago, permanent override today, flag today' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$thisDayNextYear
		];
		yield 'Override 10 days ago, permanent override today, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $tenDaysAgo ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$thisDayNextYear
		];

		yield 'Override in 10 days, permanent override 10 days ago, flag not today' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $tenDaysAgo ),
				'sysop' => date( 'd/m/Y', $notTodayDate ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, permanent override 10 days ago, flag today' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $tenDaysAgo ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, permanent override 10 days ago, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $tenDaysAgo ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, permanent override today, flag not today' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $notTodayDate ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, permanent override today, flag today' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $today ),
			],
			$inTenDays
		];
		yield 'Override in 10 days, permanent override today, flag this day last year' => [
			[
				'override' => date( 'd/m/Y', $inTenDays ),
				'override-perm' => date( 'd/m', $today ),
				'sysop' => date( 'd/m/Y', $thisDayLastYear ),
			],
			$inTenDays
		];
	}
}
