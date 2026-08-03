<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Drops vinarium_bottle.photo_file_id after Version000107 has carried the values
 * up to the vintage.
 *
 * Deliberately a separate step: dropping in the same migration that reads the
 * column would mean the source is gone the moment anything in the copy goes
 * wrong. Split in two, an aborted upgrade leaves the original data intact.
 */
class Version000108Date20260803010000 extends SimpleMigrationStep {

	public function name(): string {
		return 'Etikettenfoto: Flaschen-Spalte entfernen';
	}

	public function description(): string {
		return 'Drops the now unused photo_file_id column from vinarium_bottle.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_bottle')) {
			return null;
		}
		$table = $schema->getTable('vinarium_bottle');
		if (!$table->hasColumn('photo_file_id')) {
			return null;
		}
		$table->dropColumn('photo_file_id');

		return $schema;
	}
}
