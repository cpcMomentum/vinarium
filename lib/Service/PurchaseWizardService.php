<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Exception\ValidationException;
use OCP\IDBConnection;
use Throwable;

/**
 * Orchestrates the purchase wizard: resolves or creates producer, wine and
 * vintage, then creates the purchase with its bottles — all in one transaction.
 * Nothing is persisted until the wizard is completed.
 */
class PurchaseWizardService {

	public function __construct(
		private readonly ProducerService $producerService,
		private readonly WineService $wineService,
		private readonly VintageService $vintageService,
		private readonly PurchaseService $purchaseService,
		private readonly BottleService $bottleService,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{purchase: mixed, bottles: array}
	 */
	public function create(string $userId, array $payload): array {
		$this->db->beginTransaction();
		try {
			$producerId = $this->resolveProducer($userId, $this->section($payload, 'producer'));
			$wineId = $this->resolveWine($userId, $producerId, $this->section($payload, 'wine'));
			$vintageId = $this->resolveVintage($userId, $wineId, $this->section($payload, 'vintage'));

			$purchaseData = is_array($payload['purchase'] ?? null) ? $payload['purchase'] : [];
			$purchase = $this->purchaseService->create($userId, $vintageId, $purchaseData);
			$bottles = $this->bottleService->createBottlesForPurchase($purchase->getId(), $userId);

			$this->db->commit();
			return ['purchase' => $purchase, 'bottles' => $bottles];
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @param array<string, mixed> $section */
	private function resolveProducer(string $userId, array $section): int {
		$id = $this->existingId($section);
		if ($id !== null) {
			return $id;
		}
		$d = $this->data($section);
		$name = trim((string)($d['name'] ?? ''));
		if ($name === '') {
			throw new ValidationException('Producer name is required');
		}
		return $this->producerService->create(
			$userId, $name,
			$this->nullableString($d, 'country'),
			$this->nullableString($d, 'region'),
			$this->nullableString($d, 'website'),
			$this->nullableString($d, 'notes'),
		)->getId();
	}

	/** @param array<string, mixed> $section */
	private function resolveWine(string $userId, int $producerId, array $section): int {
		$id = $this->existingId($section);
		if ($id !== null) {
			return $id;
		}
		$d = $this->data($section);
		$name = trim((string)($d['name'] ?? ''));
		if ($name === '') {
			throw new ValidationException('Wine name is required');
		}
		return $this->wineService->create($userId, $producerId, $name, (string)($d['color'] ?? 'red'), [
			'appellation' => $this->nullableString($d, 'appellation'),
			'barcode' => $this->nullableString($d, 'barcode'),
			'notes' => $this->nullableString($d, 'notes'),
		])->getId();
	}

	/** @param array<string, mixed> $section */
	private function resolveVintage(string $userId, int $wineId, array $section): int {
		$id = $this->existingId($section);
		if ($id !== null) {
			return $id;
		}
		$d = $this->data($section);
		if (!isset($d['year']) || !is_numeric($d['year'])) {
			throw new ValidationException('Vintage year is required');
		}
		return $this->vintageService->create($userId, $wineId, (int)$d['year'], [
			'alcoholPercent' => $d['alcoholPercent'] ?? null,
			'grapeVarieties' => $this->nullableString($d, 'grapeVarieties'),
			'drinkFromYear' => $d['drinkFromYear'] ?? null,
			'drinkUntilYear' => $d['drinkUntilYear'] ?? null,
			'externalRating' => $d['externalRating'] ?? null,
			'externalRatingSource' => $this->nullableString($d, 'externalRatingSource'),
			'referenceUrl' => $this->nullableString($d, 'referenceUrl'),
			'description' => $this->nullableString($d, 'description'),
		])->getId();
	}

	/** @return array<string, mixed> */
	private function section(array $payload, string $key): array {
		return is_array($payload[$key] ?? null) ? $payload[$key] : [];
	}

	/** @param array<string, mixed> $section */
	private function existingId(array $section): ?int {
		$id = $section['id'] ?? null;
		return is_numeric($id) && (int)$id > 0 ? (int)$id : null;
	}

	/**
	 * @param array<string, mixed> $section
	 * @return array<string, mixed>
	 */
	private function data(array $section): array {
		return is_array($section['data'] ?? null) ? $section['data'] : [];
	}

	/** @param array<string, mixed> $data */
	private function nullableString(array $data, string $key): ?string {
		$value = $data[$key] ?? null;
		if ($value === null) {
			return null;
		}
		$str = trim((string)$value);
		return $str === '' ? null : $str;
	}
}
