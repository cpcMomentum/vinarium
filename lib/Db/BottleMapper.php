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
