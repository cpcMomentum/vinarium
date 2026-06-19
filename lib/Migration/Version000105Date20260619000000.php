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
 * Adds the nullable would_rebuy flag to vinarium_tasting:
 * tri-state recommendation signal (ja / nein / keine Angabe = NULL),
 * independent of the numeric rating.
 */
class Version000105Date20260619000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

	public function name(): string {
		return 'Verkostung: Würde-ich-wieder-kaufen-Flag';
	}

	public function description(): string {
		return 'Adds a nullable would_rebuy boolean column to vinarium_tasting.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_tasting')) {
			return null;
		}
		$table = $schema->getTable('vinarium_tasting');
		if (!$table->hasColumn('would_rebuy')) {
			$table->addColumn('would_rebuy', Types::BOOLEAN, ['notnull' => false, 'default' => null]);
		}

		return $schema;
	}
}
