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
 * @template-extends QBMapper<Tasting>
 */
class TastingMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_tasting', Tasting::class);
	}

	/** @return Tasting[] */
	public function findByBottle(int $bottleId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('bottle_id', $qb->createNamedParameter($bottleId, IQueryBuilder::PARAM_INT)))
			->orderBy('tasted_at', 'DESC');
		return $this->findEntities($qb);
	}
}
