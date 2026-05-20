<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Integration\Service;

use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\ProducerMapper;
use OCA\Vinarium\Db\PurchaseMapper;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Db\WineMapper;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\PurchaseService;
use OCA\Vinarium\Service\PurchaseWizardService;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class PurchaseWizardServiceTest extends IntegrationTestCase {
	private PurchaseWizardService $service;
	private ProducerService $producerService;
	private WineService $wineService;

	protected function setUp(): void {
		parent::setUp();
		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->wineService = new WineService(new WineMapper($this->db), $this->producerService);
		$vintageService = new VintageService(new VintageMapper($this->db), $this->wineService);
		$purchaseService = new PurchaseService(new PurchaseMapper($this->db), $vintageService);
		$bottleService = new BottleService(
			new BottleMapper($this->db),
			new SlotMapper($this->db),
			new CompartmentMapper($this->db),
			new ShelfMapper($this->db),
			new CellarMapper($this->db),
			$purchaseService,
			$this->db,
		);
		$this->service = new PurchaseWizardService(
			$this->producerService,
			$this->wineService,
			$vintageService,
			$purchaseService,
			$bottleService,
			$this->db,
		);
	}

	public function testCreatesAllEntitiesAndBottles(): void {
		$userId = $this->uniqueId('user');
		$result = $this->service->create($userId, [
			'producer' => ['id' => null, 'data' => ['name' => 'Weingut X', 'country' => 'DE']],
			'wine' => ['id' => null, 'data' => ['name' => 'Riesling', 'color' => 'white']],
			'vintage' => ['id' => null, 'data' => ['year' => 2021]],
			'purchase' => ['quantity' => 3, 'bottleSizeMl' => 750, 'purchasedAt' => '2026-05-20'],
		]);

		$this->assertCount(3, $result['bottles']);
		$this->assertSame(3, $result['purchase']->getQuantity());
		$this->assertGreaterThan(0, $result['purchase']->getVintageId());

		$producers = $this->producerService->list($userId);
		$this->assertCount(1, $producers);
		$this->assertSame('Weingut X', $producers[0]->getName());
	}

	public function testReusesExistingProducer(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'Bestehend');

		$this->service->create($userId, [
			'producer' => ['id' => $producer->getId(), 'data' => []],
			'wine' => ['id' => null, 'data' => ['name' => 'Neuer Wein', 'color' => 'red']],
			'vintage' => ['id' => null, 'data' => ['year' => 2020]],
			'purchase' => ['quantity' => 1, 'bottleSizeMl' => 750, 'purchasedAt' => '2026-05-20'],
		]);

		$producers = $this->producerService->list($userId);
		$this->assertCount(1, $producers, 'No duplicate producer should be created');
	}

	public function testRollsBackOnInvalidVintage(): void {
		$userId = $this->uniqueId('user');
		$this->expectException(ValidationException::class);
		try {
			$this->service->create($userId, [
				'producer' => ['id' => null, 'data' => ['name' => 'Rollback Weingut']],
				'wine' => ['id' => null, 'data' => ['name' => 'Wein', 'color' => 'red']],
				'vintage' => ['id' => null, 'data' => []],
				'purchase' => ['quantity' => 1, 'bottleSizeMl' => 750],
			]);
		} finally {
			$this->assertCount(0, $this->producerService->list($userId), 'Producer must be rolled back');
		}
	}
}
