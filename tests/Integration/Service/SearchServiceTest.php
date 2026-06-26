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
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Service\ProducerService;
use OCA\Vinarium\Service\SearchService;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use OCA\Vinarium\Tests\Integration\IntegrationTestCase;

class SearchServiceTest extends IntegrationTestCase {
	private SearchService $service;
	private ProducerService $producerService;
	private WineService $wineService;
	private VintageService $vintageService;

	protected function setUp(): void {
		parent::setUp();
		$this->producerService = new ProducerService(new ProducerMapper($this->db));
		$this->wineService = new WineService(new WineMapper($this->db), $this->producerService);
		$this->vintageService = new VintageService(new VintageMapper($this->db), $this->wineService);
		$this->service = new SearchService($this->db);
	}

	public function testFindsProducerByNameCaseInsensitiveSubstring(): void {
		$userId = $this->uniqueId('user');
		$this->producerService->create($userId, 'Château Clos Louie', 'Frankreich', 'Castillon');

		$results = $this->service->search($userId, 'clos');

		$this->assertCount(1, $results);
		$this->assertSame('producer', $results[0]['type']);
		$this->assertSame('Château Clos Louie', $results[0]['label']);
		$this->assertStringContainsString('Castillon', $results[0]['sub']);
	}

	public function testFindsProducerByRegion(): void {
		$userId = $this->uniqueId('user');
		$this->producerService->create($userId, 'Weingut A', 'Deutschland', 'Mosel');

		$results = $this->service->search($userId, 'mosel');

		$this->assertCount(1, $results);
		$this->assertSame('Weingut A', $results[0]['label']);
	}

	public function testFindsWineByNameThroughProducerJoin(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'Weingut B');
		$this->wineService->create($userId, $producer->getId(), 'Riesling Kabinett', Wine::COLOR_WHITE);

		$results = $this->service->search($userId, 'riesling');

		$wines = array_values(array_filter($results, static fn($r) => $r['type'] === 'wine'));
		$this->assertCount(1, $wines);
		$this->assertSame('Riesling Kabinett', $wines[0]['label']);
		$this->assertStringContainsString('Weingut B', $wines[0]['sub']);
	}

	public function testFindsVintageByYear(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'Weingut C');
		$wine = $this->wineService->create($userId, $producer->getId(), 'Spätburgunder', Wine::COLOR_RED);
		$this->vintageService->create($userId, $wine->getId(), 2018);

		$results = $this->service->search($userId, '2018');

		$vintages = array_values(array_filter($results, static fn($r) => $r['type'] === 'vintage'));
		$this->assertCount(1, $vintages);
		$this->assertSame('Spätburgunder 2018', $vintages[0]['label']);
		$this->assertSame(0, $vintages[0]['count']);
	}

	public function testFindsVintageByGrapeVariety(): void {
		$userId = $this->uniqueId('user');
		$producer = $this->producerService->create($userId, 'Weingut D');
		$wine = $this->wineService->create($userId, $producer->getId(), 'Cuvée', Wine::COLOR_RED);
		$this->vintageService->create($userId, $wine->getId(), 2020, ['grapeVarieties' => 'Merlot, Cabernet']);

		$results = $this->service->search($userId, 'merlot');

		$vintages = array_values(array_filter($results, static fn($r) => $r['type'] === 'vintage'));
		$this->assertCount(1, $vintages);
		$this->assertSame('Cuvée 2020', $vintages[0]['label']);
	}

	public function testOwnerScopingExcludesForeignData(): void {
		$owner = $this->uniqueId('owner');
		$intruder = $this->uniqueId('intruder');
		$producer = $this->producerService->create($owner, 'Geheimes Weingut');
		$this->wineService->create($owner, $producer->getId(), 'Geheimwein', Wine::COLOR_RED);

		$this->assertCount(0, $this->service->search($intruder, 'geheim'));
		$this->assertCount(2, $this->service->search($owner, 'geheim'));
	}

	public function testShortQueryReturnsEmpty(): void {
		$userId = $this->uniqueId('user');
		$this->producerService->create($userId, 'Aa');

		$this->assertSame([], $this->service->search($userId, 'a'));
		$this->assertSame([], $this->service->search($userId, ''));
	}
}
