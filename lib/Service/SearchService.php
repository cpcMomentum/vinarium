<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Cross-entity full-text search over producers, wines and vintages.
 * Owner-scoped via the producer relation (wine → producer, vintage → wine → producer).
 */
class SearchService {

	private const MIN_QUERY_LENGTH = 2;
	private const MAX_RESULTS = 20;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return list<array{type: string, id: int, label: string, sub: string, count: ?int}>
	 */
	public function search(string $userId, string $query): array {
		$query = trim($query);
		if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
			return [];
		}

		$results = array_merge(
			$this->searchProducers($userId, $query),
			$this->searchWines($userId, $query),
			$this->searchVintages($userId, $query),
		);

		// Relevance: exact label match first, then prefix, then substring; alpha within.
		usort($results, function (array $a, array $b) use ($query): int {
			$sa = $this->relevance($a['label'], $query);
			$sb = $this->relevance($b['label'], $query);
			if ($sa !== $sb) {
				return $sa <=> $sb;
			}
			return strcasecmp($a['label'], $b['label']);
		});

		return array_slice($results, 0, self::MAX_RESULTS);
	}

	private function relevance(string $label, string $query): int {
		$l = mb_strtolower($label);
		$q = mb_strtolower($query);
		if ($l === $q) {
			return 0;
		}
		if (str_starts_with($l, $q)) {
			return 1;
		}
		return 2;
	}

	private function like(string $query): string {
		return '%' . $this->db->escapeLikeParameter($query) . '%';
	}

	/**
	 * @return list<array{type: string, id: int, label: string, sub: string, count: ?int}>
	 */
	private function searchProducers(string $userId, string $query): array {
		$qb = $this->db->getQueryBuilder();
		$like = $this->like($query);
		$qb->select('p.id', 'p.name', 'p.region', 'p.country')
			->from('vinarium_producer', 'p')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('p.name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('p.region', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('p.country', $qb->createNamedParameter($like)),
			))
			->orderBy('p.name', 'ASC')
			->setMaxResults(self::MAX_RESULTS);
		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$rows[] = [
				'type' => 'producer',
				'id' => (int)$row['id'],
				'label' => (string)$row['name'],
				'sub' => $this->joinParts([$row['region'] ?? null, $row['country'] ?? null]),
				'count' => null,
			];
		}
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @return list<array{type: string, id: int, label: string, sub: string, count: ?int}>
	 */
	private function searchWines(string $userId, string $query): array {
		$qb = $this->db->getQueryBuilder();
		$like = $this->like($query);
		$qb->select('w.id', 'w.name', 'w.appellation', 'p.name AS producer_name')
			->from('vinarium_wine', 'w')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->iLike('w.name', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('w.appellation', $qb->createNamedParameter($like)),
				$qb->expr()->iLike('w.barcode', $qb->createNamedParameter($like)),
			))
			->orderBy('w.name', 'ASC')
			->setMaxResults(self::MAX_RESULTS);
		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$rows[] = [
				'type' => 'wine',
				'id' => (int)$row['id'],
				'label' => (string)$row['name'],
				'sub' => $this->joinParts([$row['producer_name'] ?? null, $row['appellation'] ?? null]),
				'count' => null,
			];
		}
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @return list<array{type: string, id: int, label: string, sub: string, count: ?int}>
	 */
	private function searchVintages(string $userId, string $query): array {
		$qb = $this->db->getQueryBuilder();
		$like = $this->like($query);

		// grape varieties (substring) plus the year as an exact match for numeric queries
		$ors = [$qb->expr()->iLike('v.grape_varieties', $qb->createNamedParameter($like))];
		if (ctype_digit($query)) {
			$ors[] = $qb->expr()->eq('v.year', $qb->createNamedParameter((int)$query, IQueryBuilder::PARAM_INT));
		}

		$qb->select('v.id', 'v.year', 'w.name AS wine_name', 'p.name AS producer_name')
			->selectAlias($qb->func()->count('b.id'), 'bottle_count')
			->from('vinarium_vintage', 'v')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->leftJoin('v', 'vinarium_purchase', 'pu', 'pu.vintage_id = v.id')
			->leftJoin('pu', 'vinarium_bottle', 'b',
				'b.purchase_id = pu.id AND b.status = ' . $qb->createNamedParameter('in_storage'))
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->orX(...$ors))
			->groupBy('v.id', 'v.year', 'w.name', 'p.name')
			->orderBy('w.name', 'ASC')
			->setMaxResults(self::MAX_RESULTS);
		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$rows[] = [
				'type' => 'vintage',
				'id' => (int)$row['id'],
				'label' => trim(((string)$row['wine_name']) . ' ' . ((int)$row['year'])),
				'sub' => (string)($row['producer_name'] ?? ''),
				'count' => (int)$row['bottle_count'],
			];
		}
		$result->closeCursor();
		return $rows;
	}

	/**
	 * @param array<?string> $parts
	 */
	private function joinParts(array $parts): string {
		$clean = array_filter(
			array_map(static fn($p) => $p !== null ? trim((string)$p) : '', $parts),
			static fn(string $p) => $p !== '',
		);
		return implode(' · ', $clean);
	}
}
