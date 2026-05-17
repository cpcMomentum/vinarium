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
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Introduce vinarium_level table for per-level slot configuration.
 * Removes levels/columns_front/columns_back from vinarium_compartment.
 * Pre-release: all test data is wiped and re-seeded.
 */
class Version000103Date20260514000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

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

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Wipe all test data (pre-release, intentional)
		$this->db->executeStatement('DELETE FROM `*PREFIX*vinarium_slot`');
		$this->db->executeStatement('DELETE FROM `*PREFIX*vinarium_level`');
		$this->db->executeStatement('DELETE FROM `*PREFIX*vinarium_compartment`');
		$this->db->executeStatement('DELETE FROM `*PREFIX*vinarium_shelf`');
		$this->db->executeStatement('DELETE FROM `*PREFIX*vinarium_cellar`');

		// Seed: two shelves with different configs for User01
		$userId = 'User01';

		// Cellar
		$this->db->executeStatement(
			'INSERT INTO `*PREFIX*vinarium_cellar` (owner_user_id, name, created_at) VALUES (?, ?, ?)',
			[$userId, 'Mein Weinkeller', date('Y-m-d H:i:s')]
		);
		$cellarId = (int)$this->db->lastInsertId('*PREFIX*vinarium_cellar');

		// Shelf 1: Holzregal (4 Fächer, 3 Ebenen, 6V/7H)
		$this->db->executeStatement(
			'INSERT INTO `*PREFIX*vinarium_shelf` (cellar_id, name, sort_order) VALUES (?, ?, ?)',
			[$cellarId, 'Holzregal Keller', 0]
		);
		$shelf1Id = (int)$this->db->lastInsertId('*PREFIX*vinarium_shelf');

		for ($c = 0; $c < 4; $c++) {
			$this->db->executeStatement(
				'INSERT INTO `*PREFIX*vinarium_compartment` (shelf_id, label, sort_order) VALUES (?, ?, ?)',
				[$shelf1Id, 'Fach ' . ($c + 1), $c]
			);
			$compId = (int)$this->db->lastInsertId('*PREFIX*vinarium_compartment');
			$this->seedLevelsAndSlots($compId, 3, 6, 7);
		}

		// Shelf 2: Kühlschrank (2 Fächer, 2 Ebenen, nur Vorne 5)
		$this->db->executeStatement(
			'INSERT INTO `*PREFIX*vinarium_shelf` (cellar_id, name, sort_order) VALUES (?, ?, ?)',
			[$cellarId, 'Kühlschrank', 1]
		);
		$shelf2Id = (int)$this->db->lastInsertId('*PREFIX*vinarium_shelf');

		for ($c = 0; $c < 2; $c++) {
			$this->db->executeStatement(
				'INSERT INTO `*PREFIX*vinarium_compartment` (shelf_id, label, sort_order) VALUES (?, ?, ?)',
				[$shelf2Id, 'Fach ' . ($c + 1), $c]
			);
			$compId = (int)$this->db->lastInsertId('*PREFIX*vinarium_compartment');
			$this->seedLevelsAndSlots($compId, 2, 5, null);
		}
	}

	private function seedLevelsAndSlots(int $compId, int $levels, int $front, ?int $back): void {
		for ($l = 0; $l < $levels; $l++) {
			$this->db->executeStatement(
				'INSERT INTO `*PREFIX*vinarium_level` (compartment_id, level_number, columns_front, columns_back, sort_order) VALUES (?, ?, ?, ?, ?)',
				[$compId, $l, $front, $back, $l]
			);
			for ($col = 0; $col < $front; $col++) {
				$this->db->executeStatement(
					'INSERT INTO `*PREFIX*vinarium_slot` (compartment_id, level, row, `column`) VALUES (?, ?, ?, ?)',
					[$compId, $l, 'front', $col]
				);
			}
			if ($back !== null) {
				for ($col = 0; $col < $back; $col++) {
					$this->db->executeStatement(
						'INSERT INTO `*PREFIX*vinarium_slot` (compartment_id, level, row, `column`) VALUES (?, ?, ?, ?)',
						[$compId, $l, 'back', $col]
					);
				}
			}
		}
	}
}
