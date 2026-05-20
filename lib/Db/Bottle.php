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
 * @method int getPurchaseId()
 * @method void setPurchaseId(int $purchaseId)
 * @method ?int getSlotId()
 * @method void setSlotId(?int $slotId)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?int getPhotoFileId()
 * @method void setPhotoFileId(?int $photoFileId)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method ?DateTime getEventDate()
 * @method void setEventDate(?DateTime $eventDate)
 * @method ?string getEventRecipient()
 * @method void setEventRecipient(?string $eventRecipient)
 * @method ?string getEventNote()
 * @method void setEventNote(?string $eventNote)
 */
class Bottle extends Entity implements JsonSerializable {
	public const STATUS_IN_STORAGE = 'in_storage';
	public const STATUS_CONSUMED = 'consumed';
	public const STATUS_GIFTED = 'gifted';
	public const STATUS_LOST = 'lost';

	public const STATUSES = [
		self::STATUS_IN_STORAGE, self::STATUS_CONSUMED,
		self::STATUS_GIFTED, self::STATUS_LOST,
	];

	protected ?int $purchaseId = null;
	protected ?int $slotId = null;
	protected ?string $status = null;
	protected ?int $photoFileId = null;
	protected ?string $notes = null;
	protected ?DateTime $eventDate = null;
	protected ?string $eventRecipient = null;
	protected ?string $eventNote = null;

	public function __construct() {
		$this->addType('purchaseId', Types::INTEGER);
		$this->addType('slotId', Types::INTEGER);
		$this->addType('status', Types::STRING);
		$this->addType('photoFileId', Types::INTEGER);
		$this->addType('notes', Types::TEXT);
		$this->addType('eventDate', Types::DATE);
		$this->addType('eventRecipient', Types::STRING);
		$this->addType('eventNote', Types::TEXT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'purchaseId' => $this->getPurchaseId(),
			'slotId' => $this->getSlotId(),
			'status' => $this->getStatus(),
			'photoFileId' => $this->getPhotoFileId(),
			'notes' => $this->getNotes(),
			'eventDate' => $this->getEventDate()?->format('Y-m-d'),
			'eventRecipient' => $this->getEventRecipient(),
			'eventNote' => $this->getEventNote(),
		];
	}
}
