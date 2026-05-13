<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Level>
 */
class LevelMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_level', Level::class);
	}

	/** @return Level[] ordered by sort_order ASC */
	public function findByCompartment(int $compartmentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('compartment_id', $qb->createNamedParameter($compartmentId, IQueryBuilder::PARAM_INT)))
			->orderBy('sort_order', 'ASC');
		return $this->findEntities($qb);
	}

	public function deleteByCompartment(int $compartmentId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('compartment_id', $qb->createNamedParameter($compartmentId, IQueryBuilder::PARAM_INT)));
		return $qb->executeStatement();
	}
}
