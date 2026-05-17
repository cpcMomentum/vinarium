<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Service;

use OCA\Vinarium\Db\Producer;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;

class ProducerService {

	public function __construct(
		private readonly ProducerMapper $producerMapper,
	) {
	}

	/** @return Producer[] */
	public function list(string $userId): array {
		return $this->producerMapper->findByOwner($userId);
	}

	public function get(int $id, string $userId): Producer {
		try {
			return $this->producerMapper->findOneByOwner($id, $userId);
		} catch (DoesNotExistException $e) {
			throw new NotFoundException('Producer not found', 0, $e);
		}
	}

	public function create(string $userId, string $name, ?string $country = null, ?string $region = null, ?string $website = null, ?string $notes = null): Producer {
		$producer = new Producer();
		$producer->setOwnerUserId($userId);
		$producer->setName($name);
		$producer->setCountry($country);
		$producer->setRegion($region);
		$producer->setWebsite($website);
		$producer->setNotes($notes);
		return $this->producerMapper->insert($producer);
	}

	public function update(int $id, string $userId, array $data): Producer {
		$producer = $this->get($id, $userId);
		if (array_key_exists('name', $data)) {
			$producer->setName((string)$data['name']);
		}
		if (array_key_exists('country', $data)) {
			$producer->setCountry($data['country'] !== null ? (string)$data['country'] : null);
		}
		if (array_key_exists('region', $data)) {
			$producer->setRegion($data['region'] !== null ? (string)$data['region'] : null);
		}
		if (array_key_exists('website', $data)) {
			$producer->setWebsite($data['website'] !== null ? (string)$data['website'] : null);
		}
		if (array_key_exists('notes', $data)) {
			$producer->setNotes($data['notes'] !== null ? (string)$data['notes'] : null);
		}
		return $this->producerMapper->update($producer);
	}

	public function delete(int $id, string $userId): Producer {
		$producer = $this->get($id, $userId);
		return $this->producerMapper->delete($producer);
	}
}
