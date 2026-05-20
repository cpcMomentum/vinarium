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
use OCA\Vinarium\Db\Cellar;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\Compartment;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\Producer;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\Purchase;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Db\Shelf;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\Slot;
use OCA\Vinarium\Db\LevelMapper;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Service\CellarService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class CellarServiceTest extends IntegrationTestCase {
	private CellarService $service;
	private CellarMapper $cellarMapper;
	private ShelfMapper $shelfMapper;
	private CompartmentMapper $compartmentMapper;
	private LevelMapper $levelMapper;
	private SlotMapper $slotMapper;
	private BottleMapper $bottleMapper;
	private ProducerMapper $producerMapper;
	private WineMapper $wineMapper;
	private VintageMapper $vintageMapper;
	private PurchaseMapper $purchaseMapper;

	protected function setUp(): void {
		parent::setUp();
		$this->cellarMapper = new CellarMapper($this->db);
		$this->shelfMapper = new ShelfMapper($this->db);
		$this->compartmentMapper = new CompartmentMapper($this->db);
		$this->levelMapper = new LevelMapper($this->db);
		$this->slotMapper = new SlotMapper($this->db);
		$this->bottleMapper = new BottleMapper($this->db);
		$this->producerMapper = new ProducerMapper($this->db);
		$this->wineMapper = new WineMapper($this->db);
		$this->vintageMapper = new VintageMapper($this->db);
		$this->purchaseMapper = new PurchaseMapper($this->db);

		$this->service = new CellarService(
			$this->cellarMapper,
			$this->shelfMapper,
			$this->compartmentMapper,
			$this->levelMapper,
			$this->slotMapper,
			$this->bottleMapper,
			$this->db,
		);
	}

	public function testCreateDefaultCellarCreates156Slots(): void {
		$userId = $this->uniqueId('user');
		$cellar = $this->service->createDefaultCellar($userId);

		$this->assertSame($userId, $cellar->getOwnerUserId());

		$shelves = $this->shelfMapper->findByCellar($cellar->getId());
		$this->assertCount(1, $shelves);

		$compartments = $this->compartmentMapper->findByShelf($shelves[0]->getId());
		$this->assertCount(CellarService::DEFAULT_COMPARTMENTS, $compartments);

		$totalSlots = 0;
		foreach ($compartments as $comp) {
			$totalSlots += count($this->slotMapper->findByCompartment($comp->getId()));
		}
		// DEFAULT_COMPARTMENTS(4) * DEFAULT_LEVELS(3) * (DEFAULT_COLUMNS_FRONT(6) + DEFAULT_COLUMNS_BACK(7)) = 156
		$this->assertSame(
			CellarService::DEFAULT_COMPARTMENTS * CellarService::DEFAULT_LEVELS
				* (CellarService::DEFAULT_COLUMNS_FRONT + CellarService::DEFAULT_COLUMNS_BACK),
			$totalSlots
		);
	}

	public function testGetActiveCellarReturnsNested(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);

		$result = $this->service->getActiveCellar($userId);

		$this->assertInstanceOf(Cellar::class, $result['cellar']);
		$this->assertCount(1, $result['shelves']);
		$this->assertCount(CellarService::DEFAULT_COMPARTMENTS, $result['shelves'][0]['compartments']);
	}

	public function testGetActiveCellarThrowsWhenMissing(): void {
		$this->expectException(NotFoundException::class);
		$this->service->getActiveCellar($this->uniqueId('user'));
	}

	public function testReconfigureCompartmentMovesBottlesToParkzone(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$activeCellar = $this->service->getActiveCellar($userId);
		$comp = $activeCellar['shelves'][0]['compartments'][0]['compartment'];

		$slots = $this->slotMapper->findByCompartment($comp->getId());
		$this->assertGreaterThanOrEqual(2, count($slots));

		$purchaseId = $this->seedPurchase($userId);
		$bottleIds = [];
		foreach ([$slots[0], $slots[1]] as $slot) {
			$bottle = new Bottle();
			$bottle->setPurchaseId($purchaseId);
			$bottle->setSlotId($slot->getId());
			$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
			$bottleIds[] = $this->bottleMapper->insert($bottle)->getId();
		}

		$moved = $this->service->reconfigureCompartment($comp->getId(), [
			['columnsFront' => 3, 'columnsBack' => 3],
			['columnsFront' => 3, 'columnsBack' => 3],
		], $userId);

		$this->assertSame(2, $moved);
		foreach ($bottleIds as $id) {
			$this->assertNull($this->bottleMapper->find($id)->getSlotId());
		}

		$newSlots = $this->slotMapper->findByCompartment($comp->getId());
		$this->assertCount(2 * (3 + 3), $newSlots);
	}

	public function testReconfigureRejectsForeignOwner(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$comp = $active['shelves'][0]['compartments'][0]['compartment'];

		$this->expectException(PermissionDeniedException::class);
		$this->service->reconfigureCompartment($comp->getId(), [
			['columnsFront' => 6, 'columnsBack' => 7],
		], $this->uniqueId('intruder'));
	}

	public function testAddCompartmentAppendsToShelfWithCorrectSortOrder(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$shelfId = $active['shelves'][0]['shelf']->getId();
		$initialCount = count($active['shelves'][0]['compartments']);

		$comp = $this->service->addCompartmentToShelf($shelfId, $userId, [
			['columnsFront' => 4, 'columnsBack' => 4],
			['columnsFront' => 4, 'columnsBack' => 4],
		]);

		$this->assertSame($initialCount, $comp->getSortOrder());
		$this->assertSame('Fach ' . ($initialCount + 1), $comp->getLabel());

		$slots = $this->slotMapper->findByCompartment($comp->getId());
		$this->assertCount(2 * (4 + 4), $slots);

		$compartments = $this->compartmentMapper->findByShelf($shelfId);
		$this->assertCount($initialCount + 1, $compartments);
	}

	public function testAddCompartmentRejectsForeignOwner(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$shelfId = $active['shelves'][0]['shelf']->getId();

		$this->expectException(PermissionDeniedException::class);
		$this->service->addCompartmentToShelf($shelfId, $this->uniqueId('intruder'), [
			['columnsFront' => 4, 'columnsBack' => null],
		]);
	}

	public function testDestroyCompartmentMovesBottlesToParkzone(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$comp = $active['shelves'][0]['compartments'][0]['compartment'];
		$shelfId = $active['shelves'][0]['shelf']->getId();

		$slots = $this->slotMapper->findByCompartment($comp->getId());
		$this->assertGreaterThanOrEqual(1, count($slots));

		$purchaseId = $this->seedPurchase($userId);
		$bottle = new Bottle();
		$bottle->setPurchaseId($purchaseId);
		$bottle->setSlotId($slots[0]->getId());
		$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
		$bottleId = $this->bottleMapper->insert($bottle)->getId();

		$moved = $this->service->destroyCompartment($comp->getId(), $userId);

		$this->assertSame(1, $moved);
		$this->assertNull($this->bottleMapper->find($bottleId)->getSlotId());

		$this->assertCount(0, $this->slotMapper->findByCompartment($comp->getId()));
		$remaining = $this->compartmentMapper->findByShelf($shelfId);
		$this->assertCount(CellarService::DEFAULT_COMPARTMENTS - 1, $remaining);
	}

	public function testDestroyCompartmentRejectsForeignOwner(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$comp = $active['shelves'][0]['compartments'][0]['compartment'];

		$this->expectException(PermissionDeniedException::class);
		$this->service->destroyCompartment($comp->getId(), $this->uniqueId('intruder'));
	}

	public function testUpdateShelfRenames(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$shelfId = $active['shelves'][0]['shelf']->getId();

		$updated = $this->service->updateShelf($shelfId, $userId, 'Keller Nord');

		$this->assertSame('Keller Nord', $updated->getName());
		$this->assertSame('Keller Nord', $this->shelfMapper->find($shelfId)->getName());
	}

	public function testUpdateShelfRejectsForeignOwner(): void {
		$userId = $this->uniqueId('user');
		$this->service->createDefaultCellar($userId);
		$active = $this->service->getActiveCellar($userId);
		$shelfId = $active['shelves'][0]['shelf']->getId();

		$this->expectException(PermissionDeniedException::class);
		$this->service->updateShelf($shelfId, $this->uniqueId('intruder'), 'Hacked');
	}

	public function testUpdateShelfThrowsWhenMissing(): void {
		$this->expectException(NotFoundException::class);
		$this->service->updateShelf(999999, $this->uniqueId('user'), 'X');
	}

	private function seedPurchase(string $userId): int {
		$producer = new Producer();
		$producer->setOwnerUserId($userId);
		$producer->setName('P');
		$producer = $this->producerMapper->insert($producer);

		$wine = new Wine();
		$wine->setProducerId($producer->getId());
		$wine->setName('W');
		$wine->setColor(Wine::COLOR_RED);
		$wine = $this->wineMapper->insert($wine);

		$vintage = new Vintage();
		$vintage->setWineId($wine->getId());
		$vintage->setYear(2020);
		$vintage = $this->vintageMapper->insert($vintage);

		$purchase = new Purchase();
		$purchase->setVintageId($vintage->getId());
		$purchase->setPurchasedAt(new DateTime());
		$purchase->setQuantity(1);
		$purchase->setBottleSizeMl(750);
		return $this->purchaseMapper->insert($purchase)->getId();
	}
}
