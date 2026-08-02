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
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\VintageService;
use OCA\Vinarium\Service\WineService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VintageSweetnessTest extends TestCase {
	private VintageMapper&MockObject $vintageMapper;
	private WineService&MockObject $wineService;
	private VintageService $service;

	protected function setUp(): void {
		$this->vintageMapper = $this->createMock(VintageMapper::class);
		$this->wineService = $this->createMock(WineService::class);
		// BottleMapper wird nur von stockByVintage() gebraucht (#189) und bleibt
		// hier ungenutzt — er muss aber uebergeben werden.
		$this->service = new VintageService(
			$this->vintageMapper,
			$this->wineService,
			$this->createMock(BottleMapper::class),
		);
	}

	private function existingVintage(): Vintage {
		$vintage = new Vintage();
		$vintage->setId(1);
		$vintage->setWineId(5);
		$vintage->setYear(2019);
		return $vintage;
	}

	private function expectUpdateReturningSameEntity(): void {
		$this->vintageMapper->method('find')->willReturn($this->existingVintage());
		$this->vintageMapper->method('update')->willReturnArgument(0);
	}

	/** @return array<string, array{string}> */
	public static function validSweetnessProvider(): array {
		return [
			'dry' => ['dry'],
			'off dry' => ['off_dry'],
			'medium sweet' => ['medium_sweet'],
			'sweet' => ['sweet'],
		];
	}

	/** @dataProvider validSweetnessProvider */
	public function testAcceptsEveryDefinedSweetnessLevel(string $level): void {
		$this->expectUpdateReturningSameEntity();

		$updated = $this->service->update(1, 'alice', ['sweetness' => $level]);

		$this->assertSame($level, $updated->getSweetness());
	}

	public function testRejectsUnknownSweetness(): void {
		$this->expectUpdateReturningSameEntity();

		$this->expectException(ValidationException::class);
		$this->service->update(1, 'alice', ['sweetness' => 'halbsuess']);
	}

	public function testEmptyStringMeansNotSpecified(): void {
		// The select in the UI submits '' for its placeholder option. Storing
		// that verbatim would produce a value that matches no label, so it has
		// to collapse to NULL like an explicit null does.
		$this->expectUpdateReturningSameEntity();

		$updated = $this->service->update(1, 'alice', ['sweetness' => '']);

		$this->assertNull($updated->getSweetness());
	}

	public function testNullClearsAPreviouslySetLevel(): void {
		$this->expectUpdateReturningSameEntity();

		$updated = $this->service->update(1, 'alice', ['sweetness' => null]);

		$this->assertNull($updated->getSweetness());
	}

	public function testOmittingTheKeyLeavesTheStoredLevelUntouched(): void {
		$vintage = $this->existingVintage();
		$vintage->setSweetness('sweet');
		$this->vintageMapper->method('find')->willReturn($vintage);
		$this->vintageMapper->method('update')->willReturnArgument(0);

		$updated = $this->service->update(1, 'alice', ['alcoholPercent' => 12.5]);

		$this->assertSame('sweet', $updated->getSweetness());
	}
}
