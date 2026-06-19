<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use DateTime;
use OCA\Vinarium\Db\Tasting;
use OCA\Vinarium\Db\TastingMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;

class TastingService {

	public function __construct(
		private readonly TastingMapper $tastingMapper,
		private readonly BottleService $bottleService,
		private readonly IDBConnection $db,
	) {
	}

	/** @return array<int, array<string, mixed>> */
	public function listAll(string $userId): array {
		$rows = $this->tastingMapper->findAllByOwner($userId);
		return array_map(function (array $row): array {
			$row['photo_file_ids'] = isset($row['photo_file_ids']) && $row['photo_file_ids'] !== null
				? json_decode((string)$row['photo_file_ids'], true) ?? []
				: [];
			$row['would_rebuy'] = isset($row['would_rebuy']) && $row['would_rebuy'] !== null
				? (bool)$row['would_rebuy']
				: null;
			return $row;
		}, $rows);
	}

	/** @return Tasting[] */
	public function listByBottle(int $bottleId, string $userId): array {
		$this->bottleService->get($bottleId, $userId);
		return $this->tastingMapper->findByBottle($bottleId);
	}

	public function create(string $userId, int $bottleId, array $data): Tasting {
		$this->bottleService->get($bottleId, $userId);

		$tasting = new Tasting();
		$tasting->setBottleId($bottleId);
		try {
			$tastedAt = new DateTime($data['tastedAt'] ?? 'now', new \DateTimeZone('UTC'));
		} catch (\Exception $e) {
			throw new ValidationException('Invalid tasting date');
		}
		$this->assertTastedAtNotInFuture($tastedAt);
		$tasting->setTastedAt($tastedAt);

		if (isset($data['rating'])) {
			$rating = (float)$data['rating'];
			if ($rating < 0.5 || $rating > 10.0) {
				throw new ValidationException('Rating must be 0.5..10.0');
			}
			$tasting->setRating($rating);
		}
		$tasting->setNotes($data['notes'] ?? null);
		$tasting->setOccasion($data['occasion'] ?? null);
		$tasting->setCompanions($data['companions'] ?? null);
		if (array_key_exists('wouldRebuy', $data)) {
			$tasting->setWouldRebuy($data['wouldRebuy'] === null ? null : (bool)$data['wouldRebuy']);
		}

		return $this->tastingMapper->insert($tasting);
	}

