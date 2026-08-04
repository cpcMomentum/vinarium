<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Service;

use OCA\Vinarium\Db\BottleMapper;
use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Db\VintageMapper;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Label photo bookkeeping on the vintage (#190).
 *
 * The point of interest is not storing an id but what these methods report back:
 * the caller deletes files in the user's storage based on the returned value, so
 * a wrong answer either orphans a file or removes one still in use.
 */
class VintagePhotoServiceTest extends TestCase {
	private VintageMapper&MockObject $vintageMapper;
	private VintageService $service;

	protected function setUp(): void {
		$this->vintageMapper = $this->createMock(VintageMapper::class);
		$this->service = new VintageService(
			$this->vintageMapper,
			$this->createMock(WineService::class),
			$this->createMock(BottleMapper::class),
		);
	}

	private function vintage(?int $front = null, ?int $back = null): Vintage {
		$vintage = new Vintage();
		$vintage->setId(1);
		$vintage->setWineId(5);
		$vintage->setYear(2019);
		$vintage->setPhotoFrontFileId($front);
		$vintage->setPhotoBackFileId($back);
		$this->vintageMapper->method('find')->willReturn($vintage);
		return $vintage;
	}

	public function testSettingAFirstPhotoReplacesNothing(): void {
		$vintage = $this->vintage();
		$this->vintageMapper->expects($this->once())->method('update');

		$this->assertNull(
			$this->service->setPhoto(1, 'alice', 'front', 500),
			'nothing was displaced, so nothing may be cleaned up'
		);
		$this->assertSame(500, $vintage->getPhotoFrontFileId());
	}

	public function testReplacingAPhotoReportsTheDisplacedFile(): void {
		$vintage = $this->vintage(front: 500);

		$this->assertSame(
			500,
			$this->service->setPhoto(1, 'alice', 'front', 501),
			'the caller needs the old id to release the file'
		);
		$this->assertSame(501, $vintage->getPhotoFrontFileId());
	}

	public function testWritingTheSameFileAgainReportsNothingToClean(): void {
		// Reporting 500 here would make the caller consider deleting the very file
		// the vintage still points at.
		$this->vintage(front: 500);

		$this->assertNull($this->service->setPhoto(1, 'alice', 'front', 500));
	}

	public function testTheTwoSidesDoNotInterfere(): void {
		$vintage = $this->vintage(front: 500);

		$this->assertNull($this->service->setPhoto(1, 'alice', 'back', 600));
		$this->assertSame(500, $vintage->getPhotoFrontFileId(), 'front untouched');
		$this->assertSame(600, $vintage->getPhotoBackFileId());
	}

	public function testClearingReportsTheRemovedFile(): void {
		$vintage = $this->vintage(front: 500, back: 600);

		$this->assertSame(500, $this->service->clearPhoto(1, 'alice', 'front'));
		$this->assertNull($vintage->getPhotoFrontFileId());
		$this->assertSame(600, $vintage->getPhotoBackFileId(), 'back untouched');
	}

	public function testClearingAnEmptySideChangesNothing(): void {
		$this->vintage();
		$this->vintageMapper->expects($this->never())->method('update');

		$this->assertNull($this->service->clearPhoto(1, 'alice', 'front'));
	}

	public function testReadingASide(): void {
		$this->vintage(front: 500, back: 600);

		$this->assertSame(500, $this->service->getPhotoFileId(1, 'alice', 'front'));
		$this->assertSame(600, $this->service->getPhotoFileId(1, 'alice', 'back'));
	}

	/**
	 * A side outside the known set must be rejected before anything is written —
	 * otherwise a stored file would end up referenced by nothing.
	 */
	public function testAnUnknownSideIsRejected(): void {
		$this->vintage();
		$this->vintageMapper->expects($this->never())->method('update');

		$this->expectException(\InvalidArgumentException::class);
		$this->service->setPhoto(1, 'alice', 'left', 500);
	}

	public function testAnUnknownSideIsRejectedOnClearToo(): void {
		$this->vintage(front: 500);

		$this->expectException(\InvalidArgumentException::class);
		$this->service->clearPhoto(1, 'alice', 'seite');
	}

	public function testReferenceCountIsOwnerScoped(): void {
		$this->vintageMapper->expects($this->once())
			->method('countVintagesReferencingPhoto')
			->with(500, 'alice')
			->willReturn(1);

		$this->assertSame(1, $this->service->countPhotoReferences(500, 'alice'));
	}
}
