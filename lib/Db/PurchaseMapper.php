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

	/** @return array<int, array<string, mixed>> denormalized rows with wine+producer info */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'pu.id', 'pu.vintage_id', 'pu.purchased_at', 'pu.vendor',
			'pu.unit_price', 'pu.currency', 'pu.quantity', 'pu.bottle_size_ml', 'pu.notes',
			'v.year',
			'w.name AS wine_name', 'w.color AS wine_color',
			'p.name AS producer_name',
		)
			->from($this->tableName, 'pu')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('pu.purchased_at', 'DESC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** @return Purchase[] */
	public function findByVintage(int $vintageId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('vintage_id', $qb->createNamedParameter($vintageId, IQueryBuilder::PARAM_INT)))
			->orderBy('purchased_at', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * Returns the sorted, unique list of non-empty vendor names used by the owner.
	 * @return list<string>
	 */
	public function findDistinctVendorsByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('pu.vendor')
			->from($this->tableName, 'pu')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNotNull('pu.vendor'))
			->andWhere($qb->expr()->neq('pu.vendor', $qb->createNamedParameter('')))
			->orderBy('pu.vendor', 'ASC');
		$result = $qb->executeQuery();
		$vendors = [];
		while ($row = $result->fetch()) {
			$vendors[] = (string)$row['vendor'];
		}
		$result->closeCursor();
		return $vendors;
	}
}
