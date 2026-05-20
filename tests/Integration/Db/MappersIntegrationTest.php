<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Db;

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
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Db\Tasting;
use OCA\Vinarium\Db\TastingMapper;
use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class MappersIntegrationTest extends IntegrationTestCase {

	public function testCellarInsertAndFindByOwner(): void {
		$mapper = new CellarMapper($this->db);
		$userId = $this->uniqueId('user');

		$cellar = new Cellar();
		$cellar->setOwnerUserId($userId);
		$cellar->setName('Mein Weinkeller');
		$cellar->setCreatedAt(new DateTime());
		$inserted = $mapper->insert($cellar);

		$this->assertGreaterThan(0, $inserted->getId());

		$found = $mapper->findByOwner($userId);
		$this->assertCount(1, $found);
		$this->assertSame('Mein Weinkeller', $found[0]->getName());

		$byId = $mapper->findOneByOwner($inserted->getId(), $userId);
		$this->assertSame($inserted->getId(), $byId->getId());
	}

	public function testShelfInsertAndFindByCellar(): void {
		$cellarId = $this->insertCellar();
		$mapper = new ShelfMapper($this->db);

		$shelf = new Shelf();
		$shelf->setCellarId($cellarId);
		$shelf->setName('Regal A');
		$shelf->setSortOrder(1);
		$inserted = $mapper->insert($shelf);

		$found = $mapper->findByCellar($cellarId);
		$this->assertCount(1, $found);
		$this->assertSame('Regal A', $found[0]->getName());
		$this->assertSame($inserted->getId(), $found[0]->getId());
	}

	public function testCompartmentInsertAndFindByShelf(): void {
		$shelfId = $this->insertShelf($this->insertCellar());
		$mapper = new CompartmentMapper($this->db);

		$comp = new Compartment();
		$comp->setShelfId($shelfId);
		$comp->setLabel('Fach 1');
		$comp->setSortOrder(0);
		$mapper->insert($comp);

		$found = $mapper->findByShelf($shelfId);
		$this->assertCount(1, $found);
		$this->assertSame('Fach 1', $found[0]->getLabel());
		$this->assertSame(0, $found[0]->getSortOrder());
	}

	public function testSlotInsertAndDeleteByCompartment(): void {
		$compId = $this->insertCompartment();
		$mapper = new SlotMapper($this->db);

		foreach (['front', 'back'] as $row) {
			for ($col = 0; $col < 3; $col++) {
				$slot = new Slot();
				$slot->setCompartmentId($compId);
				$slot->setLevel(0);
				$slot->setRow($row);
				$slot->setColumn($col);
				$mapper->insert($slot);
			}
		}

		$this->assertCount(6, $mapper->findByCompartment($compId));
		$deleted = $mapper->deleteByCompartment($compId);
		$this->assertSame(6, $deleted);
		$this->assertCount(0, $mapper->findByCompartment($compId));
	}

	public function testProducerInsertAndFindByOwner(): void {
		$mapper = new ProducerMapper($this->db);
		$userId = $this->uniqueId('user');

		$prod = new Producer();
		$prod->setOwnerUserId($userId);
		$prod->setName('Weingut Test');
		$prod->setCountry('DE');
		$mapper->insert($prod);

		$found = $mapper->findByOwner($userId);
		$this->assertCount(1, $found);
		$this->assertSame('DE', $found[0]->getCountry());
	}

	public function testWineInsertAndFindByProducer(): void {
		$producerId = $this->insertProducer();
		$mapper = new WineMapper($this->db);

		$wine = new Wine();
		$wine->setProducerId($producerId);
		$wine->setName('Riesling');
		$wine->setColor(Wine::COLOR_WHITE);
		$mapper->insert($wine);

		$found = $mapper->findByProducer($producerId);
		$this->assertCount(1, $found);
		$this->assertSame(Wine::COLOR_WHITE, $found[0]->getColor());
	}

	public function testVintageInsertAndFindByWine(): void {
		$wineId = $this->insertWine($this->insertProducer());
		$mapper = new VintageMapper($this->db);

		$vintage = new Vintage();
		$vintage->setWineId($wineId);
		$vintage->setYear(2022);
		$vintage->setAlcoholPercent(12.5);
		$mapper->insert($vintage);

		$found = $mapper->findByWine($wineId);
		$this->assertCount(1, $found);
		$this->assertSame(2022, $found[0]->getYear());
		$this->assertSame(12.5, $found[0]->getAlcoholPercent());
	}

	public function testPurchaseInsertAndFindByVintage(): void {
		$vintageId = $this->insertVintage();
		$mapper = new PurchaseMapper($this->db);

		$purchase = new Purchase();
		$purchase->setVintageId($vintageId);
		$purchase->setPurchasedAt(new DateTime('2026-03-01'));
		$purchase->setQuantity(6);
		$purchase->setBottleSizeMl(750);
		$mapper->insert($purchase);

		$found = $mapper->findByVintage($vintageId);
		$this->assertCount(1, $found);
		$this->assertSame(6, $found[0]->getQuantity());
	}

	public function testBottleInsertAndClearSlot(): void {
		$purchaseId = $this->insertPurchase();
		$compId = $this->insertCompartment();
		$slotMapper = new SlotMapper($this->db);

		$slot = new Slot();
		$slot->setCompartmentId($compId);
		$slot->setLevel(0);
		$slot->setRow('front');
		$slot->setColumn(0);
		$slot = $slotMapper->insert($slot);

		$mapper = new BottleMapper($this->db);
		$bottle = new Bottle();
		$bottle->setPurchaseId($purchaseId);
		$bottle->setSlotId($slot->getId());
		$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
		$inserted = $mapper->insert($bottle);

		$this->assertSame($slot->getId(), $inserted->getSlotId());

		$cleared = $mapper->clearSlotForSlotIds([$slot->getId()]);
		$this->assertSame(1, $cleared);

		$byPurchase = $mapper->findByPurchase($purchaseId);
		$this->assertNull($byPurchase[0]->getSlotId());
	}

	public function testTastingInsertAndFindByBottle(): void {
		$bottleId = $this->insertBottle();
		$mapper = new TastingMapper($this->db);

		$tasting = new Tasting();
		$tasting->setBottleId($bottleId);
		$tasting->setTastedAt(new DateTime());
		$tasting->setRating(8.5);
		$tasting->setPhotoFileIds([123, 456]);
		$mapper->insert($tasting);

		$found = $mapper->findByBottle($bottleId);
		$this->assertCount(1, $found);
		$this->assertSame(8.5, $found[0]->getRating());
		$this->assertSame([123, 456], $found[0]->getPhotoFileIds());
	}

	private function insertCellar(): int {
		$mapper = new CellarMapper($this->db);
		$cellar = new Cellar();
		$cellar->setOwnerUserId($this->uniqueId('user'));
		$cellar->setName('C');
		$cellar->setCreatedAt(new DateTime());
		return $mapper->insert($cellar)->getId();
	}

	private function insertShelf(int $cellarId): int {
		$mapper = new ShelfMapper($this->db);
		$shelf = new Shelf();
		$shelf->setCellarId($cellarId);
		$shelf->setName('S');
		$shelf->setSortOrder(0);
		return $mapper->insert($shelf)->getId();
	}

	private function insertCompartment(): int {
		$shelfId = $this->insertShelf($this->insertCellar());
		$mapper = new CompartmentMapper($this->db);
		$comp = new Compartment();
		$comp->setShelfId($shelfId);
		$comp->setLabel('F');
		$comp->setSortOrder(0);
		return $mapper->insert($comp)->getId();
	}

	private function insertProducer(): int {
		$mapper = new ProducerMapper($this->db);
		$prod = new Producer();
		$prod->setOwnerUserId($this->uniqueId('user'));
		$prod->setName('P');
		return $mapper->insert($prod)->getId();
	}

	private function insertWine(int $producerId): int {
		$mapper = new WineMapper($this->db);
		$wine = new Wine();
		$wine->setProducerId($producerId);
		$wine->setName('W');
		$wine->setColor(Wine::COLOR_RED);
		return $mapper->insert($wine)->getId();
	}

	private function insertVintage(): int {
		$wineId = $this->insertWine($this->insertProducer());
		$mapper = new VintageMapper($this->db);
		$vintage = new Vintage();
		$vintage->setWineId($wineId);
		$vintage->setYear(2020);
		return $mapper->insert($vintage)->getId();
	}

	private function insertPurchase(): int {
		$mapper = new PurchaseMapper($this->db);
		$purchase = new Purchase();
		$purchase->setVintageId($this->insertVintage());
		$purchase->setPurchasedAt(new DateTime());
		$purchase->setQuantity(1);
		$purchase->setBottleSizeMl(750);
		return $mapper->insert($purchase)->getId();
	}

	private function insertBottle(): int {
		$mapper = new BottleMapper($this->db);
		$bottle = new Bottle();
		$bottle->setPurchaseId($this->insertPurchase());
		$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
		return $mapper->insert($bottle)->getId();
	}
}
