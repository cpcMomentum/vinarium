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
 * @method int getProducerId()
 * @method void setProducerId(int $producerId)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getColor()
 * @method void setColor(string $color)
 * @method ?string getAppellation()
 * @method void setAppellation(?string $appellation)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method ?string getBarcode()
 * @method void setBarcode(?string $barcode)
 */
class Wine extends Entity implements JsonSerializable {
	public const COLOR_RED = 'red';
	public const COLOR_WHITE = 'white';
	public const COLOR_ROSE = 'rose';
	public const COLOR_SPARKLING = 'sparkling';
	public const COLOR_DESSERT = 'dessert';
	public const COLOR_FORTIFIED = 'fortified';

	public const COLORS = [
		self::COLOR_RED, self::COLOR_WHITE, self::COLOR_ROSE,
		self::COLOR_SPARKLING, self::COLOR_DESSERT, self::COLOR_FORTIFIED,
	];

	protected ?int $producerId = null;
	protected ?string $name = null;
	protected ?string $color = null;
	protected ?string $appellation = null;
	protected ?string $notes = null;
	protected ?string $barcode = null;

	public function __construct() {
		$this->addType('producerId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('color', Types::STRING);
		$this->addType('appellation', Types::STRING);
		$this->addType('notes', Types::TEXT);
		$this->addType('barcode', Types::STRING);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'producerId' => $this->getProducerId(),
			'name' => $this->getName(),
			'color' => $this->getColor(),
			'appellation' => $this->getAppellation(),
			'notes' => $this->getNotes(),
			'barcode' => $this->getBarcode(),
		];
	}
}
