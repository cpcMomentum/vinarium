<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCP\IDBConnection;

class ExportService {

	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function exportCsv(string $userId): string {
		$rows = $this->fetchDenormalized($userId);

		$out = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
		$out .= implode(';', [
			'Weingut', 'Wein', 'Farbe', 'Jahrgang', 'Rebsorten',
			'Trinken ab', 'Trinken bis', 'Kaufdatum', 'Händler',
			'Preis', 'Währung', 'Flaschengröße (ml)',
			'Status', 'Bewertung', 'Notizen',
		]) . "\n";

		foreach ($rows as $row) {
			$out .= implode(';', [
				$this->esc($row['producer_name']),
				$this->esc($row['wine_name']),
				$this->esc($row['wine_color']),
				$row['year'],
				$this->esc($row['grape_varieties'] ?? ''),
				$row['drink_from_year'] ?? '',
				$row['drink_until_year'] ?? '',
				$this->esc($row['purchased_at'] ?? ''),
				$this->esc($row['vendor'] ?? ''),
				$row['unit_price'] ?? '',
				$this->esc($row['currency'] ?? ''),
				$row['bottle_size_ml'] ?? '',
				$this->esc($row['status']),
				$row['rating'] ?? '',
				$this->esc($row['bottle_notes'] ?? ''),
			]) . "\n";
		}

		return $out;
	}

	/** @return list<array<string, mixed>> */
	private function fetchDenormalized(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'p.name AS producer_name',
			'w.name AS wine_name', 'w.color AS wine_color',
			'v.year', 'v.grape_varieties', 'v.drink_from_year', 'v.drink_until_year',
			'pu.purchased_at', 'pu.vendor', 'pu.unit_price', 'pu.currency', 'pu.bottle_size_ml',
			'b.status', 'b.notes AS bottle_notes',
		)
			->selectAlias($qb->func()->max('t.rating'), 'rating')
			->from('vinarium_bottle', 'b')
			->innerJoin('b', 'vinarium_purchase', 'pu', 'b.purchase_id = pu.id')
			->innerJoin('pu', 'vinarium_vintage', 'v', 'pu.vintage_id = v.id')
			->innerJoin('v', 'vinarium_wine', 'w', 'v.wine_id = w.id')
			->innerJoin('w', 'vinarium_producer', 'p', 'w.producer_id = p.id')
			->leftJoin('b', 'vinarium_tasting', 't', $qb->expr()->eq('t.bottle_id', 'b.id'))
			->where($qb->expr()->eq('p.owner_user_id', $qb->createNamedParameter($userId)))
			->groupBy(
				'b.id', 'b.status', 'b.notes',
				'p.name',
				'w.name', 'w.color',
				'v.year', 'v.grape_varieties', 'v.drink_from_year', 'v.drink_until_year',
				'pu.purchased_at', 'pu.vendor', 'pu.unit_price', 'pu.currency', 'pu.bottle_size_ml',
			)
			->orderBy('p.name', 'ASC')
			->addOrderBy('w.name', 'ASC')
			->addOrderBy('v.year', 'DESC');
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	private function esc(?string $value): string {
		if ($value === null || $value === '') {
			return '';
		}
		if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
			return '"' . str_replace('"', '""', $value) . '"';
		}
		return $value;
	}
}
