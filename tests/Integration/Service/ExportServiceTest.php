<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use DateTime;
use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Db\Tasting;
use OCA\Vinarium\Db\TastingMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Service\ExportService;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\PurchaseService;
use OCA\Vinarium\Service\UserClock;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;
use OCP\IL10N;
use OCP\Server;

class ExportServiceTest extends IntegrationTestCase {
	private const COL_RATING = 13;
	private const COL_NOTES = 14;

	private const EN = [
		'Weingut' => 'Producer', 'Wein' => 'Wine', 'Farbe' => 'Color', 'Jahrgang' => 'Vintage',
		'Rebsorten' => 'Grape Varieties', 'Trinken ab' => 'Drink from', 'Trinken bis' => 'Drink until',
		'Kaufdatum' => 'Purchase date', 'Händler' => 'Vendor', 'Preis' => 'Price', 'Währung' => 'Currency',
		'Flaschengröße (ml)' => 'Bottle size (ml)', 'Status' => 'Status', 'Bewertung' => 'Rating', 'Notizen' => 'Notes',
	];

	private ExportService $service;

	protected function setUp(): void {
		parent::setUp();
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => self::EN[$text] ?? $text);
		$this->service = new ExportService($this->db, $l10n);
	}

	public function testHeaderIsTranslated(): void {
		$csv = $this->service->exportCsv($this->uniqueId('user'));

		$header = explode("\n", substr($csv, 3), 2)[0];
		$this->assertSame(implode(';', array_values(self::EN)), $header);
	}

	public function testExportContainsHighestRatingPerBottle(): void {
		$userId = $this->uniqueId('user');
		$purchaseId = $this->seedPurchase($userId);
		$rated = $this->insertBottle($purchaseId, 'rated');
		$this->insertBottle($purchaseId, 'unrated');
		$this->insertTasting($rated->getId(), 3.0);
		$this->insertTasting($rated->getId(), 5.0);

		$rows = $this->dataRows($this->service->exportCsv($userId));

		$this->assertCount(2, $rows);
		$byNotes = array_column($rows, self::COL_RATING, self::COL_NOTES);
		$this->assertEquals(5, $byNotes['rated']);
		$this->assertSame('', $byNotes['unrated']);
	}

	public function testExportExcludesForeignUsers(): void {
		$owner = $this->uniqueId('user');
		$this->insertBottle($this->seedPurchase($owner), 'owned');

		$rows = $this->dataRows($this->service->exportCsv($this->uniqueId('other')));

		$this->assertSame([], $rows);
	}

	/** @return list<list<string>> */
	private function dataRows(string $csv): array {
		$lines = array_values(array_filter(explode("\n", $csv), static fn (string $l): bool => $l !== ''));
		array_shift($lines);
		return array_map(static fn (string $l): array => explode(';', $l), $lines);
	}

	private function seedPurchase(string $userId): int {
		$producerService = new ProducerService(new ProducerMapper($this->db));
		$wineService = new WineService(new WineMapper($this->db), $producerService);
		$vintageService = new VintageService(new VintageMapper($this->db), $wineService, new BottleMapper($this->db));
		$purchaseService = new PurchaseService(new PurchaseMapper($this->db), $vintageService, Server::get(UserClock::class));

		$producer = $producerService->create($userId, 'P-' . substr($userId, -4));
		$wine = $wineService->create($userId, $producer->getId(), 'W', Wine::COLOR_RED);
		$vintage = $vintageService->create($userId, $wine->getId(), 2020, []);
		return $purchaseService->create($userId, $vintage->getId(), ['quantity' => 2, 'bottleSizeMl' => 750])->getId();
	}

	private function insertBottle(int $purchaseId, string $notes): Bottle {
		$bottle = new Bottle();
		$bottle->setPurchaseId($purchaseId);
		$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
		$bottle->setNotes($notes);
		return (new BottleMapper($this->db))->insert($bottle);
	}

	private function insertTasting(int $bottleId, float $rating): void {
		$tasting = new Tasting();
		$tasting->setBottleId($bottleId);
		$tasting->setTastedAt(new DateTime('2026-11-01 18:00:00'));
		$tasting->setRating($rating);
		(new TastingMapper($this->db))->insert($tasting);
	}
}
