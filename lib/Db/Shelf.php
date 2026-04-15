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
 * @method int getCellarId()
 * @method void setCellarId(int $cellarId)
 * @method string getName()
 * @method void setName(string $name)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Shelf extends Entity implements JsonSerializable {
	protected ?int $cellarId = null;
	protected ?string $name = null;
	protected ?int $sortOrder = null;

	public function __construct() {
		$this->addType('cellarId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'cellarId' => $this->getCellarId(),
			'name' => $this->getName(),
			'sortOrder' => $this->getSortOrder(),
		];
	}
}
