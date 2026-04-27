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
use Throwable;

class TastingService {

	public function __construct(
		private readonly TastingMapper $tastingMapper,
		private readonly BottleService $bottleService,
		private readonly IDBConnection $db,
	) {
	}

	/** @return array<int, array<string, mixed>> */
	public function listAll(string $userId): array {
		return $this->tastingMapper->findAllByOwner($userId);
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
			$tasting->setTastedAt(new DateTime($data['tastedAt'] ?? 'now'));
		} catch (\Exception $e) {
			throw new ValidationException('Invalid date format for tastedAt');
		}

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

		return $this->tastingMapper->insert($tasting);
	}

	/**
	 * Consume a bottle AND create a tasting in one action.
	 */
	public function consumeWithTasting(string $userId, int $bottleId, array $tastingData): array {
		$this->db->beginTransaction();
		try {
			$bottle = $this->bottleService->consumeBottle($bottleId, $userId);
			$tasting = $this->create($userId, $bottleId, $tastingData);
			$this->db->commit();
			return ['bottle' => $bottle, 'tasting' => $tasting];
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
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

	public function delete(int $id, string $userId): Tasting {
		$tasting = $this->get($id, $userId);
		return $this->tastingMapper->delete($tasting);
	}
}
