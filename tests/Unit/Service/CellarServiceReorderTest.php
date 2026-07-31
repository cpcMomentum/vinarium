<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\Cellar;
use OCA\Vinarium\Db\CellarMapper;
use OCA\Vinarium\Db\CompartmentMapper;
use OCA\Vinarium\Db\LevelMapper;
use OCA\Vinarium\Db\Shelf;
use OCA\Vinarium\Db\ShelfMapper;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\CellarService;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CellarServiceReorderTest extends TestCase {
	private CellarMapper&MockObject $cellarMapper;
	private ShelfMapper&MockObject $shelfMapper;
	private IDBConnection&MockObject $db;
	private CellarService $service;

	protected function setUp(): void {
		$this->cellarMapper = $this->createMock(CellarMapper::class);
		$this->shelfMapper = $this->createMock(ShelfMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->service = new CellarService(
			$this->cellarMapper,
			$this->shelfMapper,
			$this->createMock(CompartmentMapper::class),
			$this->createMock(LevelMapper::class),
			$this->createMock(SlotMapper::class),
			$this->createMock(BottleMapper::class),
			$this->db,
		);
	}

	private function givenCellarOwnedBy(string $userId): void {
		$cellar = new Cellar();
		$cellar->setId(1);
		$cellar->setOwnerUserId($userId);
		$this->cellarMapper->method('find')->willReturn($cellar);
	}

	/** @param array<int, int> $idToSortOrder */
	private function givenShelves(array $idToSortOrder): void {
		$shelves = [];
		foreach ($idToSortOrder as $id => $sortOrder) {
			$shelf = new Shelf();
			$shelf->setId($id);
			$shelf->setCellarId(1);
			$shelf->setSortOrder($sortOrder);
			$shelves[] = $shelf;
		}
		$this->shelfMapper->method('findByCellar')->willReturn($shelves);
		$this->shelfMapper->method('update')->willReturnArgument(0);
	}

	public function testRenumbersDenselyFromZeroInTheGivenOrder(): void {
		$this->givenCellarOwnedBy('alice');
		$this->givenShelves([7 => 0, 8 => 1, 9 => 2]);
		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$result = $this->service->reorderShelves(1, 'alice', [9, 7, 8]);

		$this->assertSame([9, 7, 8], array_map(static fn (Shelf $s): int => $s->getId(), $result));
		$this->assertSame([0, 1, 2], array_map(static fn (Shelf $s): int => $s->getSortOrder(), $result));
	}

	public function testRejectsAForeignShelfId(): void {
		// Ohne diese Pruefung wuerde ein fremdes Regal in den Keller einsortiert
		// bzw. auf einen Index gesetzt, den der Aufrufer nie gesehen hat.
		$this->givenCellarOwnedBy('alice');
		$this->givenShelves([7 => 0, 8 => 1]);

		$this->expectException(ValidationException::class);
		$this->service->reorderShelves(1, 'alice', [7, 99]);
	}

	public function testRejectsAnIncompleteList(): void {
		// Ein fehlendes Regal wuerde seine alte sort_order behalten und danach
		// mit einem der neu vergebenen Werte kollidieren.
		$this->givenCellarOwnedBy('alice');
		$this->givenShelves([7 => 0, 8 => 1, 9 => 2]);

		$this->expectException(ValidationException::class);
		$this->service->reorderShelves(1, 'alice', [9, 7]);
	}

	public function testRejectsDuplicateIds(): void {
		$this->givenCellarOwnedBy('alice');
		$this->givenShelves([7 => 0, 8 => 1]);

		$this->expectException(ValidationException::class);
		$this->service->reorderShelves(1, 'alice', [7, 7]);
	}

	public function testRejectsAForeignCellar(): void {
		$this->givenCellarOwnedBy('bob');

		$this->expectException(PermissionDeniedException::class);
		$this->service->reorderShelves(1, 'alice', [7]);
	}

	public function testDoesNotOpenATransactionWhenValidationFails(): void {
		$this->givenCellarOwnedBy('alice');
		$this->givenShelves([7 => 0, 8 => 1]);
		$this->db->expects($this->never())->method('beginTransaction');

		$this->expectException(ValidationException::class);
		$this->service->reorderShelves(1, 'alice', [7]);
	}
}
