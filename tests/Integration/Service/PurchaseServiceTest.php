<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use DateTime;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\PurchaseService;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class PurchaseServiceTest extends IntegrationTestCase {
	private PurchaseService $service;
	private VintageService $vintageService;
	private WineService $wineService;
	private ProducerService $producerService;

	protected function setUp(): void {
		parent::setUp();
		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->wineService = new WineService(new WineMapper($this->db), $this->producerService);
		$this->vintageService = new VintageService(new VintageMapper($this->db), $this->wineService);
		$this->service = new PurchaseService(new PurchaseMapper($this->db), $this->vintageService);
	}

	public function testCreateAndGet(): void {
		[$userId, $vintageId] = $this->seedVintage();

		$purchase = $this->service->create($userId, $vintageId, [
			'purchasedAt' => '2026-03-15',
			'vendor' => 'Weinhandlung Müller',
			'unitPrice' => 24.50,
			'currency' => 'EUR',
			'quantity' => 6,
			'bottleSizeMl' => 750,
		]);

		$this->assertSame(6, $purchase->getQuantity());
		$fetched = $this->service->get($purchase->getId(), $userId);
		$this->assertSame('Weinhandlung Müller', $fetched->getVendor());
	}

	public function testListByVintage(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$this->service->create($userId, $vintageId, ['quantity' => 3, 'bottleSizeMl' => 750]);
		$this->service->create($userId, $vintageId, ['quantity' => 6, 'bottleSizeMl' => 1500]);

		$this->assertCount(2, $this->service->listByVintage($vintageId, $userId));
	}

	public function testRejectsZeroQuantity(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$this->expectException(ValidationException::class);
		$this->service->create($userId, $vintageId, ['quantity' => 0, 'bottleSizeMl' => 750]);
	}

	public function testRejectsInvalidBottleSize(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$this->expectException(ValidationException::class);
		$this->service->create($userId, $vintageId, ['quantity' => 1, 'bottleSizeMl' => 999]);
	}

	public function testForeignUserCannotAccess(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$purchase = $this->service->create($userId, $vintageId, ['quantity' => 1, 'bottleSizeMl' => 750]);

		$this->expectException(NotFoundException::class);
		$this->service->get($purchase->getId(), $this->uniqueId('intruder'));
	}

	public function testUpdate(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$purchase = $this->service->create($userId, $vintageId, ['quantity' => 1, 'bottleSizeMl' => 750]);

		$updated = $this->service->update($purchase->getId(), $userId, ['quantity' => 12]);
		$this->assertSame(12, $updated->getQuantity());
	}

	public function testDelete(): void {
		[$userId, $vintageId] = $this->seedVintage();
		$purchase = $this->service->create($userId, $vintageId, ['quantity' => 1, 'bottleSizeMl' => 750]);
		$this->service->delete($purchase->getId(), $userId);

		$this->expectException(NotFoundException::class);
		$this->service->get($purchase->getId(), $userId);
	}

	/** @return array{0: string, 1: int} [userId, vintageId] */
	private function seedVintage(): array {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'P');
		$wine = $this->wineService->create($userId, $producer->getId(), 'W', Wine::COLOR_RED);
		$vintage = $this->vintageService->create($userId, $wine->getId(), 2020);
		return [$userId, $vintage->getId()];
	}
}
