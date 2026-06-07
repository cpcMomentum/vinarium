<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use DateTime;
use OCA\Vinarium\Db\Purchase;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;

class PurchaseService {

	public const ALLOWED_BOTTLE_SIZES_ML = [375, 500, 750, 1000, 1500, 3000];

	public function __construct(
		private readonly PurchaseMapper $purchaseMapper,
		private readonly VintageService $vintageService,
	) {
	}

	/** @return array<int, array<string, mixed>> */
	public function listAll(string $userId): array {
		return $this->purchaseMapper->findAllByOwner($userId);
	}

	/** @return list<string> */
	public function listVendors(string $userId): array {
		return $this->purchaseMapper->findDistinctVendorsByOwner($userId);
	}

	/** @return Purchase[] */
	public function listByVintage(int $vintageId, string $userId): array {
		$this->vintageService->get($vintageId, $userId);
		return $this->purchaseMapper->findByVintage($vintageId);
	}

	public function get(int $id, string $userId): Purchase {
		$purchase = $this->findPurchase($id);
		$this->vintageService->get($purchase->getVintageId(), $userId);
		return $purchase;
	}

	public function create(string $userId, int $vintageId, array $data): Purchase {
		$this->vintageService->get($vintageId, $userId);
		$this->validate($data);

		$purchase = new Purchase();
		$purchase->setVintageId($vintageId);
		$purchase->setPurchasedAt($this->parseDate($data['purchasedAt'] ?? null) ?? new DateTime());
		$purchase->setVendor(isset($data['vendor']) ? (string)$data['vendor'] : null);
		$purchase->setUnitPrice(isset($data['unitPrice']) && $data['unitPrice'] !== null ? (float)$data['unitPrice'] : null);
		$purchase->setCurrency(isset($data['currency']) ? (string)$data['currency'] : 'EUR');
		$purchase->setQuantity((int)($data['quantity'] ?? 1));
		$purchase->setBottleSizeMl((int)($data['bottleSizeMl'] ?? 750));
		$purchase->setNotes(isset($data['notes']) ? (string)$data['notes'] : null);
		return $this->purchaseMapper->insert($purchase);
	}

	public function update(int $id, string $userId, array $data): Purchase {
		$purchase = $this->get($id, $userId);
		$this->validate($data, partial: true);

		if (array_key_exists('purchasedAt', $data)) {
			$purchase->setPurchasedAt($this->parseDate($data['purchasedAt']) ?? $purchase->getPurchasedAt());
		}
		if (array_key_exists('vendor', $data)) {
			$purchase->setVendor($data['vendor'] !== null ? (string)$data['vendor'] : null);
		}
		if (array_key_exists('unitPrice', $data)) {
			$purchase->setUnitPrice($data['unitPrice'] !== null ? (float)$data['unitPrice'] : null);
		}
		if (array_key_exists('currency', $data)) {
			$purchase->setCurrency(isset($data['currency']) ? (string)$data['currency'] : null);
		}
		if (array_key_exists('quantity', $data)) {
			$purchase->setQuantity((int)$data['quantity']);
		}
		if (array_key_exists('bottleSizeMl', $data)) {
			$purchase->setBottleSizeMl((int)$data['bottleSizeMl']);
		}
		if (array_key_exists('notes', $data)) {
			$purchase->setNotes($data['notes'] !== null ? (string)$data['notes'] : null);
		}
		return $this->purchaseMapper->update($purchase);
	}

	public function delete(int $id, string $userId): Purchase {
		$purchase = $this->get($id, $userId);
		$bottleCount = $this->purchaseMapper->countBottlesForPurchase($id);
		if ($bottleCount > 0) {
			throw new ValidationException(
				"{$bottleCount} Flasche(n) sind diesem Kauf zugeordnet. Erst die Flaschen entsorgen, verschenken oder trinken, bevor der Kauf gelöscht wird."
			);
		}
		return $this->purchaseMapper->delete($purchase);
	}

	private function validate(array $data, bool $partial = false): void {
		if (!$partial || array_key_exists('quantity', $data)) {
			$qty = (int)($data['quantity'] ?? 0);
			if ($qty < 1) {
				throw new ValidationException('quantity must be >= 1');
			}
		}
		if (!$partial || array_key_exists('bottleSizeMl', $data)) {
			$size = (int)($data['bottleSizeMl'] ?? 750);
			if (!in_array($size, self::ALLOWED_BOTTLE_SIZES_ML, true)) {
				throw new ValidationException(sprintf(
					'bottleSizeMl must be one of %s',
					implode(', ', self::ALLOWED_BOTTLE_SIZES_ML),
				));
			}
		}
	}

	private function parseDate(mixed $value): ?DateTime {
		if ($value === null || $value === '') {
			return null;
		}
		try {
			return new DateTime((string)$value);
		} catch (\Exception $e) {
			throw new ValidationException('Invalid purchasedAt date', 0, $e);
		}
	}

	private function findPurchase(int $id): Purchase {
		try {
			return $this->purchaseMapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Purchase not found', 0, $e);
		}
	}
}
