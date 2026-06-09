<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;

class WineService {

	public function __construct(
		private readonly WineMapper $wineMapper,
		private readonly ProducerService $producerService,
	) {
	}

	/** @return Wine[] */
	public function listByProducer(int $producerId, string $userId): array {
		$this->producerService->get($producerId, $userId);
		return $this->wineMapper->findByProducer($producerId);
	}

	public function get(int $id, string $userId): Wine {
		$wine = $this->findWine($id);
		$this->producerService->get($wine->getProducerId(), $userId);
		return $wine;
	}

	public function create(string $userId, int $producerId, string $name, string $color, array $data = []): Wine {
		$this->producerService->get($producerId, $userId);
		$this->assertValidColor($color);

		$wine = new Wine();
		$wine->setProducerId($producerId);
		$wine->setName($name);
		$wine->setColor($color);
		$wine->setAppellation($data['appellation'] ?? null);
		$wine->setNotes($data['notes'] ?? null);
		$wine->setBarcode($data['barcode'] ?? null);
		return $this->wineMapper->insert($wine);
	}

	public function update(int $id, string $userId, array $data): Wine {
		$wine = $this->get($id, $userId);
		// Accept both flat and nested ({ data: { ... } }) payloads.
		$fields = $data;
		if (isset($data['data']) && is_array($data['data'])) {
			$fields = $data['data'] + $fields;
		}
		if (array_key_exists('name', $fields)) {
			$wine->setName((string)$fields['name']);
		}
		if (array_key_exists('color', $fields)) {
			$color = (string)$fields['color'];
			$this->assertValidColor($color);
			$wine->setColor($color);
		}
		if (array_key_exists('appellation', $fields)) {
			$wine->setAppellation($fields['appellation'] !== null ? (string)$fields['appellation'] : null);
		}
		if (array_key_exists('notes', $fields)) {
			$wine->setNotes($fields['notes'] !== null ? (string)$fields['notes'] : null);
		}
		if (array_key_exists('barcode', $fields)) {
			$wine->setBarcode($fields['barcode'] !== null ? (string)$fields['barcode'] : null);
		}
		return $this->wineMapper->update($wine);
	}

	public function delete(int $id, string $userId): Wine {
		$wine = $this->get($id, $userId);
		return $this->wineMapper->delete($wine);
	}

	private function findWine(int $id): Wine {
		try {
			return $this->wineMapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Wine not found', 0, $e);
		}
	}

	private function assertValidColor(string $color): void {
		if (!in_array($color, Wine::COLORS, true)) {
			throw new ValidationException('Invalid wine color: ' . $color);
		}
	}
}
