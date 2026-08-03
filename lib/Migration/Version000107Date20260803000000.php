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
 * Moves the label photo from the bottle up to the vintage and makes room for a
 * second one (front and back of the label).
 *
 * A label describes the bottling, not the individual bottle, so the vintage is
 * the level it belongs to. Until now the reference was held redundantly on every
 * bottle row and kept in sync by BottleService::setPhotoAndPropagate(); storing
 * it once removes that machinery along with the possibility of diverging copies.
 *
 * Existing bottle photos are carried up as the FRONT side. Where several bottles
 * of one vintage carry different files, the lowest bottle id wins — the earliest
 * taken photo. Divergence is only possible for rows created before 9d4ac85
 * (2026-06-08), which introduced the propagation; afterwards all bottles of a
 * vintage hold the same value.
 *
 * Nothing is deleted here: the source column survives this step and is dropped
 * separately in Version000108, and no file in the user's storage is touched.
 */
class Version000107Date20260803000000 extends SimpleMigrationStep {

	public function __construct(private readonly IDBConnection $db) {
	}

	public function name(): string {
		return 'Etikettenfoto: Vorder- und Rückseite am Jahrgang';
	}

	public function description(): string {
		return 'Adds photo_front_file_id/photo_back_file_id to vinarium_vintage and carries existing bottle photos up.';
	}

	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('vinarium_vintage')) {
			return null;
		}
		$table = $schema->getTable('vinarium_vintage');
		foreach (['photo_front_file_id', 'photo_back_file_id'] as $column) {
			if (!$table->hasColumn($column)) {
				$table->addColumn($column, Types::BIGINT, [
					'notnull' => false,
					'default' => null,
					'length' => 20,
				]);
			}
		}

		return $schema;
	}

	#[\Override]
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('vinarium_bottle') || !$schema->hasTable('vinarium_purchase')) {
			return;
		}
		if (!$schema->getTable('vinarium_bottle')->hasColumn('photo_file_id')) {
			// Already migrated in an earlier run.
			return;
		}

		// Ordered by bottle id so that the first row seen per vintage is the winner;
		// resolving the conflict in PHP keeps the statement portable across the
		// database platforms Nextcloud supports.
		$rows = $this->db->executeQuery(
			'SELECT b.id AS bottle_id, b.photo_file_id, p.vintage_id '
			. 'FROM `*PREFIX*vinarium_bottle` b '
			. 'JOIN `*PREFIX*vinarium_purchase` p ON p.id = b.purchase_id '
			. 'WHERE b.photo_file_id IS NOT NULL '
			. 'ORDER BY b.id ASC'
		)->fetchAll();

		$winners = [];
		$discarded = 0;
		foreach ($rows as $row) {
			$vintageId = (int)$row['vintage_id'];
			$fileId = (int)$row['photo_file_id'];
			if (!isset($winners[$vintageId])) {
				$winners[$vintageId] = $fileId;
				continue;
			}
			if ($winners[$vintageId] !== $fileId) {
				$discarded++;
			}
		}

		$migrated = 0;
		foreach ($winners as $vintageId => $fileId) {
			$migrated += $this->db->executeStatement(
				'UPDATE `*PREFIX*vinarium_vintage` SET photo_front_file_id = ? '
				. 'WHERE id = ? AND photo_front_file_id IS NULL',
				[$fileId, $vintageId]
			);
		}

		$output->info(sprintf('Etikettenfotos an den Jahrgang übertragen: %d', $migrated));
		if ($discarded > 0) {
			$output->warning(sprintf(
				'%d abweichende Flaschenfotos wurden nicht übernommen (je Jahrgang gewinnt die niedrigste Flaschen-ID). '
				. 'Die Dateien bleiben im Speicher erhalten.',
				$discarded
			));
		}
	}
}
