<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class WineServiceTest extends IntegrationTestCase {
	private WineService $service;
	private ProducerService $producerService;

	protected function setUp(): void {
		parent::setUp();
		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->service = new WineService(new WineMapper($this->db), $this->producerService);
	}

	public function testCreateAndGet(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');

		$wine = $this->service->create($userId, $producer->getId(), 'Riesling', Wine::COLOR_WHITE, [
			'grapeVarieties' => 'Riesling 100%',
		]);

		$fetched = $this->service->get($wine->getId(), $userId);
		$this->assertSame('Riesling', $fetched->getName());
		$this->assertSame(Wine::COLOR_WHITE, $fetched->getColor());
	}

	public function testListByProducer(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');
		$this->service->create($userId, $producer->getId(), 'A', Wine::COLOR_RED);
		$this->service->create($userId, $producer->getId(), 'B', Wine::COLOR_WHITE);

		$this->assertCount(2, $this->service->listByProducer($producer->getId(), $userId));
	}

	public function testUpdate(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');
		$wine = $this->service->create($userId, $producer->getId(), 'Alt', Wine::COLOR_RED);

		$updated = $this->service->update($wine->getId(), $userId, [
			'name' => 'Neu',
			'color' => Wine::COLOR_ROSE,
		]);

		$this->assertSame('Neu', $updated->getName());
		$this->assertSame(Wine::COLOR_ROSE, $updated->getColor());
	}

	public function testCreateRejectsInvalidColor(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');

		$this->expectException(ValidationException::class);
		$this->service->create($userId, $producer->getId(), 'X', 'purple');
	}

	public function testUpdateRejectsInvalidColor(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');
		$wine = $this->service->create($userId, $producer->getId(), 'X', Wine::COLOR_RED);

		$this->expectException(ValidationException::class);
		$this->service->update($wine->getId(), $userId, ['color' => 'emerald']);
	}

	public function testForeignUserCannotAccess(): void {
		$owner = $this->uniqueId('owner');
		$producer = $this->producerService->create($owner, 'P');
		$wine = $this->service->create($owner, $producer->getId(), 'Secret', Wine::COLOR_RED);

		$this->expectException(NotFoundException::class);
		$this->service->get($wine->getId(), $this->uniqueId('intruder'));
	}

	public function testDelete(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');
		$wine = $this->service->create($userId, $producer->getId(), 'X', Wine::COLOR_RED);

		$this->service->delete($wine->getId(), $userId);

		$this->expectException(NotFoundException::class);
		$this->service->get($wine->getId(), $userId);
	}
}
