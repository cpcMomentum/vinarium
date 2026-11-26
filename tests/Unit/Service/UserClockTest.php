<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use DateTime;
use DateTimeZone;
use OCA\Vinarium\Service\UserClock;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;
use PHPUnit\Framework\TestCase;

class UserClockTest extends TestCase {
	public static function clock(TestCase $test, string $utcInstant, string $zone = 'Europe/Berlin'): UserClock {
		$timeFactory = $test->getMockBuilder(ITimeFactory::class)->getMock();
		$timeFactory->method('getDateTime')->willReturnCallback(
			function (string $time = 'now', ?DateTimeZone $timezone = null) use ($utcInstant): DateTime {
				$d = new DateTime($utcInstant);
				return $timezone === null ? $d : $d->setTimezone($timezone);
			}
		);
		$dateTimeZone = $test->getMockBuilder(IDateTimeZone::class)->getMock();
		$dateTimeZone->method('getTimeZone')->willReturn(new DateTimeZone($zone));
		return new UserClock($timeFactory, $dateTimeZone);
	}

	public function testTodayIsTheLocalDayShortlyAfterMidnightInWinter(): void {
		$clock = self::clock($this, '2026-11-11T23:30:00Z');
		$this->assertSame('2026-11-12', $clock->today('alice')->format('Y-m-d'));
	}

	public function testTodayIsTheLocalDayShortlyAfterMidnightInSummer(): void {
		$clock = self::clock($this, '2026-07-01T22:30:00Z');
		$this->assertSame('2026-07-02', $clock->today('alice')->format('Y-m-d'));
	}

	public function testTodayIsMidnightLikeAParsedRequestDate(): void {
		$clock = self::clock($this, '2026-11-11T23:30:00Z');
		$parsed = DateTime::createFromFormat('Y-m-d', '2026-11-12');
		$parsed->setTime(0, 0);
		$this->assertEquals($parsed, $clock->today('alice'));
	}

	public function testNowCarriesTheUserYear(): void {
		$clock = self::clock($this, '2026-12-31T23:30:00Z');
		$this->assertSame('2027', $clock->now('alice')->format('Y'));
	}
}
