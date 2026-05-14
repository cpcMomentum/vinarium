<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getCompartmentId()
 * @method void setCompartmentId(int $compartmentId)
 * @method int getLevelNumber()
 * @method void setLevelNumber(int $levelNumber)
 * @method int getColumnsFront()
 * @method void setColumnsFront(int $columnsFront)
 * @method int|null getColumnsBack()
 * @method void setColumnsBack(?int $columnsBack)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Level extends Entity implements JsonSerializable {
	protected ?int $compartmentId = null;
	protected ?int $levelNumber = null;
	protected ?int $columnsFront = null;
	protected ?int $columnsBack = null;
	protected ?int $sortOrder = null;

	public function __construct() {
		$this->addType('compartmentId', Types::INTEGER);
		$this->addType('levelNumber', Types::INTEGER);
		$this->addType('columnsFront', Types::INTEGER);
		$this->addType('columnsBack', Types::INTEGER);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'compartmentId' => $this->getCompartmentId(),
			'levelNumber' => $this->getLevelNumber(),
			'columnsFront' => $this->getColumnsFront(),
			'columnsBack' => $this->getColumnsBack(),
			'sortOrder' => $this->getSortOrder(),
		];
	}
}
