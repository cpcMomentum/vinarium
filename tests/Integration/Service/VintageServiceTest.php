<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class VintageServiceTest extends IntegrationTestCase {
	private VintageService $service;
	private WineService $wineService;
	private ProducerService $producerService;

	protected function setUp(): void {
		parent::setUp();
		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->wineService = new WineService(new WineMapper($this->db), $this->producerService);
		$this->service = new VintageService(new VintageMapper($this->db), $this->wineService);
	}

	public function testCreateAndGet(): void {
		$wineId = $this->seedWine();
		$userId = $this->currentUserId;

		$vintage = $this->service->create($userId, $wineId, 2022, [
			'alcoholPercent' => 13.5,
			'description' => 'Mineralisch',
		]);

		$fetched = $this->service->get($vintage->getId(), $userId);
		$this->assertSame(2022, $fetched->getYear());
		$this->assertSame(13.5, $fetched->getAlcoholPercent());
	}

	public function testListByWine(): void {
		$wineId = $this->seedWine();
		$this->service->create($this->currentUserId, $wineId, 2020);
		$this->service->create($this->currentUserId, $wineId, 2021);

		$this->assertCount(2, $this->service->listByWine($wineId, $this->currentUserId));
	}

	public function testCreateRejectsInvalidYear(): void {
		$wineId = $this->seedWine();
		$this->expectException(ValidationException::class);
		$this->service->create($this->currentUserId, $wineId, 1800);
	}

	public function testForeignUserCannotAccess(): void {
		$wineId = $this->seedWine();
		$vintage = $this->service->create($this->currentUserId, $wineId, 2020);

		$this->expectException(NotFoundException::class);
		$this->service->get($vintage->getId(), $this->uniqueId('intruder'));
	}

	public function testUpdate(): void {
		$wineId = $this->seedWine();
		$vintage = $this->service->create($this->currentUserId, $wineId, 2020);

		$updated = $this->service->update($vintage->getId(), $this->currentUserId, [
			'year' => 2021,
			'description' => 'Jetzt besser',
		]);

		$this->assertSame(2021, $updated->getYear());
		$this->assertSame('Jetzt besser', $updated->getDescription());
	}

	public function testDelete(): void {
		$wineId = $this->seedWine();
		$vintage = $this->service->create($this->currentUserId, $wineId, 2020);
		$this->service->delete($vintage->getId(), $this->currentUserId);

		$this->expectException(NotFoundException::class);
		$this->service->get($vintage->getId(), $this->currentUserId);
	}

	private string $currentUserId;

	private function seedWine(): int {
		$this->currentUserId = $this->uniqueId('user');
		$producer = $this->producerService->create($this->currentUserId, 'P');
		$wine = $this->wineService->create($this->currentUserId, $producer->getId(), 'W', Wine::COLOR_RED);
		return $wine->getId();
	}
}
