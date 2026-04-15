<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class ProducerServiceTest extends IntegrationTestCase {
	private ProducerService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new ProducerService(new ProducerMapper($this->db));
	}

	public function testCreateAndGet(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->service->create($userId, 'Weingut Müller', 'DE', 'Mosel');

		$fetched = $this->service->get($producer->getId(), $userId);
		$this->assertSame('Weingut Müller', $fetched->getName());
		$this->assertSame('Mosel', $fetched->getRegion());
	}

	public function testListReturnsOwnerScoped(): void {
		$userA = $this->uniqueId('a');
		$userB = $this->uniqueId('b');
		$this->service->create($userA, 'Weingut A1');
		$this->service->create($userA, 'Weingut A2');
		$this->service->create($userB, 'Weingut B1');

		$this->assertCount(2, $this->service->list($userA));
		$this->assertCount(1, $this->service->list($userB));
	}

	public function testUpdate(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->service->create($userId, 'Alt');

		$updated = $this->service->update($producer->getId(), $userId, ['name' => 'Neu', 'country' => 'FR']);
		$this->assertSame('Neu', $updated->getName());
		$this->assertSame('FR', $updated->getCountry());
	}

	public function testGetForeignUserThrowsNotFound(): void {
		$userId = $this->uniqueId('owner');
		$producer = $this->service->create($userId, 'Geheim');

		$this->expectException(NotFoundException::class);
		$this->service->get($producer->getId(), $this->uniqueId('intruder'));
	}

	public function testDelete(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->service->create($userId, 'Weg damit');
		$this->service->delete($producer->getId(), $userId);

		$this->expectException(NotFoundException::class);
		$this->service->get($producer->getId(), $userId);
	}
}
