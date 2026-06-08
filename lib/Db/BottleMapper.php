<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Bottle>
 */
class BottleMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_bottle', Bottle::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Bottle {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Bottle[] */
	public function findByPurchase(int $purchaseId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('purchase_id', $qb->createNamedParameter($purchaseId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC');
		return $this->findEntities($qb);
	}

	/** @return Bottle[] */
	public function findBySlotIds(array $slotIds): array {
		if (count($slotIds) === 0) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $this->findEntities($qb);
	}

	/** @return Bottle[] */
	public function findByOwnerParked(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.*')
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNull('b.slot_id'))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('in_storage')));
		return $this->findEntities($qb);
	}

	/** @return list<string> distinct gift recipients for the user */
	public function findGiftRecipients(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('b.event_recipient')
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('gifted')))
			->andWhere($qb->expr()->isNotNull('b.event_recipient'))
			->orderBy('b.event_recipient', 'ASC');
		$result = $qb->executeQuery();
		$recipients = [];
		while ($row = $result->fetch()) {
			$recipients[] = (string)$row['event_recipient'];
		}
		$result->closeCursor();
		return $recipients;
	}

	/** @return array<int, array<string, mixed>> raw denormalized rows */
	public function findFilteredByOwner(string $userId, array $filter = []): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'b.id', 'b.purchase_id', 'b.slot_id', 'b.status', 'b.photo_file_id', 'b.notes',
			'b.event_date', 'b.event_recipient', 'b.event_note',
			'v.id AS vintage_id', 'v.year',
			'w.id AS wine_id', 'w.name AS wine_name', 'w.color AS wine_color',
			'p.id AS producer_id', 'p.name AS producer_name',
			'v.drink_until_year',
			'sl.level AS slot_level', 'sl.row AS slot_row', 'sl.column AS slot_column',
			'co.label AS compartment_label',
		)
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->leftJoin('b', 'vinarium_slot', 'sl', 'b.slot_id = sl.id')
			->leftJoin('sl', 'vinarium_compartment', 'co', 'sl.compartment_id = co.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));

		if (isset($filter['status'])) {
			$qb->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter((string)$filter['status'])));
		}
		if (isset($filter['color'])) {
			$qb->andWhere($qb->expr()->eq('w.color', $qb->createNamedParameter((string)$filter['color'])));
		}
		if (isset($filter['year'])) {
			$qb->andWhere($qb->expr()->eq('v.year', $qb->createNamedParameter((int)$filter['year'], IQueryBuilder::PARAM_INT)));
		}
		if (isset($filter['producerId'])) {
			$qb->andWhere($qb->expr()->eq('p.id', $qb->createNamedParameter((int)$filter['producerId'], IQueryBuilder::PARAM_INT)));
		}
		if (isset($filter['drinkUntilYearBefore'])) {
			$qb->andWhere($qb->expr()->lte('v.drink_until_year', $qb->createNamedParameter((int)$filter['drinkUntilYearBefore'], IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('p.name', 'ASC')->addOrderBy('w.name', 'ASC')->addOrderBy('v.year', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/**
	 * Average rating per vintage_id for an owner — supplies the bottle list with
	 * an avg_rating per (wine × vintage) without N+1 queries.
	 * @return array<int, float> map vintage_id => avg_rating
	 */
	public function avgRatingByVintageForOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('v.id')
			->selectAlias($qb->createFunction('AVG(t.rating)'), 'avg_rating')
			->from('vinarium_tasting', 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNotNull('t.rating'))
			->groupBy('v.id');
		$result = $qb->executeQuery();
		$map = [];
		while ($row = $result->fetch()) {
			$map[(int)$row['id']] = (float)$row['avg_rating'];
		}
		$result->closeCursor();
		return $map;
	}

	/**
	 * Returns the vintage_id of the bottle if it belongs to the user, else null.
	 * Used as the lookup key for propagating photos to all bottles of the same wine × vintage.
	 */
	public function findVintageIdForOwner(int $bottleId, string $userId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('pu.vintage_id')
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('b.id', $qb->createNamedParameter($bottleId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ? (int)$row['vintage_id'] : null;
	}

	/**
	 * Returns one existing photo_file_id for a vintage of the owner, or null if none.
	 * Used when a new purchase is created to inherit the photo of pre-existing siblings.
	 */
	public function findExistingPhotoForVintage(int $vintageId, string $userId): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.photo_file_id')
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('pu.vintage_id', $qb->createNamedParameter($vintageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNotNull('b.photo_file_id'))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ? (int)$row['photo_file_id'] : null;
	}

	/**
	 * Sets photo_file_id on every bottle of the given vintage (and owner) — except the
	 * source bottle — to $newFileId. Per design ("ein Foto teilen sich alle Flaschen
	 * dieser Vintage"), an upload on any bottle wins for the whole vintage. The source
	 * bottle is already updated by the controller before this method is called.
	 *
	 * Also returns the list of distinct previous photo_file_ids that were displaced,
	 * so the caller can decide which physical files are now orphaned.
	 *
	 * @return array{updated: int, displaced_file_ids: list<int>}
	 */
	public function propagatePhotoToVintageSiblings(int $sourceBottleId, int $vintageId, string $userId, int $newFileId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'b.photo_file_id')
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('pu.vintage_id', $qb->createNamedParameter($vintageId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->neq('b.id', $qb->createNamedParameter($sourceBottleId, IQueryBuilder::PARAM_INT)));

		$result = $qb->executeQuery();
		$ids = [];
		$displacedFileIds = [];
		while ($row = $result->fetch()) {
			$bottleId = (int)$row['id'];
			$ids[] = $bottleId;
			if ($row['photo_file_id'] !== null) {
				$prev = (int)$row['photo_file_id'];
				if ($prev !== $newFileId && !in_array($prev, $displacedFileIds, true)) {
					$displacedFileIds[] = $prev;
				}
			}
		}
		$result->closeCursor();
		if ($ids === []) {
			return ['updated' => 0, 'displaced_file_ids' => []];
		}

		$update = $this->db->getQueryBuilder();
		$update->update($this->tableName)
			->set('photo_file_id', $update->createNamedParameter($newFileId, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('id', $update->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$updated = (int)$update->executeStatement();
		return ['updated' => $updated, 'displaced_file_ids' => $displacedFileIds];
	}

	/**
	 * Counts how many bottles of $userId still reference $fileId. Used to decide whether
	 * a photo file in storage is now orphaned and may be physically deleted.
	 */
	public function countBottlesReferencingPhoto(int $fileId, string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('b.id'))
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('b.photo_file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** @return array<string, mixed>|null Fully denormalized bottle row with wine/vintage/producer/purchase/slot info */
	public function findDetails(int $id, string $userId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'b.id', 'b.purchase_id', 'b.slot_id', 'b.status', 'b.photo_file_id', 'b.notes',
			'b.event_date', 'b.event_recipient', 'b.event_note',
			'v.year', 'v.grape_varieties', 'v.drink_from_year', 'v.drink_until_year',
			'v.alcohol_percent', 'v.external_rating', 'v.external_rating_source',
			'w.name AS wine_name', 'w.color AS wine_color', 'w.appellation',
			'p.name AS producer_name',
			'pu.purchased_at', 'pu.vendor', 'pu.unit_price', 'pu.currency', 'pu.bottle_size_ml',
			'sl.level AS slot_level', 'sl.row AS slot_row', 'sl.column AS slot_column',
			'co.label AS compartment_label',
		)
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->leftJoin('b', 'vinarium_slot', 'sl', 'b.slot_id = sl.id')
			->leftJoin('sl', 'vinarium_compartment', 'co', 'sl.compartment_id = co.id')
			->where($qb->expr()->eq('b.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ?: null;
	}

	public function isSlotOccupied(int $slotId, ?int $excludeBottleId = null): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('id'))
			->from($this->tableName)
			->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('in_storage')));
		if ($excludeBottleId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeBottleId, IQueryBuilder::PARAM_INT)));
		}
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count > 0;
	}

	public function clearSlotForSlotIds(array $slotIds): int {
		if (count($slotIds) === 0) {
			return 0;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('slot_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
			->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)));
		return $qb->executeStatement();
	}
}
