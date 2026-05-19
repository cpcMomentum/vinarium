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
use OCA\Vinarium\Db\Level;
use OCA\Vinarium\Db\LevelMapper;
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

	public const DEFAULT_COMPARTMENTS = 4;
	public const DEFAULT_LEVELS = 3;
	public const DEFAULT_COLUMNS_FRONT = 6;
	public const DEFAULT_COLUMNS_BACK = 7;

	public function __construct(
		private readonly CellarMapper $cellarMapper,
		private readonly ShelfMapper $shelfMapper,
		private readonly CompartmentMapper $compartmentMapper,
		private readonly LevelMapper $levelMapper,
		private readonly SlotMapper $slotMapper,
		private readonly BottleMapper $bottleMapper,
		private readonly IDBConnection $db,
	) {
	}

	/** Creates a first cellar with one default shelf for a new user. */
	public function createDefaultCellar(string $userId): Cellar {
		$this->db->beginTransaction();
		try {
			$cellar = new Cellar();
			$cellar->setOwnerUserId($userId);
			$cellar->setName('Mein Weinkeller');
			$cellar->setCreatedAt(new DateTime());
			$cellar = $this->cellarMapper->insert($cellar);

			$this->createShelfInternal(
				$cellar->getId(),
				'Regal 1',
				0,
				self::DEFAULT_COMPARTMENTS,
				self::DEFAULT_LEVELS,
				self::DEFAULT_COLUMNS_FRONT,
				self::DEFAULT_COLUMNS_BACK,
			);

			$this->db->commit();
			return $cellar;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Wizard: creates a new named shelf with uniform level config.
	 *
	 * @param array<int, array{columnsFront: int, columnsBack: int|null}> $levelsConfig per-level config
	 */
	public function createShelf(
		string $userId,
		string $name,
		int $compartmentCount,
		array $levelsConfig,
	): Shelf {
		if ($compartmentCount < 1 || $compartmentCount > 20) {
			throw new \InvalidArgumentException('compartmentCount must be 1–20');
		}
		if ($levelsConfig === []) {
			throw new \InvalidArgumentException('levelsConfig must not be empty');
		}

		$this->db->beginTransaction();
		try {
			$cellar = $this->getOrCreateCellar($userId);

			$existingShelves = $this->shelfMapper->findByCellar($cellar->getId());
			$sortOrder = count($existingShelves);

			$shelf = new Shelf();
			$shelf->setCellarId($cellar->getId());
			$shelf->setName($name);
			$shelf->setSortOrder($sortOrder);
			$shelf = $this->shelfMapper->insert($shelf);

			for ($c = 0; $c < $compartmentCount; $c++) {
				$comp = new Compartment();
				$comp->setShelfId($shelf->getId());
				$comp->setLabel('Fach ' . ($c + 1));
				$comp->setSortOrder($c);
				$comp = $this->compartmentMapper->insert($comp);

				foreach ($levelsConfig as $idx => $levelDef) {
					$front = max(0, (int)($levelDef['columnsFront'] ?? 0));
					$back = isset($levelDef['columnsBack']) && $levelDef['columnsBack'] !== null
						? max(0, (int)$levelDef['columnsBack'])
						: null;
					$this->insertLevelWithSlots($comp->getId(), $idx, $front, $back);
				}
			}

			$this->db->commit();
			return $shelf;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** Destroys a shelf and all its compartments, levels and slots. Bottles go to Parkzone. */
	public function destroyShelf(int $shelfId, string $userId): int {
		$this->db->beginTransaction();
		try {
			$shelf = $this->shelfMapper->find($shelfId);
			$cellar = $this->cellarMapper->find($shelf->getCellarId());
			if ($cellar->getOwnerUserId() !== $userId) {
				throw new PermissionDeniedException('Shelf not owned by user');
			}

			$compartments = $this->compartmentMapper->findByShelf($shelfId);
			$movedBottles = 0;
			foreach ($compartments as $comp) {
				$movedBottles += $this->wipeCompartment($comp->getId());
				$this->compartmentMapper->delete($comp);
			}
			$this->shelfMapper->delete($shelf);

			$this->db->commit();
			return $movedBottles;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return array{cellar: Cellar, shelves: list<array{shelf: Shelf, compartments: list<array{compartment: Compartment, levels: Level[]}>}>} */
	public function getActiveCellar(string $userId): array {
		$cellars = $this->cellarMapper->findByOwner($userId);
		if ($cellars === []) {
			throw new NotFoundException('No cellar for user');
		}
		$cellar = $cellars[0];

		$shelves = $this->shelfMapper->findByCellar($cellar->getId());
		$result = [];
		foreach ($shelves as $shelf) {
			$compartments = $this->compartmentMapper->findByShelf($shelf->getId());
			$compData = [];
			foreach ($compartments as $comp) {
				$compData[] = [
					'compartment' => $comp,
					'levels' => $this->levelMapper->findByCompartment($comp->getId()),
				];
			}
			$result[] = ['shelf' => $shelf, 'compartments' => $compData];
		}

		return ['cellar' => $cellar, 'shelves' => $result];
	}

	/**
	 * Appends a new compartment to an existing shelf with the given level config.
	 *
	 * @param array<int, array{columnsFront: int, columnsBack: int|null}> $levelsConfig
	 */
	public function addCompartmentToShelf(
		int $shelfId,
		string $userId,
		array $levelsConfig,
		?string $label = null,
	): Compartment {
		if ($levelsConfig === []) {
			throw new \InvalidArgumentException('levelsConfig must not be empty');
		}

		$this->db->beginTransaction();
		try {
			$shelf = $this->shelfMapper->find($shelfId);
			$cellar = $this->cellarMapper->find($shelf->getCellarId());
			if ($cellar->getOwnerUserId() !== $userId) {
				throw new PermissionDeniedException('Shelf not owned by user');
			}

			$existing = $this->compartmentMapper->findByShelf($shelfId);
			$sortOrder = count($existing);

			$comp = new Compartment();
			$comp->setShelfId($shelfId);
			$comp->setLabel($label !== null && $label !== '' ? $label : 'Fach ' . ($sortOrder + 1));
			$comp->setSortOrder($sortOrder);
			$comp = $this->compartmentMapper->insert($comp);

			foreach ($levelsConfig as $idx => $levelDef) {
				$front = max(0, (int)($levelDef['columnsFront'] ?? 0));
				$back = isset($levelDef['columnsBack']) && $levelDef['columnsBack'] !== null
					? max(0, (int)$levelDef['columnsBack'])
					: null;
				$this->insertLevelWithSlots($comp->getId(), $idx, $front, $back);
			}

			$this->db->commit();
			return $comp;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Destroys a compartment and all its levels/slots. Bottles go to Parkzone.
	 * Returns the number of bottles moved.
	 */
	public function destroyCompartment(int $compartmentId, string $userId): int {
		$this->db->beginTransaction();
		try {
			$comp = $this->compartmentMapper->find($compartmentId);
			$this->assertCompartmentOwnership($comp, $userId);

			$movedBottles = $this->wipeCompartment($compartmentId);
			$this->compartmentMapper->delete($comp);

			$this->db->commit();
			return $movedBottles;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Reconfigures levels for a compartment. Rebuilds all slots.
	 * Returns the number of bottles moved to Parkzone.
	 *
	 * @param array<int, array{columnsFront: int, columnsBack: int|null}> $levelsConfig
	 */
	public function reconfigureCompartment(
		int $compartmentId,
		array $levelsConfig,
		string $userId,
	): int {
		if ($levelsConfig === []) {
			throw new \InvalidArgumentException('levelsConfig must not be empty');
		}

		$this->db->beginTransaction();
		try {
			$comp = $this->compartmentMapper->find($compartmentId);
			$this->assertCompartmentOwnership($comp, $userId);

			$movedBottles = $this->wipeCompartment($compartmentId);

			foreach ($levelsConfig as $idx => $levelDef) {
				$front = max(0, (int)($levelDef['columnsFront'] ?? 0));
				$back = isset($levelDef['columnsBack']) && $levelDef['columnsBack'] !== null
					? max(0, (int)$levelDef['columnsBack'])
					: null;
				$this->insertLevelWithSlots($compartmentId, $idx, $front, $back);
			}

			$this->db->commit();
			return $movedBottles;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	// --- private helpers ---

	private function getOrCreateCellar(string $userId): Cellar {
		$cellars = $this->cellarMapper->findByOwner($userId);
		if ($cellars !== []) {
			return $cellars[0];
		}
		$cellar = new Cellar();
		$cellar->setOwnerUserId($userId);
		$cellar->setName('Mein Weinkeller');
		$cellar->setCreatedAt(new DateTime());
		return $this->cellarMapper->insert($cellar);
	}

	private function createShelfInternal(
		int $cellarId,
		string $name,
		int $sortOrder,
		int $compartmentCount,
		int $levels,
		int $columnsFront,
		?int $columnsBack,
	): Shelf {
		$shelf = new Shelf();
		$shelf->setCellarId($cellarId);
		$shelf->setName($name);
		$shelf->setSortOrder($sortOrder);
		$shelf = $this->shelfMapper->insert($shelf);

		for ($c = 0; $c < $compartmentCount; $c++) {
			$comp = new Compartment();
			$comp->setShelfId($shelf->getId());
			$comp->setLabel('Fach ' . ($c + 1));
			$comp->setSortOrder($c);
			$comp = $this->compartmentMapper->insert($comp);

			for ($l = 0; $l < $levels; $l++) {
				$this->insertLevelWithSlots($comp->getId(), $l, $columnsFront, $columnsBack);
			}
		}

		return $shelf;
	}

	private function insertLevelWithSlots(int $compId, int $levelNumber, int $front, ?int $back): void {
		$level = new Level();
		$level->setCompartmentId($compId);
		$level->setLevelNumber($levelNumber);
		$level->setColumnsFront($front);
		$level->setColumnsBack($back);
		$level->setSortOrder($levelNumber);
		$this->levelMapper->insert($level);

		for ($col = 0; $col < $front; $col++) {
			$this->insertSlot($compId, $levelNumber, 'front', $col);
		}
		if ($back !== null) {
			for ($col = 0; $col < $back; $col++) {
				$this->insertSlot($compId, $levelNumber, 'back', $col);
			}
		}
	}

	/** Clears all slots and levels for a compartment, moves bottles to Parkzone. */
	private function wipeCompartment(int $compartmentId): int {
		$slots = $this->slotMapper->findByCompartment($compartmentId);
		$slotIds = array_map(static fn (Slot $s): int => (int)$s->getId(), $slots);
		$moved = $slotIds !== [] ? $this->bottleMapper->clearSlotForSlotIds($slotIds) : 0;
		$this->slotMapper->deleteByCompartment($compartmentId);
		$this->levelMapper->deleteByCompartment($compartmentId);
		return $moved;
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
