<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use DateTime;
use OCA\Vinarium\Db\Cellar;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\Compartment;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\Shelf;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\Slot;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use Throwable;

class CellarService {

	public const DEFAULT_COMPARTMENTS = 6;
	public const DEFAULT_LEVELS = 3;
	public const DEFAULT_COLUMNS_FRONT = 6;
	public const DEFAULT_COLUMNS_BACK = 7;

	public function __construct(
		private readonly CellarMapper $cellarMapper,
		private readonly ShelfMapper $shelfMapper,
		private readonly CompartmentMapper $compartmentMapper,
		private readonly SlotMapper $slotMapper,
		private readonly BottleMapper $bottleMapper,
		private readonly IDBConnection $db,
	) {
	}

	public function createDefaultCellar(string $userId): Cellar {
		$this->db->beginTransaction();
		try {
			$cellar = new Cellar();
			$cellar->setOwnerUserId($userId);
			$cellar->setName('Mein Weinkeller');
			$cellar->setCreatedAt(new DateTime());
			$cellar = $this->cellarMapper->insert($cellar);

			$shelf = new Shelf();
			$shelf->setCellarId($cellar->getId());
			$shelf->setName('Regal 1');
			$shelf->setSortOrder(0);
			$shelf = $this->shelfMapper->insert($shelf);

			for ($c = 0; $c < self::DEFAULT_COMPARTMENTS; $c++) {
				$comp = new Compartment();
				$comp->setShelfId($shelf->getId());
				$comp->setLabel('Fach ' . ($c + 1));
				$comp->setSortOrder($c);
				$comp->setLevels(self::DEFAULT_LEVELS);
				$comp->setColumnsFront(self::DEFAULT_COLUMNS_FRONT);
				$comp->setColumnsBack(self::DEFAULT_COLUMNS_BACK);
				$comp = $this->compartmentMapper->insert($comp);

				$this->createSlotsForCompartment(
					$comp->getId(),
					self::DEFAULT_LEVELS,
					self::DEFAULT_COLUMNS_FRONT,
					self::DEFAULT_COLUMNS_BACK,
				);
			}

			$this->db->commit();
			return $cellar;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return array{cellar: Cellar, shelves: list<array{shelf: Shelf, compartments: Compartment[]}>} */
	public function getActiveCellar(string $userId): array {
		$cellars = $this->cellarMapper->findByOwner($userId);
		if ($cellars === []) {
			throw new NotFoundException('No cellar for user');
		}
		$cellar = $cellars[0];

		$shelves = $this->shelfMapper->findByCellar($cellar->getId());
		$result = [];
		foreach ($shelves as $shelf) {
			$result[] = [
				'shelf' => $shelf,
				'compartments' => $this->compartmentMapper->findByShelf($shelf->getId()),
			];
		}

		return ['cellar' => $cellar, 'shelves' => $result];
	}

	/**
	 * Replaces a compartment's slots and moves affected bottles to the Parkzone (slot_id = NULL).
	 *
	 * @return int Number of bottles moved to the Parkzone.
	 */
	public function reconfigureCompartment(
		int $compartmentId,
		int $levels,
		int $columnsFront,
		int $columnsBack,
		string $userId,
	): int {
		if ($levels < 1 || $columnsFront < 0 || $columnsBack < 0 || ($columnsFront + $columnsBack) < 1) {
			throw new \InvalidArgumentException('Invalid compartment geometry');
		}

		$this->db->beginTransaction();
		try {
			$comp = $this->compartmentMapper->find($compartmentId);
			$this->assertCompartmentOwnership($comp, $userId);

			$existingSlots = $this->slotMapper->findByCompartment($compartmentId);
			$slotIds = array_map(static fn (Slot $s): int => (int)$s->getId(), $existingSlots);

			$movedBottles = $slotIds === [] ? 0 : $this->bottleMapper->clearSlotForSlotIds($slotIds);
			$this->slotMapper->deleteByCompartment($compartmentId);

			$comp->setLevels($levels);
			$comp->setColumnsFront($columnsFront);
			$comp->setColumnsBack($columnsBack);
			$this->compartmentMapper->update($comp);

			$this->createSlotsForCompartment($compartmentId, $levels, $columnsFront, $columnsBack);

			$this->db->commit();
			return $movedBottles;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	private function createSlotsForCompartment(
		int $compartmentId,
		int $levels,
		int $columnsFront,
		int $columnsBack,
	): void {
		for ($level = 0; $level < $levels; $level++) {
			for ($col = 0; $col < $columnsFront; $col++) {
				$this->insertSlot($compartmentId, $level, 'front', $col);
			}
			for ($col = 0; $col < $columnsBack; $col++) {
				$this->insertSlot($compartmentId, $level, 'back', $col);
			}
		}
	}

	private function insertSlot(int $compartmentId, int $level, string $row, int $col): void {
		$slot = new Slot();
		$slot->setCompartmentId($compartmentId);
		$slot->setLevel($level);
		$slot->setRow($row);
		$slot->setColumn($col);
		$this->slotMapper->insert($slot);
	}

	private function assertCompartmentOwnership(Compartment $comp, string $userId): void {
		try {
			$shelf = $this->shelfMapper->find($comp->getShelfId());
			$cellar = $this->cellarMapper->find($shelf->getCellarId());
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Compartment hierarchy incomplete', 0, $e);
		}
		if ($cellar->getOwnerUserId() !== $userId) {
			throw new PermissionDeniedException('Compartment not owned by user');
		}
	}
}
