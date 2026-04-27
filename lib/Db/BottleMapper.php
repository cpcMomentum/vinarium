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

	/** @return array<int, array<string, mixed>> raw denormalized rows */
	public function findFilteredByOwner(string $userId, array $filter = []): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'b.id', 'b.purchase_id', 'b.slot_id', 'b.status', 'b.photo_file_id', 'b.notes',
			'v.year',
			'w.name AS wine_name', 'w.color AS wine_color',
			'p.name AS producer_name',
			'v.drink_until_year',
		)
			->from($this->tableName, 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
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
		if (isset($filter['drinkUntilYearBefore'])) {
			$qb->andWhere($qb->expr()->lte('v.drink_until_year', $qb->createNamedParameter((int)$filter['drinkUntilYearBefore'], IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('p.name', 'ASC')->addOrderBy('w.name', 'ASC')->addOrderBy('v.year', 'DESC');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
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
