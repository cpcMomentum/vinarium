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

	public function testTypeListMatchesTheDocumentedContract(): void {
		// 'consumed' fehlt hier absichtlich: Entkorken erzeugt immer eine
		// Verkostung, die den Zeitpunkt traegt — die Flasche selbst hat fuer
		// diesen Fall kein event_date.
		$this->assertSame(['purchase', 'tasting', 'gifted', 'lost'], ActivityService::TYPES);
	}
}
