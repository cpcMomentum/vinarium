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
 * @method int getVintageId()
 * @method void setVintageId(int $vintageId)
 * @method DateTime getPurchasedAt()
 * @method void setPurchasedAt(DateTime $purchasedAt)
 * @method ?string getVendor()
 * @method void setVendor(?string $vendor)
 * @method ?float getUnitPrice()
 * @method void setUnitPrice(?float $unitPrice)
 * @method ?string getCurrency()
 * @method void setCurrency(?string $currency)
 * @method int getQuantity()
 * @method void setQuantity(int $quantity)
 * @method int getBottleSizeMl()
 * @method void setBottleSizeMl(int $bottleSizeMl)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 */
class Purchase extends Entity implements JsonSerializable {
	protected ?int $vintageId = null;
	protected ?DateTime $purchasedAt = null;
	protected ?string $vendor = null;
	protected ?float $unitPrice = null;
	protected ?string $currency = null;
	protected ?int $quantity = null;
	protected ?int $bottleSizeMl = null;
	protected ?string $notes = null;

	public function __construct() {
		$this->addType('vintageId', Types::INTEGER);
		$this->addType('purchasedAt', Types::DATETIME);
		$this->addType('vendor', Types::STRING);
		$this->addType('unitPrice', Types::FLOAT);
		$this->addType('currency', Types::STRING);
		$this->addType('quantity', Types::INTEGER);
		$this->addType('bottleSizeMl', Types::INTEGER);
		$this->addType('notes', Types::TEXT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'vintageId' => $this->getVintageId(),
			'purchasedAt' => $this->getPurchasedAt()?->format(DateTime::ATOM),
			'vendor' => $this->getVendor(),
			'unitPrice' => $this->getUnitPrice(),
			'currency' => $this->getCurrency(),
			'quantity' => $this->getQuantity(),
			'bottleSizeMl' => $this->getBottleSizeMl(),
			'notes' => $this->getNotes(),
		];
	}
}
