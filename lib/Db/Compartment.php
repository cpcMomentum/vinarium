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
 * @method int getShelfId()
 * @method void setShelfId(int $shelfId)
 * @method string getLabel()
 * @method void setLabel(string $label)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 * @method int getLevels()
 * @method void setLevels(int $levels)
 * @method int getColumnsFront()
 * @method void setColumnsFront(int $columnsFront)
 * @method int getColumnsBack()
 * @method void setColumnsBack(int $columnsBack)
 */
class Compartment extends Entity implements JsonSerializable {
	protected ?int $shelfId = null;
	protected ?string $label = null;
	protected ?int $sortOrder = null;
	protected ?int $levels = null;
	protected ?int $columnsFront = null;
	protected ?int $columnsBack = null;

	public function __construct() {
		$this->addType('shelfId', Types::INTEGER);
		$this->addType('label', Types::STRING);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('levels', Types::INTEGER);
		$this->addType('columnsFront', Types::INTEGER);
		$this->addType('columnsBack', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'shelfId' => $this->getShelfId(),
			'label' => $this->getLabel(),
			'sortOrder' => $this->getSortOrder(),
			'levels' => $this->getLevels(),
			'columnsFront' => $this->getColumnsFront(),
			'columnsBack' => $this->getColumnsBack(),
		];
	}
}
