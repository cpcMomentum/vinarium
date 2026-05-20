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
 * Adds event columns to vinarium_bottle for gifted/lost bottles:
 * event_date, event_recipient (gift only), event_note (occasion / reason).
 */
class Version000104Date20260520000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

	public function name(): string {
		return 'Flaschen-Status: Verschenkt/Verloren Felder';
	}

	public function description(): string {
		return 'Adds event_date, event_recipient and event_note columns to vinarium_bottle for gifted/lost bottles.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_bottle')) {
			return null;
		}
		$table = $schema->getTable('vinarium_bottle');
		if (!$table->hasColumn('event_date')) {
			$table->addColumn('event_date', Types::DATE, ['notnull' => false]);
		}
		if (!$table->hasColumn('event_recipient')) {
			$table->addColumn('event_recipient', Types::STRING, ['notnull' => false, 'length' => 255]);
		}
		if (!$table->hasColumn('event_note')) {
			$table->addColumn('event_note', Types::TEXT, ['notnull' => false]);
		}

		return $schema;
	}
}
