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
 * @method int getBottleId()
 * @method void setBottleId(int $bottleId)
 * @method DateTime getTastedAt()
 * @method void setTastedAt(DateTime $tastedAt)
 * @method ?float getRating()
 * @method void setRating(?float $rating)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method ?string getOccasion()
 * @method void setOccasion(?string $occasion)
 * @method ?string getCompanions()
 * @method void setCompanions(?string $companions)
 * @method ?array getPhotoFileIds()
 * @method void setPhotoFileIds(?array $photoFileIds)
 */
class Tasting extends Entity implements JsonSerializable {
	protected ?int $bottleId = null;
	protected ?DateTime $tastedAt = null;
	protected ?float $rating = null;
	protected ?string $notes = null;
	protected ?string $occasion = null;
	protected ?string $companions = null;
	protected ?array $photoFileIds = null;

	public function __construct() {
		$this->addType('bottleId', Types::INTEGER);
		$this->addType('tastedAt', Types::DATETIME);
		$this->addType('rating', Types::FLOAT);
		$this->addType('notes', Types::TEXT);
		$this->addType('occasion', Types::STRING);
		$this->addType('companions', Types::STRING);
		$this->addType('photoFileIds', Types::JSON);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'bottleId' => $this->getBottleId(),
			'tastedAt' => $this->getTastedAt()?->format(DateTime::ATOM),
			'rating' => $this->getRating(),
			'notes' => $this->getNotes(),
			'occasion' => $this->getOccasion(),
			'companions' => $this->getCompanions(),
			'photoFileIds' => $this->getPhotoFileIds(),
		];
	}
}
