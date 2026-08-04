<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\ActivityService;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Deckt die Eingabe-Validierung und den Typ-Vertrag ab. Die Aggregation selbst
 * ist reines SQL und braucht eine DB — die Query-Pfade werden hier bewusst
 * nicht angefasst (der Mapper-Mock liefert keine Ergebnisse).
 */
class ActivityServiceTest extends TestCase {
	private IDBConnection&MockObject $db;
	private ActivityService $service;

	protected function setUp(): void {
		$this->db = $this->createMock(IDBConnection::class);
		$this->service = new ActivityService($this->db);
	}

	public function testRejectsAnUnknownType(): void {
		$this->expectException(ValidationException::class);
		$this->service->stream('alice', ['type' => 'bought']);
	}

	public function testRejectsAMalformedFromDate(): void {
		// Ein freier String landete sonst unquotiert im Datumsvergleich und
		// lieferte je nach DB stumm ein leeres oder falsches Ergebnis.
		$this->expectException(ValidationException::class);
		$this->service->stream('alice', ['from' => '01.01.2026']);
	}

	public function testRejectsAMalformedToDate(): void {
		$this->expectException(ValidationException::class);
		$this->service->stream('alice', ['to' => 'gestern']);
	}

	/** @param list<array{date: string, label: string}> $events */
	private function sortLabels(array $events): array {
		$m = new \ReflectionMethod(ActivityService::class, 'compareByDateDesc');
		$m->setAccessible(true);
		usort($events, static fn (array $a, array $b): int => $m->invoke(null, $a, $b));
		return array_column($events, 'label');
	}

	public function testNewestDayComesFirst(): void {
		$this->assertSame(['okt', 'aug', 'jul'], $this->sortLabels([
			['date' => '2026-07-29 09:00:00', 'label' => 'jul'],
			['date' => '2026-10-20 09:00:00', 'label' => 'okt'],
			['date' => '2026-08-03', 'label' => 'aug'],
		]));
	}

	public function testYearBoundaryIsRespected(): void {
		// Same day and month, different year: the older one must sink.
		$this->assertSame(['2026', '2025'], $this->sortLabels([
			['date' => '2025-10-20 12:00:00', 'label' => '2025'],
			['date' => '2026-10-20', 'label' => '2026'],
		]));
	}

	public function testAnEventWithoutATimeCountsAsMidnight(): void {
		// #214: previously the raw strcmp let the string length decide. There is no
		// correct position for an event that records no time, so the rule is made
		// explicit instead: midnight, i.e. last within its own day.
		$this->assertSame(['kauf-spaet', 'kauf-frueh', 'ereignis', 'vortag'], $this->sortLabels([
			['date' => '2026-08-03 23:59:59', 'label' => 'kauf-spaet'],
			['date' => '2026-08-03', 'label' => 'ereignis'],
			['date' => '2026-08-03 00:00:01', 'label' => 'kauf-frueh'],
			['date' => '2026-08-02 12:00:00', 'label' => 'vortag'],
		]));
	}

	public function testFormatVariationDoesNotChangeTheOrder(): void {
		// An ISO 'T' separator or a fractional second must not shift an entry.
		$this->assertSame(['spaet', 'frueh'], $this->sortLabels([
			['date' => '2026-08-03T08:00:00', 'label' => 'frueh'],
			['date' => '2026-08-03 20:00:00', 'label' => 'spaet'],
		]));
	}

	public function testTimestampsWithinOneDayKeepTheirOrder(): void {
		$this->assertSame(['abend', 'mittag', 'morgen'], $this->sortLabels([
			['date' => '2026-08-03 08:00:00', 'label' => 'morgen'],
			['date' => '2026-08-03 20:00:00', 'label' => 'abend'],
			['date' => '2026-08-03 12:00:00', 'label' => 'mittag'],
		]));
	}

	public function testTypeListMatchesTheDocumentedContract(): void {
		// 'consumed' fehlt hier absichtlich: Entkorken erzeugt immer eine
		// Verkostung, die den Zeitpunkt traegt — die Flasche selbst hat fuer
		// diesen Fall kein event_date.
		$this->assertSame(['purchase', 'tasting', 'gifted', 'lost'], ActivityService::TYPES);
	}
}
