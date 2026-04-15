<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Exception\SlotOccupiedException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\CellarService;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\PurchaseService;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class BottleServiceTest extends IntegrationTestCase {
	private BottleService $service;
	private PurchaseService $purchaseService;
	private VintageService $vintageService;
	private WineService $wineService;
	private ProducerService $producerService;
	private CellarService $cellarService;
	private SlotMapper $slotMapper;

	protected function setUp(): void {
		parent::setUp();
		$bottleMapper = new BottleMapper($this->db);
		$this->slotMapper = new SlotMapper($this->db);
		$compartmentMapper = new CompartmentMapper($this->db);
		$shelfMapper = new ShelfMapper($this->db);
		$cellarMapper = new CellarMapper($this->db);

		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->wineService = new WineService(new WineMapper($this->db), $this->producerService);
		$this->vintageService = new VintageService(new VintageMapper($this->db), $this->wineService);
		$this->purchaseService = new PurchaseService(new PurchaseMapper($this->db), $this->vintageService);

		$this->cellarService = new CellarService(
			$cellarMapper, $shelfMapper, $compartmentMapper,
			$this->slotMapper, $bottleMapper, $this->db,
		);
		$this->service = new BottleService(
			$bottleMapper, $this->slotMapper, $compartmentMapper,
			$shelfMapper, $cellarMapper,
			$this->purchaseService, $this->db,
		);
	}

	public function testCreateBottlesForPurchaseBulk(): void {
		[$userId, $purchaseId] = $this->seedPurchase(6);

		$bottles = $this->service->createBottlesForPurchase($purchaseId, $userId);

		$this->assertCount(6, $bottles);
		foreach ($bottles as $b) {
			$this->assertNull($b->getSlotId());
			$this->assertSame(Bottle::STATUS_IN_STORAGE, $b->getStatus());
		}
	}

	public function testGetParkedBottlesScopedByOwner(): void {
		[$userId, $purchaseId] = $this->seedPurchase(3);
		$this->service->createBottlesForPurchase($purchaseId, $userId);

		[, $purchaseIdOther] = $this->seedPurchase(2);

		$parked = $this->service->getParkedBottles($userId);
		$this->assertCount(3, $parked);
	}

	public function testMoveBottleToFreeSlot(): void {
		[$userId, $purchaseId] = $this->seedPurchase(1);
		[$bottle] = $this->service->createBottlesForPurchase($purchaseId, $userId);

		$slotId = $this->seedSlotForUser($userId);
		$moved = $this->service->moveBottle($bottle->getId(), $slotId, $userId);

		$this->assertSame($slotId, $moved->getSlotId());
	}

	public function testMoveToOccupiedSlotThrows(): void {
		[$userId, $purchaseId] = $this->seedPurchase(2);
		[$first, $second] = $this->service->createBottlesForPurchase($purchaseId, $userId);

		$slotId = $this->seedSlotForUser($userId);
		$this->service->moveBottle($first->getId(), $slotId, $userId);

		$this->expectException(SlotOccupiedException::class);
		$this->service->moveBottle($second->getId(), $slotId, $userId);
	}

	public function testMoveToForeignSlotRejected(): void {
		[$userId, $purchaseId] = $this->seedPurchase(1);
		[$bottle] = $this->service->createBottlesForPurchase($purchaseId, $userId);

		$intruderSlotId = $this->seedSlotForUser($this->uniqueId('owner2'));

		$this->expectException(PermissionDeniedException::class);
		$this->service->moveBottle($bottle->getId(), $intruderSlotId, $userId);
	}

	public function testConsumeReleasesSlot(): void {
		[$userId, $purchaseId] = $this->seedPurchase(1);
		[$bottle] = $this->service->createBottlesForPurchase($purchaseId, $userId);
		$slotId = $this->seedSlotForUser($userId);
		$this->service->moveBottle($bottle->getId(), $slotId, $userId);

		$consumed = $this->service->consumeBottle($bottle->getId(), $userId);

		$this->assertSame(Bottle::STATUS_CONSUMED, $consumed->getStatus());
		$this->assertNull($consumed->getSlotId());
	}

	public function testFilteredByColor(): void {
		[$userId, $purchaseRedId] = $this->seedPurchase(2, Wine::COLOR_RED);
		$this->service->createBottlesForPurchase($purchaseRedId, $userId);
		[, $purchaseWhiteId] = $this->seedPurchase(3, Wine::COLOR_WHITE, $userId);
		$this->service->createBottlesForPurchase($purchaseWhiteId, $userId);

		$reds = $this->service->getFilteredBottles($userId, ['color' => Wine::COLOR_RED]);
		$this->assertCount(2, $reds);
		$whites = $this->service->getFilteredBottles($userId, ['color' => Wine::COLOR_WHITE]);
		$this->assertCount(3, $whites);
	}

	/** @return array{0: string, 1: int} [userId, purchaseId] */
	private function seedPurchase(int $quantity, string $color = Wine::COLOR_RED, ?string $reuseUser = null): array {
		$userId = $reuseUser ?? $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P-' . substr($userId, -4));
		$wine = $this->wineService->create($userId, $producer->getId(), 'W', $color);
		$vintage = $this->vintageService->create($userId, $wine->getId(), 2020);
		$purchase = $this->purchaseService->create($userId, $vintage->getId(), [
			'quantity' => $quantity,
			'bottleSizeMl' => 750,
		]);
		return [$userId, $purchase->getId()];
	}

	private function seedSlotForUser(string $userId): int {
		$cellar = $this->cellarService->createDefaultCellar($userId);
		$active = $this->cellarService->getActiveCellar($userId);
		$comp = $active['shelves'][0]['compartments'][0];
		$slots = $this->slotMapper->findByCompartment($comp->getId());
		return $slots[0]->getId();
	}
}
