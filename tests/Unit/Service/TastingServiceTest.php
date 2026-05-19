<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Db\Tasting;
use OCA\Vinarium\Db\TastingMapper;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\TastingService;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TastingServiceTest extends TestCase {
	private TastingMapper&MockObject $tastingMapper;
	private BottleService&MockObject $bottleService;
	private IDBConnection&MockObject $db;
	private TastingService $service;

	protected function setUp(): void {
		$this->tastingMapper = $this->createMock(TastingMapper::class);
		$this->bottleService = $this->createMock(BottleService::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->service = new TastingService($this->tastingMapper, $this->bottleService, $this->db);
	}

	public function testConsumeWithTastingCommitsOnSuccess(): void {
		$bottle = new Bottle();
		$bottle->setId(42);
		$tasting = new Tasting();
		$tasting->setId(7);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$this->bottleService->expects($this->once())->method('consumeBottle')
			->with(42, 'alice')->willReturn($bottle);
		// create() uses bottleService->get() for ownership check + tastingMapper->insert()
		$this->bottleService->expects($this->once())->method('get')->with(42, 'alice');
		$this->tastingMapper->expects($this->once())->method('insert')->willReturn($tasting);

		$result = $this->service->consumeWithTasting('alice', 42, ['tastedAt' => '2026-05-18']);

		$this->assertSame($bottle, $result['bottle']);
		$this->assertSame($tasting, $result['tasting']);
	}

	public function testConsumeWithTastingRollsBackWhenTastingCreationFails(): void {
		$bottle = new Bottle();
		$bottle->setId(42);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$this->bottleService->expects($this->once())->method('consumeBottle')
			->with(42, 'alice')->willReturn($bottle);
		$this->bottleService->expects($this->once())->method('get')->with(42, 'alice');
		// Rating out of range → ValidationException thrown by create()
		$this->tastingMapper->expects($this->never())->method('insert');

		$this->expectException(ValidationException::class);
		$this->service->consumeWithTasting('alice', 42, ['tastedAt' => '2026-05-18', 'rating' => 99.0]);
	}

	public function testConsumeWithTastingRollsBackWhenBottleConsumeFails(): void {
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->never())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$this->bottleService->expects($this->once())->method('consumeBottle')
			->willThrowException(new \RuntimeException('DB error'));
		// Tasting creation must not happen if consume fails
		$this->bottleService->expects($this->never())->method('get');
		$this->tastingMapper->expects($this->never())->method('insert');

		$this->expectException(\RuntimeException::class);
		$this->service->consumeWithTasting('alice', 42, ['tastedAt' => '2026-05-18']);
	}
}
