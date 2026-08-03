<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;

class VintageService {

	private const MIN_YEAR = 1900;
	private const MAX_YEAR_OFFSET = 2;

	public function __construct(
		private readonly VintageMapper $vintageMapper,
		private readonly WineService $wineService,
		private readonly BottleMapper $bottleMapper,
	) {
	}

	/** @return Vintage[] */
	public function listByWine(int $wineId, string $userId): array {
		$this->wineService->get($wineId, $userId);
		return $this->vintageMapper->findByWine($wineId);
	}

	/**
	 * Number of bottles still in storage per vintage, for the whole cellar.
	 *
	 * Vintages without any bottle in storage are absent from the map rather
	 * than carrying a zero — the caller treats a missing key as 0.
	 *
	 * @return array<int, int> vintage id => bottles in storage
	 */
	public function stockByVintage(string $userId): array {
		return $this->bottleMapper->countInStorageByVintage($userId);
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
		// Accept both flat and nested ({ data: { ... } }) payloads — the frontend
		// VintageCreate/Update type carries optional fields inside a `data` object,
		// while existing internal callers pass them flat.
		$fields = $data;
		if (isset($data['data']) && is_array($data['data'])) {
			$fields = $data['data'] + $fields;
		}
		$this->applyOptionalFields($vintage, $fields);
		return $this->vintageMapper->update($vintage);
	}

	public function delete(int $id, string $userId): Vintage {
		$vintage = $this->get($id, $userId);
		return $this->vintageMapper->delete($vintage);
	}

	/**
	 * Point one side of the label at a stored file and return the file id it
	 * replaced, so the caller can clean up the previous file if it became orphaned.
	 *
	 * Photo references deliberately do not travel through update()/applyOptionalFields():
	 * a file id there would let a client attach any file it can guess the id of.
	 * They are only ever set here, right after this app wrote the file itself.
	 */
	public function setPhoto(int $id, string $userId, string $side, int $fileId): ?int {
		$vintage = $this->get($id, $userId);
		$previous = $this->readPhoto($vintage, $side);
		$this->writePhoto($vintage, $side, $fileId);
		$this->vintageMapper->update($vintage);
		return $previous !== $fileId ? $previous : null;
	}

	/** Clear one side and return the file id that was referenced, if any. */
	public function clearPhoto(int $id, string $userId, string $side): ?int {
		$vintage = $this->get($id, $userId);
		$previous = $this->readPhoto($vintage, $side);
		if ($previous === null) {
			return null;
		}
		$this->writePhoto($vintage, $side, null);
		$this->vintageMapper->update($vintage);
		return $previous;
	}

	public function getPhotoFileId(int $id, string $userId, string $side): ?int {
		return $this->readPhoto($this->get($id, $userId), $side);
	}

	/** Number of vintages of the owner still referencing the given file id. */
	public function countPhotoReferences(int $fileId, string $userId): int {
		return $this->vintageMapper->countVintagesReferencingPhoto($fileId, $userId);
	}

	private function readPhoto(Vintage $vintage, string $side): ?int {
		$this->assertValidSide($side);
		return $side === 'front' ? $vintage->getPhotoFrontFileId() : $vintage->getPhotoBackFileId();
	}

	private function writePhoto(Vintage $vintage, string $side, ?int $fileId): void {
		$this->assertValidSide($side);
		if ($side === 'front') {
			$vintage->setPhotoFrontFileId($fileId);
			return;
		}
		$vintage->setPhotoBackFileId($fileId);
	}

	private function assertValidSide(string $side): void {
		if (!in_array($side, Vintage::PHOTO_SIDES, true)) {
			throw new \InvalidArgumentException('Ungültige Etikettenseite: ' . $side);
		}
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
		$from = $vintage->getDrinkFromYear();
		$until = $vintage->getDrinkUntilYear();
		if ($from !== null && $until !== null && $from > $until) {
			throw new ValidationException('drinkFromYear must not be greater than drinkUntilYear');
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
		if (array_key_exists('sweetness', $data)) {
			$vintage->setSweetness($this->parseSweetness($data['sweetness']));
		}
	}

	/**
	 * NULL and the empty string both mean "not specified" — the select in the
	 * UI submits an empty value for its placeholder option. Anything else has
	 * to be one of the known levels; an unknown string would otherwise sit in
	 * the database and render as a missing label.
	 */
	private function parseSweetness(mixed $value): ?string {
		if ($value === null || $value === '') {
			return null;
		}
		$sweetness = (string)$value;
		if (!in_array($sweetness, Vintage::SWEETNESS_VALUES, true)) {
			throw new ValidationException(sprintf(
				'Invalid sweetness "%s" (allowed: %s)',
				$sweetness,
				implode(', ', Vintage::SWEETNESS_VALUES),
			));
		}
		return $sweetness;
	}

	private function parseYear(mixed $value): int {
		if (!is_numeric($value)) {
			throw new ValidationException('Invalid year: ' . print_r($value, true));
		}
		$year = (int)$value;
		$maxYear = (int)date('Y') + 50;
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
