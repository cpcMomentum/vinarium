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
 * @template-extends QBMapper<Tasting>
 */
class TastingMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_tasting', Tasting::class);
	}

	/** @throws DoesNotExistException */
	public function find(int $id): Tasting {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		return $this->findEntity($qb);
	}

	/** @return array<int, array<string, mixed>> denormalized with wine info */
	public function findAllByOwner(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			't.id', 't.bottle_id', 't.tasted_at', 't.rating', 't.notes', 't.occasion', 't.companions',
			'w.name AS wine_name', 'w.color AS wine_color',
			'v.year', 'p.name AS producer_name',
		)
			->from($this->tableName, 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('t.tasted_at', 'DESC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** @return Tasting[] */
	public function findByBottle(int $bottleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('bottle_id', $qb->createNamedParameter($bottleId, IQueryBuilder::PARAM_INT)))
			->orderBy('tasted_at', 'DESC');
		return $this->findEntities($qb);
	}
}
