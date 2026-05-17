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
			't.photo_file_ids',
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

	/** @return array<string, mixed>|null Full detail row with wine/vintage/producer/purchase info */
	public function findDetails(int $id, string $userId): ?array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			't.id', 't.bottle_id', 't.tasted_at', 't.rating', 't.notes', 't.occasion', 't.companions',
			't.photo_file_ids',
			'w.id AS wine_id', 'w.name AS wine_name', 'w.color AS wine_color',
			'v.id AS vintage_id', 'v.year AS year',
			'p.id AS producer_id', 'p.name AS producer_name',
			'pu.purchased_at', 'pu.vendor', 'pu.unit_price', 'pu.currency', 'pu.bottle_size_ml',
		)
			->from($this->tableName, 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('t.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		return $row ?: null;
	}

	/** @return array<int, array<string, mixed>> Other tastings of the same wine */
	public function findRelatedSameWine(int $tastingId, int $wineId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 't.tasted_at', 't.rating', 't.notes', 'v.year')
			->from($this->tableName, 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('w.id', $qb->createNamedParameter($wineId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('t.id', $qb->createNamedParameter($tastingId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('t.tasted_at', 'DESC')
			->setMaxResults(10);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** @return array<int, array<string, mixed>> Tastings of other wines from the same producer */
	public function findRelatedSameProducer(int $tastingId, int $producerId, int $wineId, string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 't.tasted_at', 't.rating', 't.notes', 'w.name AS wine_name', 'v.year')
			->from($this->tableName, 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.id', $qb->createNamedParameter($producerId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('w.id', $qb->createNamedParameter($wineId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('t.id', $qb->createNamedParameter($tastingId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('t.tasted_at', 'DESC')
			->setMaxResults(10);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}
}
