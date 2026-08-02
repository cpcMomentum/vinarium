<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VintageServiceStockTest extends TestCase {
	private VintageMapper&MockObject $vintageMapper;
	private WineService&MockObject $wineService;
	private BottleMapper&MockObject $bottleMapper;
	private VintageService $service;

	protected function setUp(): void {
		$this->vintageMapper = $this->createMock(VintageMapper::class);
		$this->wineService = $this->createMock(WineService::class);
		$this->bottleMapper = $this->createMock(BottleMapper::class);
		$this->service = new VintageService($this->vintageMapper, $this->wineService, $this->bottleMapper);
	}

	public function testStockByVintagePassesTheUserThroughForOwnerScoping(): void {
		$this->bottleMapper->expects($this->once())
			->method('countInStorageByVintage')
			->with('alice')
			->willReturn([7 => 11, 9 => 2]);

		$this->assertSame([7 => 11, 9 => 2], $this->service->stockByVintage('alice'));
	}

	public function testVintagesWithoutStockAreAbsentRatherThanZero(): void {
		// The mapper groups over existing bottles, so a fully consumed vintage
		// yields no row at all. Callers must read a missing key as 0 — this
		// test pins that contract down.
		$this->bottleMapper->method('countInStorageByVintage')->willReturn([7 => 11]);

		$stock = $this->service->stockByVintage('alice');

		$this->assertArrayNotHasKey(9, $stock);
		$this->assertSame(0, $stock[9] ?? 0);
	}
}
