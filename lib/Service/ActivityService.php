<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Exception\ValidationException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Chronological stream over everything that happened in the cellar.
 *
 * Three sources feed it: purchases (one event per purchase, carrying the
 * bottle count), tastings, and bottles that were gifted or lost.
 *
 * Consumed bottles deliberately have no source of their own. Drinking a bottle
 * always goes through TastingService::consumeWithTasting(), which creates a
 * tasting in the same transaction — the tasting *is* the event. The bottle row
 * itself carries no date for consumption (event_date is only written for gifts
 * and losses), so a separate "consumed" source would produce either duplicates
 * or dateless rows.
 */
class ActivityService {

	public const TYPES = ['purchase', 'tasting', 'gifted', 'lost'];

	private const MAX_LIMIT = 200;

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @param array{type?: string, from?: string, to?: string, limit?: int, offset?: int} $filter
	 * @return array{events: list<array<string, mixed>>, hasMore: bool}
	 */
	public function stream(string $userId, array $filter = []): array {
		$type = $filter['type'] ?? null;
		if ($type !== null && $type !== 'all' && !in_array($type, self::TYPES, true)) {
			throw new ValidationException(sprintf(
				'Unknown activity type "%s" (allowed: all, %s)',
				$type,
				implode(', ', self::TYPES),
			));
		}
		$wanted = ($type === null || $type === 'all') ? self::TYPES : [$type];

		$limit = max(1, min(self::MAX_LIMIT, (int)($filter['limit'] ?? 100)));
		$offset = max(0, (int)($filter['offset'] ?? 0));
		$from = $this->normalizeDate($filter['from'] ?? null, 'from');
		$to = $this->normalizeDate($filter['to'] ?? null, 'to');

		// Each source is capped at offset+limit+1: after merging and sorting we
		// only ever need that many rows, and the extra one tells us whether a
		// further page exists. Deep offsets stay linear, which is fine for a
		// personal cellar and avoids a cursor that would have to break ties on
		// identical dates.
		$perSource = $offset + $limit + 1;

		$events = [];
		if (in_array('purchase', $wanted, true)) {
			$events = array_merge($events, $this->purchaseEvents($userId, $from, $to, $perSource));
		}
		if (in_array('tasting', $wanted, true)) {
			$events = array_merge($events, $this->tastingEvents($userId, $from, $to, $perSource));
		}
		$statuses = array_values(array_intersect(['gifted', 'lost'], $wanted));
		if ($statuses !== []) {
			$events = array_merge($events, $this->bottleStatusEvents($userId, $statuses, $from, $to, $perSource));
		}

		usort($events, static fn (array $a, array $b): int => strcmp((string)$b['date'], (string)$a['date']));

		$page = array_slice($events, $offset, $limit);
		return [
			'events' => array_values($page),
			'hasMore' => count($events) > $offset + $limit,
		];
	}

	private function normalizeDate(?string $value, string $field): ?string {
		if ($value === null || $value === '') {
			return null;
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
			throw new ValidationException(sprintf('%s must be a date in the form YYYY-MM-DD', $field));
		}
		return $value;
	}

	/** @return list<array<string, mixed>> */
	private function purchaseEvents(string $userId, ?string $from, ?string $to, int $limit): array {
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
		$this->applyDateWindow($qb, 'pu.purchased_at', $from, $to);

		$result = $qb->executeQuery();
		$events = [];
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => 'purchase',
				'date' => $row['purchased_at'],
				'label' => $row['bottle_count'] . '× ' . $row['wine_name'] . ' ' . $row['year'],
				'refs' => [
					'purchase_id' => (int)$row['id'],
					'wine_id' => (int)$row['wine_id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
				],
			];
		}
		$result->closeCursor();
		return $events;
	}

	/** @return list<array<string, mixed>> */
	private function tastingEvents(string $userId, ?string $from, ?string $to, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('t.id', 't.tasted_at', 't.rating', 'w.id AS wine_id', 'w.name AS wine_name',
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
		$this->applyDateWindow($qb, 't.tasted_at', $from, $to);

		$result = $qb->executeQuery();
		$events = [];
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => 'tasting',
				'date' => $row['tasted_at'],
				'label' => $row['wine_name'] . ' ' . $row['year'],
				'refs' => [
					'tasting_id' => (int)$row['id'],
					'wine_id' => (int)$row['wine_id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
					'rating' => $row['rating'] !== null ? (float)$row['rating'] : null,
				],
			];
		}
		$result->closeCursor();
		return $events;
	}

	/**
	 * @param list<string> $statuses
	 * @return list<array<string, mixed>>
	 */
	private function bottleStatusEvents(string $userId, array $statuses, ?string $from, ?string $to, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('b.id', 'b.status', 'b.event_date', 'b.event_recipient', 'b.event_note',
			'w.id AS wine_id', 'w.name AS wine_name', 'w.color AS wine_color', 'p.name AS producer_name', 'v.year')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->in('b.status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->isNotNull('b.event_date'))
			->orderBy('b.event_date', 'DESC')
			->setMaxResults($limit);
		$this->applyDateWindow($qb, 'b.event_date', $from, $to);

		$result = $qb->executeQuery();
		$events = [];
		while ($row = $result->fetch()) {
			$events[] = [
				'type' => $row['status'],
				'date' => $row['event_date'],
				'label' => $row['wine_name'] . ' ' . $row['year']
					. ($row['event_recipient'] ? ' → ' . $row['event_recipient'] : ''),
				'refs' => [
					'bottle_id' => (int)$row['id'],
					'wine_id' => (int)$row['wine_id'],
					'wine_color' => $row['wine_color'],
					'producer_name' => $row['producer_name'],
					'event_note' => $row['event_note'],
				],
			];
		}
		$result->closeCursor();
		return $events;
	}

	private function applyDateWindow(IQueryBuilder $qb, string $column, ?string $from, ?string $to): void {
		if ($from !== null) {
			$qb->andWhere($qb->expr()->gte($column, $qb->createNamedParameter($from . ' 00:00:00')));
		}
		if ($to !== null) {
			// Inclusive upper bound: the caller passes a day, not a timestamp.
			$qb->andWhere($qb->expr()->lte($column, $qb->createNamedParameter($to . ' 23:59:59')));
		}
	}
}
