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
 * Move grape_varieties from vinarium_wine to vinarium_vintage.
 *
 * Rationale: grape blend composition varies per vintage (e.g. Bordeaux
 * Merlot/CabFranc ratio shifts year over year). Keeping it on Wine
 * (cuvee level) forces a single invariant blend, which the domain
 * doesn't support.
 */
class Version000101Date20260415210000 extends SimpleMigrationStep {

	public function name(): string {
		return 'Move grape_varieties from wine to vintage';
	}

	public function description(): string {
		return 'Grape blend composition is vintage-specific, not cuvee-invariant.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('vinarium_wine')) {
			$wineTable = $schema->getTable('vinarium_wine');
			if ($wineTable->hasColumn('grape_varieties')) {
				$wineTable->dropColumn('grape_varieties');
			}
		}

		if ($schema->hasTable('vinarium_vintage')) {
			$vintageTable = $schema->getTable('vinarium_vintage');
			if (!$vintageTable->hasColumn('grape_varieties')) {
				$vintageTable->addColumn('grape_varieties', Types::TEXT, ['notnull' => false]);
			}
		}

		return $schema;
	}
}
