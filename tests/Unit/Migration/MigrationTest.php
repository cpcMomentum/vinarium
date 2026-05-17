<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Migration;

use Closure;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use OCA\Vinarium\Migration\Version000100Date20260415120000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MigrationTest extends TestCase {

	/** @var string[] */
	private array $createdTables = [];
	/** @var array<string, array<string, string>> table => [column => type] */
	private array $columnsPerTable = [];
	/** @var array<string, list<array{localColumns: list<string>, foreignTable: string, foreignColumns: list<string>, options: array}>> */
	private array $foreignKeysPerTable = [];
	/** @var array<string, list<array{columns: list<string>, unique: bool, name: ?string}>> */
	private array $indexesPerTable = [];

	private function buildSchemaMock(): ISchemaWrapper&MockObject {
		$this->createdTables = [];
		$this->columnsPerTable = [];
		$this->foreignKeysPerTable = [];
		$this->indexesPerTable = [];

		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturn(false);
		$schema->method('createTable')->willReturnCallback(function (string $name): Table {
			$this->createdTables[] = $name;
			$this->columnsPerTable[$name] = [];
			$this->foreignKeysPerTable[$name] = [];
			$this->indexesPerTable[$name] = [];
			return $this->buildTableMock($name);
		});
		return $schema;
	}

	private function buildTableMock(string $tableName): Table&MockObject {
		$table = $this->createMock(Table::class);

		$table->method('addColumn')->willReturnCallback(
			function (string $colName, string $type, array $options = []) use ($tableName): Column {
				$this->columnsPerTable[$tableName][$colName] = $type;
				$column = $this->createMock(Column::class);
				$column->method('setDefault')->willReturnSelf();
				$column->method('setNotnull')->willReturnSelf();
				$column->method('setLength')->willReturnSelf();
				$column->method('setAutoincrement')->willReturnSelf();
				return $column;
			}
		);

		$table->method('setPrimaryKey')->willReturnSelf();

		$table->method('addIndex')->willReturnCallback(
			function (array $columns, ?string $name = null) use ($tableName): Table {
				$this->indexesPerTable[$tableName][] = [
					'columns' => $columns,
					'unique' => false,
					'name' => $name,
				];
				return $this->buildTableMock($tableName);
			}
		);

		$table->method('addUniqueIndex')->willReturnCallback(
			function (array $columns, ?string $name = null) use ($tableName): Table {
				$this->indexesPerTable[$tableName][] = [
					'columns' => $columns,
					'unique' => true,
					'name' => $name,
				];
				return $this->buildTableMock($tableName);
			}
		);

		$table->method('addForeignKeyConstraint')->willReturnCallback(
			function ($foreignTable, array $localCols, array $foreignCols, array $options = []) use ($tableName): Table {
				$foreignName = $foreignTable instanceof Table ? $foreignTable->getName() : (string)$foreignTable;
				$this->foreignKeysPerTable[$tableName][] = [
					'localColumns' => $localCols,
					'foreignTable' => $foreignName,
					'foreignColumns' => $foreignCols,
					'options' => $options,
				];
				return $this->buildTableMock($tableName);
			}
		);

		return $table;
	}

	private function runMigration(): void {
		$migration = new Version000100Date20260415120000();
		$output = $this->createMock(IOutput::class);
		$schema = $this->buildSchemaMock();
		$closure = static fn(): ISchemaWrapper => $schema;

		$migration->changeSchema($output, $closure, []);
	}

	public function testAllTenTablesCreated(): void {
		$this->runMigration();
		$expected = [
			'vinarium_cellar', 'vinarium_shelf', 'vinarium_compartment', 'vinarium_slot',
			'vinarium_producer', 'vinarium_wine', 'vinarium_vintage', 'vinarium_purchase',
			'vinarium_bottle', 'vinarium_tasting',
		];
		sort($expected);
		$actual = $this->createdTables;
		sort($actual);
		$this->assertSame($expected, $actual);
	}

	public function testCellarHasOwnerAndName(): void {
		$this->runMigration();
		$this->assertArrayHasKey('owner_user_id', $this->columnsPerTable['vinarium_cellar']);
		$this->assertArrayHasKey('name', $this->columnsPerTable['vinarium_cellar']);
		$this->assertSame('string', $this->columnsPerTable['vinarium_cellar']['owner_user_id']);
	}

	public function testBottleHasSlotAndPurchaseColumns(): void {
		$this->runMigration();
		$this->assertArrayHasKey('slot_id', $this->columnsPerTable['vinarium_bottle']);
		$this->assertArrayHasKey('purchase_id', $this->columnsPerTable['vinarium_bottle']);
		$this->assertArrayHasKey('status', $this->columnsPerTable['vinarium_bottle']);
		$this->assertSame('bigint', $this->columnsPerTable['vinarium_bottle']['slot_id']);
	}

	public function testTastingPhotoFileIdsIsJson(): void {
		$this->runMigration();
		$this->assertArrayHasKey('photo_file_ids', $this->columnsPerTable['vinarium_tasting']);
		$this->assertSame('json', $this->columnsPerTable['vinarium_tasting']['photo_file_ids']);
	}

	public function testRequiredIndexes(): void {
		$this->runMigration();
		$this->assertIndexOnColumns('vinarium_cellar', ['owner_user_id'], false);
		$this->assertIndexOnColumns('vinarium_shelf', ['cellar_id'], false);
		$this->assertIndexOnColumns('vinarium_slot', ['compartment_id', 'level', 'row', 'column'], true);
		$this->assertIndexOnColumns('vinarium_bottle', ['slot_id'], false);
	}

	private function assertIndexOnColumns(string $table, array $columns, bool $unique): void {
		foreach ($this->indexesPerTable[$table] ?? [] as $idx) {
			if ($idx['columns'] === $columns && $idx['unique'] === $unique) {
				$this->addToAssertionCount(1);
				return;
			}
		}
		$this->fail(sprintf(
			'Expected %sindex on %s(%s), none found',
			$unique ? 'unique ' : '',
			$table,
			implode(',', $columns),
		));
	}
}
