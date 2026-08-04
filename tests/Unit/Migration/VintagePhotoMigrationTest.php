<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\Vinarium\Migration\Version000107Date20260803000000;
use OCP\DB\IResult;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Covers the data half of the label-photo move: carrying bottle photos up to the
 * vintage. The conflict rule is the part worth pinning down — it decides which
 * photo a user keeps seeing.
 */
class VintagePhotoMigrationTest extends TestCase {

	/** @var list<array{string, array}> captured UPDATE statements */
	private array $statements = [];
	private array $warnings = [];

	/**
	 * @param list<array<string, mixed>> $bottleRows
	 */
	private function runMigration(array $bottleRows, bool $bottleColumnExists = true): IOutput&MockObject {
		$this->statements = [];
		$this->warnings = [];

		$result = $this->createMock(IResult::class);
		$result->method('fetchAll')->willReturn($bottleRows);

		$db = $this->createMock(IDBConnection::class);
		$db->method('executeQuery')->willReturn($result);
		$db->method('executeStatement')->willReturnCallback(
			function (string $sql, array $params = []): int {
				$this->statements[] = [$sql, $params];
				return 1;
			}
		);

		$table = $this->createMock(Table::class);
		$table->method('hasColumn')->willReturn($bottleColumnExists);

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(true);
		$schema->method('getTable')->willReturn($table);

		$output = $this->createMock(IOutput::class);
		$output->method('warning')->willReturnCallback(function (string $msg): void {
			$this->warnings[] = $msg;
		});

		$migration = new Version000107Date20260803000000($db);
		$migration->postSchemaChange($output, static fn (): ISchemaWrapper => $schema, []);

		return $output;
	}

	/** @return list<array{int, int}> [fileId, vintageId] pairs actually written */
	private function writtenPairs(): array {
		return array_map(
			static fn (array $s): array => [(int)$s[1][0], (int)$s[1][1]],
			$this->statements
		);
	}

	public function testCarriesSinglePhotoUpToItsVintage(): void {
		$this->runMigration([
			['bottle_id' => 1, 'photo_file_id' => 500, 'vintage_id' => 10],
		]);

		$this->assertSame([[500, 10]], $this->writtenPairs());
		$this->assertSame([], $this->warnings);
	}

	public function testSiblingsSharingOnePhotoWriteItOnce(): void {
		// The normal case after #129: propagation kept every bottle of the vintage
		// on the same file, so there is nothing to resolve.
		$this->runMigration([
			['bottle_id' => 1, 'photo_file_id' => 500, 'vintage_id' => 10],
			['bottle_id' => 2, 'photo_file_id' => 500, 'vintage_id' => 10],
			['bottle_id' => 3, 'photo_file_id' => 500, 'vintage_id' => 10],
		]);

		$this->assertSame([[500, 10]], $this->writtenPairs());
		$this->assertSame([], $this->warnings, 'identical siblings are not a conflict');
	}

	public function testLowestBottleIdWinsAndConflictIsReported(): void {
		// Only reachable for rows predating the propagation.
		$this->runMigration([
			['bottle_id' => 7, 'photo_file_id' => 500, 'vintage_id' => 10],
			['bottle_id' => 9, 'photo_file_id' => 501, 'vintage_id' => 10],
			['bottle_id' => 12, 'photo_file_id' => 502, 'vintage_id' => 10],
		]);

		$this->assertSame([[500, 10]], $this->writtenPairs(), 'earliest photo wins');
		$this->assertCount(1, $this->warnings);
		$this->assertStringContainsString('2', $this->warnings[0], 'both discarded photos are counted');
	}

	public function testEachVintageIsResolvedIndependently(): void {
		$this->runMigration([
			['bottle_id' => 1, 'photo_file_id' => 500, 'vintage_id' => 10],
			['bottle_id' => 2, 'photo_file_id' => 600, 'vintage_id' => 20],
			['bottle_id' => 3, 'photo_file_id' => 601, 'vintage_id' => 20],
		]);

		$pairs = $this->writtenPairs();
		$this->assertContains([500, 10], $pairs);
		$this->assertContains([600, 20], $pairs);
		$this->assertCount(2, $pairs);
	}

	public function testNothingIsWrittenWithoutPhotos(): void {
		$this->runMigration([]);

		$this->assertSame([], $this->statements);
		$this->assertSame([], $this->warnings);
	}

	public function testSecondRunIsANoOp(): void {
		// After Version000108 dropped the source column the step must not touch
		// anything, so a re-run of the upgrade cannot overwrite newer photos.
		$this->runMigration([
			['bottle_id' => 1, 'photo_file_id' => 500, 'vintage_id' => 10],
		], bottleColumnExists: false);

		$this->assertSame([], $this->statements);
	}

	public function testUpdateLeavesAnAlreadySetFrontPhotoAlone(): void {
		$this->runMigration([
			['bottle_id' => 1, 'photo_file_id' => 500, 'vintage_id' => 10],
		]);

		$this->assertStringContainsString('photo_front_file_id IS NULL', $this->statements[0][0]);
	}
}