	/**
	 * Consume a bottle AND create a tasting in one atomic action.
	 */
	public function consumeWithTasting(string $userId, int $bottleId, array $tastingData): array {
		$this->db->beginTransaction();
		try {
			$bottle = $this->bottleService->consumeBottle($bottleId, $userId);
			$tasting = $this->create($userId, $bottleId, $tastingData);
			$this->db->commit();
			return ['bottle' => $bottle, 'tasting' => $tasting];
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/** @return array<string, mixed> */
	public function getDetails(int $id, string $userId): array {
		$row = $this->tastingMapper->findDetails($id, $userId);
		if ($row === null) {
			throw new NotFoundException('Tasting not found');
		}
		$relatedSameWine = $this->tastingMapper->findRelatedSameWine(
			$id,
			(int)$row['wine_id'],
			$userId
		);
		$relatedSameProducer = $this->tastingMapper->findRelatedSameProducer(
			$id,
			(int)$row['producer_id'],
			(int)$row['wine_id'],
			$userId
		);
		$photoFileIds = isset($row['photo_file_ids']) && $row['photo_file_ids'] !== null
			? json_decode((string)$row['photo_file_ids'], true) ?? []
			: [];

		return [
			'id' => (int)$row['id'],
			'bottle_id' => (int)$row['bottle_id'],
			'tasted_at' => $row['tasted_at'],
			'rating' => $row['rating'] !== null ? (float)$row['rating'] : null,
			'notes' => $row['notes'],
			'occasion' => $row['occasion'],
			'companions' => $row['companions'],
			'would_rebuy' => isset($row['would_rebuy']) && $row['would_rebuy'] !== null ? (bool)$row['would_rebuy'] : null,
			'photo_file_ids' => $photoFileIds,
			'wine_id' => (int)$row['wine_id'],
			'wine_name' => $row['wine_name'],
			'wine_color' => $row['wine_color'],
			'vintage_id' => (int)$row['vintage_id'],
			'year' => (int)$row['year'],
			'producer_id' => (int)$row['producer_id'],
			'producer_name' => $row['producer_name'],
			'purchase' => [
				'purchased_at' => $row['purchased_at'],
				'vendor' => $row['vendor'],
				'unit_price' => $row['unit_price'] !== null ? (float)$row['unit_price'] : null,
				'currency' => $row['currency'],
				'bottle_size_ml' => (int)$row['bottle_size_ml'],
			],
			'related_same_wine' => array_map(fn($r) => [
				'id' => (int)$r['id'],
				'tasted_at' => $r['tasted_at'],
				'rating' => $r['rating'] !== null ? (float)$r['rating'] : null,
				'notes' => $r['notes'],
				'year' => (int)$r['year'],
			], $relatedSameWine),
			'related_same_producer' => array_map(fn($r) => [
				'id' => (int)$r['id'],
				'tasted_at' => $r['tasted_at'],
				'rating' => $r['rating'] !== null ? (float)$r['rating'] : null,
				'notes' => $r['notes'],
				'wine_name' => $r['wine_name'],
				'year' => (int)$r['year'],
			], $relatedSameProducer),
		];
	}

	public function get(int $id, string $userId): Tasting {
		try {
			$tasting = $this->tastingMapper->find($id);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Tasting not found', 0, $e);
		}
		$this->bottleService->get($tasting->getBottleId(), $userId);
		return $tasting;
	}

	public function update(int $id, string $userId, array $data): Tasting {
		$tasting = $this->get($id, $userId);

		if (isset($data['tastedAt'])) {
			try {
				$tastedAt = new DateTime($data['tastedAt'], new \DateTimeZone('UTC'));
			} catch (\Exception $e) {
				throw new ValidationException('Invalid tasting date');
			}
			$this->assertTastedAtNotInFuture($tastedAt);
			$tasting->setTastedAt($tastedAt);
		}
		if (array_key_exists('rating', $data)) {
			if ($data['rating'] !== null) {
				$rating = (float)$data['rating'];
				if ($rating < 0.5 || $rating > 10.0) {
					throw new ValidationException('Rating must be 0.5..10.0');
				}
				$tasting->setRating($rating);
			} else {
				$tasting->setRating(null);
			}
		}
		if (array_key_exists('notes', $data)) {
			$tasting->setNotes($data['notes']);
		}
		if (array_key_exists('occasion', $data)) {
			$tasting->setOccasion($data['occasion']);
		}
		if (array_key_exists('companions', $data)) {
			$tasting->setCompanions($data['companions']);
		}
		if (array_key_exists('wouldRebuy', $data)) {
			$tasting->setWouldRebuy($data['wouldRebuy'] === null ? null : (bool)$data['wouldRebuy']);
		}

		return $this->tastingMapper->update($tasting);
	}

	public function save(Tasting $tasting): Tasting {
		return $this->tastingMapper->update($tasting);
	}

	public function delete(int $id, string $userId): Tasting {
		$tasting = $this->get($id, $userId);
		return $this->tastingMapper->delete($tasting);
	}

	/** @return array{year: int, month: int, count_year: int, count_current_month: int, total_count: int, avg_rating: float|null, best_wine: array{wine_name:string,producer_name:string,year:int,rating:float}|null, with_photos_count: int} */
	public function getStats(string $userId): array {
		$now = new DateTime('now', new \DateTimeZone('UTC'));
		$year = (int)$now->format('Y');
		$month = (int)$now->format('m');
		return [
			'year' => $year,
			'month' => $month,
			'count_year' => $this->tastingMapper->countByOwnerYear($userId, $year),
			'count_current_month' => $this->tastingMapper->countByOwnerMonth($userId, $year, $month),
			'total_count' => $this->tastingMapper->countAllByOwner($userId),
			'avg_rating' => $this->tastingMapper->avgRatingByOwner($userId),
			'best_wine' => $this->tastingMapper->findBestRatedByOwner($userId),
			'with_photos_count' => $this->tastingMapper->countWithPhotosByOwner($userId),
		];
	}

	/**
	 * Reject tasting dates in the future. Tolerates timezone offsets by allowing
	 * up to the start of tomorrow (UTC) — covers any client timezone up to UTC-12.
	 */
	private function assertTastedAtNotInFuture(DateTime $tastedAt): void {
		$limit = new DateTime('tomorrow 00:00:00', new \DateTimeZone('UTC'));
		if ($tastedAt > $limit) {
			throw new ValidationException('Tasting date cannot be in the future');
		}
	}
}
