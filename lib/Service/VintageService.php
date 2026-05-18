<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;

class VintageService {

	private const MIN_YEAR = 1900;
	private const MAX_YEAR_OFFSET = 2;
	private const MAX_DRINK_YEAR_OFFSET = 50;

	public function __construct(
		private readonly VintageMapper $vintageMapper,
		private readonly WineService $wineService,
	) {
	}

	/** @return Vintage[] */
	public function listByWine(int $wineId, string $userId): array {
		$this->wineService->get($wineId, $userId);
		return $this->vintageMapper->findByWine($wineId);
	}

	public function get(int $id, string $userId): Vintage {
		$vintage = $this->findVintage($id);
		$this->wineService->get($vintage->getWineId(), $userId);
		return $vintage;
	}

	public function create(string $userId, int $wineId, int $year, array $data = []): Vintage {
		$this->wineService->get($wineId, $userId);
		$this->assertValidYear($year);

		$vintage = new Vintage();
		$vintage->setWineId($wineId);
		$vintage->setYear($year);
		$this->applyOptionalFields($vintage, $data);
		return $this->vintageMapper->insert($vintage);
	}

	public function update(int $id, string $userId, array $data): Vintage {
		$vintage = $this->get($id, $userId);
		if (array_key_exists('year', $data)) {
			$year = (int)$data['year'];
			$this->assertValidYear($year);
			$vintage->setYear($year);
		}
		$this->applyOptionalFields($vintage, $data);
		return $this->vintageMapper->update($vintage);
	}

	public function delete(int $id, string $userId): Vintage {
		$vintage = $this->get($id, $userId);
		return $this->vintageMapper->delete($vintage);
	}

	private function applyOptionalFields(Vintage $vintage, array $data): void {
		if (array_key_exists('alcoholPercent', $data)) {
			$vintage->setAlcoholPercent($data['alcoholPercent'] !== null ? (float)$data['alcoholPercent'] : null);
		}
		if (array_key_exists('grapeVarieties', $data)) {
			$vintage->setGrapeVarieties($data['grapeVarieties'] !== null ? (string)$data['grapeVarieties'] : null);
		}
		if (array_key_exists('drinkFromYear', $data)) {
			$vintage->setDrinkFromYear($data['drinkFromYear'] !== null ? $this->parseYear($data['drinkFromYear']) : null);
		}
		if (array_key_exists('drinkUntilYear', $data)) {
			$vintage->setDrinkUntilYear($data['drinkUntilYear'] !== null ? $this->parseYear($data['drinkUntilYear']) : null);
		}
		if (array_key_exists('externalRating', $data)) {
			$vintage->setExternalRating($data['externalRating'] !== null ? (float)$data['externalRating'] : null);
		}
		if (array_key_exists('externalRatingSource', $data)) {
			$vintage->setExternalRatingSource($data['externalRatingSource'] !== null ? (string)$data['externalRatingSource'] : null);
		}
		if (array_key_exists('description', $data)) {
			$vintage->setDescription($data['description'] !== null ? (string)$data['description'] : null);
		}
		if (array_key_exists('referenceUrl', $data)) {
			$vintage->setReferenceUrl($data['referenceUrl'] !== null ? (string)$data['referenceUrl'] : null);
		}
	}

	private function parseYear(mixed $value): int {
		if (!is_numeric($value)) {
			throw new ValidationException('Invalid year: ' . print_r($value, true));
		}
		$year = (int)$value;
		$maxYear = (int)date('Y') + self::MAX_DRINK_YEAR_OFFSET;
		if ($year < self::MIN_YEAR || $year > $maxYear) {
			throw new ValidationException(sprintf('Year %d out of range (%d..%d)', $year, self::MIN_YEAR, $maxYear));
		}
		return $year;
	}

	private function findVintage(int $id): Vintage {
		try {
			return $this->vintageMapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Vintage not found', 0, $e);
		}
	}

	private function assertValidYear(int $year): void {
		$maxYear = (int)date('Y') + self::MAX_YEAR_OFFSET;
		if ($year < self::MIN_YEAR || $year > $maxYear) {
			throw new ValidationException(sprintf('Invalid year: %d (expected %d..%d)', $year, self::MIN_YEAR, $maxYear));
		}
	}
}
