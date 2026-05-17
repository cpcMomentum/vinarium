<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Db;

use DateTime;
use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getOwnerUserId()
 * @method void setOwnerUserId(string $ownerUserId)
 * @method string getName()
 * @method void setName(string $name)
 * @method DateTime getCreatedAt()
 * @method void setCreatedAt(DateTime $createdAt)
 */
class Cellar extends Entity implements JsonSerializable {
	protected ?string $ownerUserId = null;
	protected ?string $name = null;
	protected ?DateTime $createdAt = null;

	public function __construct() {
		$this->addType('ownerUserId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'ownerUserId' => $this->getOwnerUserId(),
			'name' => $this->getName(),
			'createdAt' => $this->getCreatedAt()?->format(DateTime::ATOM),
		];
	}
}
