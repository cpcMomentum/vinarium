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
 * @template-extends QBMapper<Vintage>
 */
class VintageMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_vintage', Vintage::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(int $id): Vintage {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return Vintage[] */
	public function findByWine(int $wineId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('wine_id', $qb->createNamedParameter($wineId, IQueryBuilder::PARAM_INT)))
			->orderBy('year', 'DESC');
		return $this->findEntities($qb);
	}

	/**
	 * How many of the owner's vintages still point at the given photo file, on
	 * either side. A result of 0 means the file in storage is orphaned and may be
	 * physically deleted.
	 */
	public function countVintagesReferencingPhoto(int $fileId, string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$param = $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT);
		$qb->select($qb->func()->count('v.id'))
			->from($this->tableName, 'v')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->orX(
				$qb->expr()->eq('v.photo_front_file_id', $param),
				$qb->expr()->eq('v.photo_back_file_id', $param)
			))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}
}
