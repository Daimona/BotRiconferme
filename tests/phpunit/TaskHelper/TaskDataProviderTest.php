<?php

declare( strict_types = 1 );

namespace BotRiconferme\Tests\TaskHelper;

use BotRiconferme\Clock;
use BotRiconferme\Config;
use BotRiconferme\Message\MessageProvider;
use BotRiconferme\TaskHelper\TaskDataProvider;
use BotRiconferme\Tests\ConfigAwareTestCase;
use BotRiconferme\Wiki\Page\PageBotList;
use BotRiconferme\Wiki\User;
use BotRiconferme\Wiki\UserInfo;
use BotRiconferme\Wiki\WikiGroup;
use DateTime;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;

#[CoversClass( TaskDataProvider::class )]
#[UsesClass( Clock::class )]
#[UsesClass( UserInfo::class )]
#[UsesClass( PageBotList::class )]
#[UsesClass( User::class )]
#[UsesClass( Config::class )]
class TaskDataProviderTest extends ConfigAwareTestCase {
	private const FAKE_TIME = 1733500000;

	public static function setUpBeforeClass(): void {
		Clock::setFakeTime( self::FAKE_TIME );
	}

	public static function tearDownAfterClass(): void {
		Clock::clearFakeTime();
	}

	#[DataProvider( 'provideUsersToProcess' )]
	public function testGetUsersToProcess( array $userData, array $expectedNames ) {
		$adminList = [];
		foreach ( $userData as $username => $data ) {
			$adminList[$username] = new UserInfo( $username, $data );
		}

		$pageBotList = $this->createStub( PageBotList::class );
		$pageBotList->method( 'getAdminsList' )->willReturn( $adminList );
		$taskProvider = new TaskDataProvider(
			$this->createStub( LoggerInterface::class ),
			$this->createStub( WikiGroup::class ),
			$this->createStub( MessageProvider::class ),
			$pageBotList
		);
		$this->assertSame( $expectedNames, array_keys( $taskProvider->getUsersToProcess() ) );
	}

	public static function provideUsersToProcess(): Generator {
		$today = self::FAKE_TIME;
		$thisDayLastYear = ( new DateTime )->setTimestamp( $today )->modify( '-1 year' )->getTimestamp();
		$earlierThisYear = $today - 60 * 60 * 24 * 10;
		$laterThisYear = $today + 60 * 60 * 24 * 10;
		$notTodayDate = $today - 60 * 60 * 24 * 50;

		yield 'Empty list' => [ [], [] ];

		yield 'No overrides, flagged today' => [
			[ 'Rick' => [ 'sysop' => date( 'd/m/Y', $today ) ] ],
			[]
		];
		yield 'No overrides, flagged on this day last year' => [
			[ 'Rick' => [ 'sysop' => date( 'd/m/Y', $thisDayLastYear ) ] ],
			[ 'Rick' ]
		];

		yield 'Permanent override today\'s date, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override-perm' => date( 'd/m', $today ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Permanent override today\'s date, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override-perm' => date( 'd/m', $today ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Permanent override not today\'s date, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override-perm' => date( 'd/m', $notTodayDate ),
				]
			],
			[]
		];
		yield 'Permanent override not today\'s date, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
				]
			],
			[]
		];

		yield 'Override this day last year, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];
		yield 'Override this day last year, flag today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $today ),
					'override' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];
		yield 'Override this day last year, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[ 'Rick' ]
		];

		yield 'Override earlier this year, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override' => date( 'd/m/Y', $earlierThisYear ),
				]
			],
			[]
		];
		yield 'Override earlier this year, flag today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $today ),
					'override' => date( 'd/m/Y', $earlierThisYear ),
				]
			],
			[]
		];
		yield 'Override earlier this year, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override' => date( 'd/m/Y', $earlierThisYear ),
				]
			],
			[]
		];

		yield 'Override today, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override' => date( 'd/m/Y', $today ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Override today, flag today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $today ),
					'override' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override today, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override' => date( 'd/m/Y', $today ),
				]
			],
			[ 'Rick' ]
		];

		yield 'Override later this year, flag not today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $notTodayDate ),
					'override' => date( 'd/m/Y', $laterThisYear ),
				]
			],
			[]
		];
		yield 'Override later this year, flag today' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $today ),
					'override' => date( 'd/m/Y', $laterThisYear ),
				]
			],
			[]
		];
		yield 'Override later this year, flag this day last year' => [
			[
				'Rick' => [
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
					'override' => date( 'd/m/Y', $laterThisYear ),
				]
			],
			[]
		];

		yield 'Override today, permanent override not today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Override today, permanent override not today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override today, permanent override not today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Override today, permanent override today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[ 'Rick' ]
		];
		yield 'Override today, permanent override today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override today, permanent override today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $today ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[ 'Rick' ]
		];

		yield 'Override earlier this year, permanent override not today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[]
		];
		yield 'Override earlier this year, permanent override not today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override earlier this year, permanent override not today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];
		yield 'Override earlier this year, permanent override today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[]
		];
		yield 'Override earlier this year, permanent override today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override earlier this year, permanent override today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $earlierThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];

		yield 'Override later this year, permanent override not today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[]
		];
		yield 'Override later this year, permanent override not today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override later this year, permanent override not today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $notTodayDate ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];
		yield 'Override later this year, permanent override today, flag not today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $notTodayDate ),
				]
			],
			[]
		];
		yield 'Override later this year, permanent override today, flag today' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $today ),
				]
			],
			[]
		];
		yield 'Override later this year, permanent override today, flag this day last year' => [
			[
				'Rick' => [
					'override' => date( 'd/m/Y', $laterThisYear ),
					'override-perm' => date( 'd/m', $today ),
					'sysop' => date( 'd/m/Y', $thisDayLastYear ),
				]
			],
			[]
		];
	}
}
