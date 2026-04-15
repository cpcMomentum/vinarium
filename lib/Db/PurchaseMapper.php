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
 * @template-extends QBMapper<Purchase>
 */
class PurchaseMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_purchase', Purchase::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Purchase {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Purchase[] */
	public function findByVintage(int $vintageId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('vintage_id', $qb->createNamedParameter($vintageId, IQueryBuilder::PARAM_INT)))
			->orderBy('purchased_at', 'DESC');
		return $this->findEntities($qb);
	}
}
