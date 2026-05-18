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

/**
 * Introduce vinarium_level table for per-level slot configuration.
 * Removes levels/columns_front/columns_back from vinarium_compartment.
 */
class Version000103Date20260514000000 extends SimpleMigrationStep {

	public function name(): string {
		return 'Regal-Einstellungen: vinarium_level Tabelle';
	}

	public function description(): string {
		return 'Adds vinarium_level table for per-level slot configuration, simplifies vinarium_compartment.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// --- vinarium_level (new) ---
		if (!$schema->hasTable('vinarium_level')) {
			$table = $schema->createTable('vinarium_level');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
			$table->addColumn('compartment_id', Types::BIGINT, ['notnull' => true]);
			$table->addColumn('level_number', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('columns_front', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('columns_back', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['compartment_id'], 'vinarium_level_comp_idx');
		}

		// --- vinarium_compartment: drop geometry columns ---
		if ($schema->hasTable('vinarium_compartment')) {
			$comp = $schema->getTable('vinarium_compartment');
			foreach (['levels', 'columns_front', 'columns_back'] as $col) {
				if ($comp->hasColumn($col)) {
					$comp->dropColumn($col);
				}
			}
		}

		return $schema;
	}
}
