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
 * @method string getOwnerUserId()
 * @method void setOwnerUserId(string $ownerUserId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?string getCountry()
 * @method void setCountry(?string $country)
 * @method ?string getRegion()
 * @method void setRegion(?string $region)
 * @method ?string getWebsite()
 * @method void setWebsite(?string $website)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 */
class Producer extends Entity implements JsonSerializable {
	protected ?string $ownerUserId = null;
	protected ?string $name = null;
	protected ?string $country = null;
	protected ?string $region = null;
	protected ?string $website = null;
	protected ?string $notes = null;

	public function __construct() {
		$this->addType('ownerUserId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('country', Types::STRING);
		$this->addType('region', Types::STRING);
		$this->addType('website', Types::STRING);
		$this->addType('notes', Types::TEXT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'ownerUserId' => $this->getOwnerUserId(),
			'name' => $this->getName(),
			'country' => $this->getCountry(),
			'region' => $this->getRegion(),
			'website' => $this->getWebsite(),
			'notes' => $this->getNotes(),
		];
	}
}
