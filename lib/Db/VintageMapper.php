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
 * @template-extends QBMapper<Vintage>
 */
class VintageMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'vinarium_vintage', Vintage::class);
	}

	/** @return Vintage[] */
	public function findByWine(int $wineId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->tableName)
			->where($qb->expr()->eq('wine_id', $qb->createNamedParameter($wineId, IQueryBuilder::PARAM_INT)))
			->orderBy('year', 'DESC');
		return $this->findEntities($qb);
	}
}
