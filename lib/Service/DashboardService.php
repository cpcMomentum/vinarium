<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DashboardService {

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function getStats(string $userId): array {
		return [
			'totalBottles' => $this->countBottles($userId),
			'inStorage' => $this->countBottles($userId, 'in_storage'),
			'consumed' => $this->countBottles($userId, 'consumed'),
			'parked' => $this->countParked($userId),
			'colorDistribution' => $this->colorDistribution($userId),
			'drinkSoon' => $this->drinkSoon($userId),
			'recentTastings' => $this->recentTastings($userId),
		];
	}

	private function countBottles(string $userId, ?string $status = null): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('b.id'))
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)));
		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter($status)));
		}
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function countParked(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('b.id'))
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->isNull('b.slot_id'))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('in_storage')));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	/** @return array<string, int> */
	private function colorDistribution(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('w.color')
			->selectAlias($qb->func()->count('b.id'), 'cnt')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('in_storage')))
			->groupBy('w.color');
		$result = $qb->executeQuery();
		$dist = [];
		while ($row = $result->fetch()) {
			$dist[(string)$row['color']] = (int)$row['cnt'];
		}
		$result->closeCursor();
		return $dist;
	}

	/** @return list<array<string, mixed>> vintages with drink_until_year approaching */
	private function drinkSoon(string $userId): array {
		$currentYear = (int)date('Y');
		$qb = $this->db->getQueryBuilder();
		$qb->select('w.name AS wine_name', 'v.year', 'v.drink_until_year', 'p.name AS producer_name')
			->selectAlias($qb->func()->count('b.id'), 'bottle_count')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('in_storage')))
			->andWhere($qb->expr()->isNotNull('v.drink_until_year'))
			->andWhere($qb->expr()->lte('v.drink_until_year', $qb->createNamedParameter($currentYear + 1, IQueryBuilder::PARAM_INT)))
			->groupBy('w.name', 'v.year', 'v.drink_until_year', 'p.name')
			->orderBy('v.drink_until_year', 'ASC')
			->setMaxResults(10);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	/** @return list<array<string, mixed>> */
	private function recentTastings(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.tasted_at', 't.rating', 't.notes', 'w.name AS wine_name', 'v.year', 'p.name AS producer_name')
			->from('vinarium_tasting', 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('t.tasted_at', 'DESC')
			->setMaxResults(5);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}
}
