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
 * @method int getLevel()
 * @method void setLevel(int $level)
 * @method string getRow()
 * @method void setRow(string $row)
 * @method int getColumn()
 * @method void setColumn(int $column)
 */
class Slot extends Entity implements JsonSerializable {
	protected ?int $compartmentId = null;
	protected ?int $level = null;
	protected ?string $row = null;
	protected ?int $column = null;

	public function __construct() {
		$this->addType('compartmentId', Types::INTEGER);
		$this->addType('level', Types::INTEGER);
		$this->addType('row', Types::STRING);
		$this->addType('column', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'compartmentId' => $this->getCompartmentId(),
			'level' => $this->getLevel(),
			'row' => $this->getRow(),
			'column' => $this->getColumn(),
		];
	}
}
