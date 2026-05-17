<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000100Date20260415120000 extends SimpleMigrationStep {

	public function name(): string {
		return 'Schema v0.1.0 (Vinarium MVP)';
	}

	public function description(): string {
		return 'Create initial schema: cellar, shelf, compartment, slot, producer, wine, vintage, purchase, bottle, tasting.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->createCellarTable($schema);
		$this->createShelfTable($schema);
		$this->createCompartmentTable($schema);
		$this->createSlotTable($schema);
		$this->createProducerTable($schema);
		$this->createWineTable($schema);
		$this->createVintageTable($schema);
		$this->createPurchaseTable($schema);
		$this->createBottleTable($schema);
		$this->createTastingTable($schema);

		return $schema;
	}

	private function createCellarTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_cellar')) {
			return;
		}
		$table = $schema->createTable('vinarium_cellar');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('owner_user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['owner_user_id'], 'vinarium_cellar_owner');
	}

	private function createShelfTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_shelf')) {
			return;
		}
		$table = $schema->createTable('vinarium_shelf');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('cellar_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['cellar_id'], 'vinarium_shelf_cellar');
		$table->addIndex(['cellar_id', 'sort_order'], 'vinarium_shelf_order');
	}

	private function createCompartmentTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_compartment')) {
			return;
		}
		$table = $schema->createTable('vinarium_compartment');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('shelf_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
		$table->addColumn('levels', Types::INTEGER, ['notnull' => true, 'default' => 3]);
		$table->addColumn('columns_front', Types::INTEGER, ['notnull' => true, 'default' => 6]);
		$table->addColumn('columns_back', Types::INTEGER, ['notnull' => true, 'default' => 7]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['shelf_id'], 'vinarium_comp_shelf');
	}

	private function createSlotTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_slot')) {
			return;
		}
		$table = $schema->createTable('vinarium_slot');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('compartment_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('level', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('row', Types::STRING, ['notnull' => true, 'length' => 10]);
		$table->addColumn('column', Types::INTEGER, ['notnull' => true]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['compartment_id', 'level', 'row', 'column'], 'vinarium_slot_pos');
	}

	private function createProducerTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_producer')) {
			return;
		}
		$table = $schema->createTable('vinarium_producer');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('owner_user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('country', Types::STRING, ['notnull' => false, 'length' => 100]);
		$table->addColumn('region', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('website', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['owner_user_id'], 'vinarium_prod_owner');
	}

	private function createWineTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_wine')) {
			return;
		}
		$table = $schema->createTable('vinarium_wine');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('producer_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
		$table->addColumn('color', Types::STRING, ['notnull' => true, 'length' => 20]);
		$table->addColumn('grape_varieties', Types::TEXT, ['notnull' => false]);
		$table->addColumn('appellation', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('barcode', Types::STRING, ['notnull' => false, 'length' => 32]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['producer_id'], 'vinarium_wine_producer');
		$table->addIndex(['barcode'], 'vinarium_wine_barcode');
	}

	private function createVintageTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_vintage')) {
			return;
		}
		$table = $schema->createTable('vinarium_vintage');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('wine_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('year', Types::INTEGER, ['notnull' => true]);
		$table->addColumn('alcohol_percent', Types::FLOAT, ['notnull' => false]);
		$table->addColumn('drink_from', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('drink_until', Types::DATETIME, ['notnull' => false]);
		$table->addColumn('external_rating', Types::FLOAT, ['notnull' => false]);
		$table->addColumn('external_rating_source', Types::STRING, ['notnull' => false, 'length' => 100]);
		$table->addColumn('description', Types::TEXT, ['notnull' => false]);
		$table->addColumn('reference_url', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['wine_id'], 'vinarium_vintage_wine');
	}

	private function createPurchaseTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_purchase')) {
			return;
		}
		$table = $schema->createTable('vinarium_purchase');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('vintage_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('purchased_at', Types::DATETIME, ['notnull' => true]);
		$table->addColumn('vendor', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('unit_price', Types::FLOAT, ['notnull' => false]);
		$table->addColumn('currency', Types::STRING, ['notnull' => false, 'length' => 3]);
		$table->addColumn('quantity', Types::INTEGER, ['notnull' => true, 'default' => 1]);
		$table->addColumn('bottle_size_ml', Types::INTEGER, ['notnull' => true, 'default' => 750]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['vintage_id', 'purchased_at'], 'vinarium_purch_vintage');
	}

	private function createBottleTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_bottle')) {
			return;
		}
		$table = $schema->createTable('vinarium_bottle');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('purchase_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('slot_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
		$table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 20, 'default' => 'in_storage']);
		$table->addColumn('photo_file_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['purchase_id'], 'vinarium_bottle_purchase');
		$table->addIndex(['slot_id'], 'vinarium_bottle_slot');
		$table->addIndex(['status'], 'vinarium_bottle_status');
	}

	private function createTastingTable(ISchemaWrapper $schema): void {
		if ($schema->hasTable('vinarium_tasting')) {
			return;
		}
		$table = $schema->createTable('vinarium_tasting');
		$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
		$table->addColumn('bottle_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
		$table->addColumn('tasted_at', Types::DATETIME, ['notnull' => true]);
		$table->addColumn('rating', Types::FLOAT, ['notnull' => false]);
		$table->addColumn('notes', Types::TEXT, ['notnull' => false]);
		$table->addColumn('occasion', Types::STRING, ['notnull' => false, 'length' => 255]);
		$table->addColumn('companions', Types::STRING, ['notnull' => false, 'length' => 500]);
		$table->addColumn('photo_file_ids', Types::JSON, ['notnull' => false]);
		$table->setPrimaryKey(['id']);
		$table->addIndex(['bottle_id', 'tasted_at'], 'vinarium_tast_bottle');
	}
}
