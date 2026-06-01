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
			'gifted' => $this->countBottles($userId, 'gifted'),
			'lost' => $this->countBottles($userId, 'lost'),
			'parked' => $this->countParked($userId),
			'shelfCount' => $this->countShelves($userId),
			'colorDistribution' => $this->colorDistribution($userId),
			'drinkSoon' => $this->drinkSoon($userId),
			'recentTastings' => $this->recentTastings($userId),
			'recentActivity' => $this->recentActivity($userId, 5),
		];
	}

	private function countShelves(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('s.id'))
			->from('vinarium_shelf', 's')
			->innerJoin('s', 'vinarium_cellar', 'c', 's.cellar_id = c.id')
			->where($qb->expr()->eq('c.owner_user_id', $qb->createNamedParameter($userId)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
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

		// Group by wine + vintage + drink_until — slot_label kommt aus erstem Slot der Gruppe (per Aggregat)
		$qb = $this->db->getQueryBuilder();
		$qb->select('w.id AS wine_id', 'w.name AS wine_name', 'w.color AS wine_color',
				'v.year', 'v.drink_until_year', 'p.name AS producer_name')
			->selectAlias($qb->func()->count('b.id'), 'bottle_count')
			->selectAlias($qb->func()->min('sh.name'), 'shelf_name')
			->selectAlias($qb->func()->min('sl.row'), 'slot_row')
			->selectAlias($qb->func()->min('sl.column'), 'slot_column')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->leftJoin('b', 'vinarium_slot', 'sl', 'b.slot_id = sl.id')
			->leftJoin('sl', 'vinarium_compartment', 'co', 'sl.compartment_id = co.id')
			->leftJoin('co', 'vinarium_shelf', 'sh', 'co.shelf_id = sh.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('b.status', $qb->createNamedParameter('in_storage')))
			->andWhere($qb->expr()->isNotNull('v.drink_until_year'))
			->andWhere($qb->expr()->lte('v.drink_until_year', $qb->createNamedParameter($currentYear + 1, IQueryBuilder::PARAM_INT)))
			->groupBy('w.id', 'w.name', 'w.color', 'v.year', 'v.drink_until_year', 'p.name')
			->orderBy('v.drink_until_year', 'ASC')
			->setMaxResults(10);
		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$shelf = $row['shelf_name'] ?? null;
			$slotRow = $row['slot_row'] ?? null;
			$slotCol = $row['slot_column'] ?? null;
			$slotLabel = null;
			if ($shelf !== null && $slotRow !== null && $slotCol !== null) {
				$slotLabel = $shelf . ' ' . $slotRow . $slotCol;
			}
			$rows[] = [
				'wine_id' => (int)$row['wine_id'],
				'wine_name' => $row['wine_name'],
				'wine_color' => $row['wine_color'],
				'producer_name' => $row['producer_name'],
				'year' => (int)$row['year'],
				'drink_until_year' => (int)$row['drink_until_year'],
				'bottle_count' => (int)$row['bottle_count'],
				'slot_label' => $slotLabel,
			];
		}
		$result->closeCursor();
		return $rows;
	}

	/**
	 * Recent activity stream: purchases, tastings, gifted/lost events, merged + sorted DESC.
	 * @return list<array{type: string, date: string, label: string, refs: array<string, mixed>}>
	 */
	private function recentActivity(string $userId, int $limit): array {
		$events = [];

		// Käufe — pro Purchase ein Event mit Anzahl Flaschen
		$qb = $this->db->getQueryBuilder();
		$qb->select('pu.id', 'pu.purchased_at', 'w.id AS wine_id', 'w.name AS wine_name',
				'w.color AS wine_color', 'p.name AS producer_name', 'v.year')
			->selectAlias($qb->func()->count('b.id'), 'bottle_count')
			->from('vinarium_purchase', 'pu')
			->innerJoin('pu', 'vinarium_bottle', 'b', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->groupBy('pu.id', 'pu.purchased_at', 'w.id', 'w.name', 'w.color', 'p.name', 'v.year')
			->orderBy('pu.purchased_at', 'DESC')
			->setMaxResults($limit);
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => 'purchase',
				'date' => $row['purchased_at'],
				'label' => $row['bottle_count'] . '× ' . $row['wine_name'] . ' ' . $row['year'],
				'refs' => [
					'wine_id' => (int)$row['wine_id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
				],
			];
		}
		$result->closeCursor();

		// Verkostungen
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 't.tasted_at', 'w.id AS wine_id', 'w.name AS wine_name',
				'w.color AS wine_color', 'p.name AS producer_name', 'v.year')
			->from('vinarium_tasting', 't')
			->innerJoin('t', 'vinarium_bottle', 'b', 't.bottle_id = b.id')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->orderBy('t.tasted_at', 'DESC')
			->setMaxResults($limit);
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => 'tasting',
				'date' => $row['tasted_at'],
				'label' => $row['wine_name'] . ' ' . $row['year'],
				'refs' => [
					'tasting_id' => (int)$row['id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
				],
			];
		}
		$result->closeCursor();

		// Geschenke / Verluste (Bottle-Status-Events mit event_date)
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'b.status', 'b.event_date', 'b.event_recipient', 'w.name AS wine_name',
				'w.color AS wine_color', 'p.name AS producer_name', 'v.year')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('b.status', $qb->createNamedParameter(['gifted', 'lost'], IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->isNotNull('b.event_date'))
			->orderBy('b.event_date', 'DESC')
			->setMaxResults($limit);
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => $row['status'], // 'gifted' | 'lost'
				'date' => $row['event_date'],
				'label' => $row['wine_name'] . ' ' . $row['year']
					. ($row['event_recipient'] ? ' → ' . $row['event_recipient'] : ''),
				'refs' => [
					'bottle_id' => (int)$row['id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
				],
			];
		}
		$result->closeCursor();

		// Merge + sort DESC by date, limit
		usort($events, fn(array $a, array $b) => strcmp((string)$b['date'], (string)$a['date']));
		return array_slice($events, 0, $limit);
	}

	/** @return list<array<string, mixed>> */
	private function recentTastings(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.tasted_at', 't.rating', 't.notes', 'w.name AS wine_name', 'w.color AS wine_color', 'v.year', 'p.name AS producer_name')
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
