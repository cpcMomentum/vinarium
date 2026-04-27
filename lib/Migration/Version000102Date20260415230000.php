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
 * Drinking window is year-only, not a calendar date.
 *
 * Replaces drink_from/drink_until DATETIME columns with
 * drink_from_year/drink_until_year INTEGER columns on vinarium_vintage.
 * Pre-release, no production data to preserve.
 */
class Version000102Date20260415230000 extends SimpleMigrationStep {

	public function name(): string {
		return 'Drink window year-only';
	}

	public function description(): string {
		return 'Replace vinarium_vintage drink_from/drink_until DATETIME with INTEGER year columns.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_vintage')) {
			return $schema;
		}
		$table = $schema->getTable('vinarium_vintage');

		if ($table->hasColumn('drink_from')) {
			$table->dropColumn('drink_from');
		}
		if ($table->hasColumn('drink_until')) {
			$table->dropColumn('drink_until');
		}
		if (!$table->hasColumn('drink_from_year')) {
			$table->addColumn('drink_from_year', Types::INTEGER, ['notnull' => false]);
		}
		if (!$table->hasColumn('drink_until_year')) {
			$table->addColumn('drink_until_year', Types::INTEGER, ['notnull' => false]);
		}

		return $schema;
	}
}
