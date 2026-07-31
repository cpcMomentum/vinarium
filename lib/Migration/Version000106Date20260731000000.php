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
 * Adds the nullable sweetness column to vinarium_vintage.
 *
 * Sweetness sits on the vintage rather than the wine because the same wine can
 * be bottled dry in one year and sweet in the next (Riesling trocken vs.
 * Spaetlese vs. Auslese). No backfill: NULL means "not specified", which is the
 * honest state for every existing row.
 */
class Version000106Date20260731000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

	public function name(): string {
		return 'Jahrgang: Süße-Grad';
	}

	public function description(): string {
		return 'Adds a nullable sweetness column to vinarium_vintage.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_vintage')) {
			return null;
		}
		$table = $schema->getTable('vinarium_vintage');
		if (!$table->hasColumn('sweetness')) {
			$table->addColumn('sweetness', Types::STRING, [
				'notnull' => false,
				'default' => null,
				'length' => 16,
			]);
		}

		return $schema;
	}
}
