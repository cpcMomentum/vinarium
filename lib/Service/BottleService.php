<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\Purchase;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Exception\SlotOccupiedException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use Throwable;

class BottleService {

	public function __construct(
		private readonly BottleMapper $bottleMapper,
		private readonly SlotMapper $slotMapper,
		private readonly CompartmentMapper $compartmentMapper,
		private readonly ShelfMapper $shelfMapper,
		private readonly CellarMapper $cellarMapper,
		private readonly PurchaseService $purchaseService,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * Bulk-create N bottles for a purchase, all parked (slot_id = NULL, status = in_storage).
	 *
	 * @return Bottle[]
	 */
	public function createBottlesForPurchase(int $purchaseId, string $userId): array {
		$purchase = $this->purchaseService->get($purchaseId, $userId);
		$count = $purchase->getQuantity();

		$this->db->beginTransaction();
		try {
			$bottles = [];
			for ($i = 0; $i < $count; $i++) {
				$bottle = new Bottle();
				$bottle->setPurchaseId($purchase->getId());
				$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
				$bottles[] = $this->bottleMapper->insert($bottle);
			}
			$this->db->commit();
			return $bottles;
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function get(int $id, string $userId): Bottle {
		$bottle = $this->findBottle($id);
		$this->purchaseService->get($bottle->getPurchaseId(), $userId);
		return $bottle;
	}

	public function moveBottle(int $bottleId, ?int $slotId, string $userId): Bottle {
		$bottle = $this->get($bottleId, $userId);

		if ($slotId !== null) {
			$this->assertSlotOwnership($slotId, $userId);
			if ($this->bottleMapper->isSlotOccupied($slotId, $bottleId)) {
				throw new SlotOccupiedException(sprintf('Slot %d is already occupied', $slotId));
			}
		}

		$bottle->setSlotId($slotId);
		return $this->bottleMapper->update($bottle);
	}

	public function consumeBottle(int $id, string $userId): Bottle {
		$bottle = $this->get($id, $userId);
		$bottle->setStatus(Bottle::STATUS_CONSUMED);
		$bottle->setSlotId(null);
		return $this->bottleMapper->update($bottle);
	}

	public function restoreBottle(int $id, string $userId): Bottle {
		$bottle = $this->get($id, $userId);
		$bottle->setStatus(Bottle::STATUS_IN_STORAGE);
		$bottle->setSlotId(null);
		return $this->bottleMapper->update($bottle);
	}

	/**
	 * Swap slot_ids between two bottles (transactional).
	 * @return Bottle[]
	 */
	public function swapBottles(int $bottleAId, int $bottleBId, string $userId): array {
		$a = $this->get($bottleAId, $userId);
		$b = $this->get($bottleBId, $userId);

		$this->db->beginTransaction();
		try {
			$slotA = $a->getSlotId();
			$slotB = $b->getSlotId();
			$a->setSlotId($slotB);
			$b->setSlotId($slotA);
			$this->bottleMapper->update($a);
			$this->bottleMapper->update($b);
			$this->db->commit();
			return [$a, $b];
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return Bottle[] */
	public function getParkedBottles(string $userId): array {
		return $this->bottleMapper->findByOwnerParked($userId);
	}

	/** @return array<int, array<string, mixed>> */
	public function getFilteredBottles(string $userId, array $filter = []): array {
		return $this->bottleMapper->findFilteredByOwner($userId, $filter);
	}

	/** @return array<string, mixed> Fully denormalized bottle detail */
	public function getDetails(int $id, string $userId): array {
		$row = $this->bottleMapper->findDetails($id, $userId);
		if ($row === null) {
			throw new NotFoundException('Bottle not found');
		}
		return [
			'id' => (int)$row['id'],
			'purchase_id' => (int)$row['purchase_id'],
			'slot_id' => $row['slot_id'] !== null ? (int)$row['slot_id'] : null,
			'status' => $row['status'],
			'notes' => $row['notes'],
			'wine_name' => $row['wine_name'],
			'wine_color' => $row['wine_color'],
			'appellation' => $row['appellation'],
			'producer_name' => $row['producer_name'],
			'year' => (int)$row['year'],
			'grape_varieties' => $row['grape_varieties'],
			'drink_from_year' => $row['drink_from_year'] !== null ? (int)$row['drink_from_year'] : null,
			'drink_until_year' => $row['drink_until_year'] !== null ? (int)$row['drink_until_year'] : null,
			'alcohol_percent' => $row['alcohol_percent'] !== null ? (float)$row['alcohol_percent'] : null,
			'external_rating' => $row['external_rating'] !== null ? (float)$row['external_rating'] : null,
			'external_rating_source' => $row['external_rating_source'],
			'purchased_at' => $row['purchased_at'],
			'vendor' => $row['vendor'],
			'unit_price' => $row['unit_price'] !== null ? (float)$row['unit_price'] : null,
			'currency' => $row['currency'],
			'bottle_size_ml' => (int)$row['bottle_size_ml'],
			'slot_level' => $row['slot_level'] !== null ? (int)$row['slot_level'] : null,
			'slot_row' => $row['slot_row'],
			'slot_column' => $row['slot_column'] !== null ? (int)$row['slot_column'] : null,
			'compartment_label' => $row['compartment_label'],
		];
	}

	public function delete(int $id, string $userId): Bottle {
		$bottle = $this->get($id, $userId);
		return $this->bottleMapper->delete($bottle);
	}

	private function assertSlotOwnership(int $slotId, string $userId): void {
		try {
			$slot = $this->slotMapper->find($slotId);
			$comp = $this->compartmentMapper->find($slot->getCompartmentId());
			$shelf = $this->shelfMapper->find($comp->getShelfId());
			$cellar = $this->cellarMapper->find($shelf->getCellarId());
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Slot hierarchy incomplete', 0, $e);
		}
		if ($cellar->getOwnerUserId() !== $userId) {
			throw new PermissionDeniedException('Slot not owned by user');
		}
	}

	private function findBottle(int $id): Bottle {
		try {
			return $this->bottleMapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Bottle not found', 0, $e);
		}
	}
}
