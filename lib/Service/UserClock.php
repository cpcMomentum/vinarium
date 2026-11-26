<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use DateTime;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDateTimeZone;

class UserClock {
	public function __construct(
		private readonly ITimeFactory $timeFactory,
		private readonly IDateTimeZone $dateTimeZone,
	) {
	}

	public function now(string $userId): DateTime {
		return $this->timeFactory->getDateTime('now', $this->dateTimeZone->getTimeZone(false, $userId));
	}

	/** Mitternacht des Kalendertags des Nutzers, gebaut wie ein geparster Y-m-d-Wert aus dem Request. */
	public function today(string $userId): DateTime {
		$today = DateTime::createFromFormat('!Y-m-d', $this->now($userId)->format('Y-m-d'));
		if ($today === false) {
			throw new \LogicException('Unparseable date');
		}
		return $today;
	}
}
