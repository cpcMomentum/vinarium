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
 * @method int getWineId()
 * @method void setWineId(int $wineId)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method ?float getAlcoholPercent()
 * @method void setAlcoholPercent(?float $alcoholPercent)
 * @method ?string getGrapeVarieties()
 * @method void setGrapeVarieties(?string $grapeVarieties)
 * @method ?int getDrinkFromYear()
 * @method void setDrinkFromYear(?int $drinkFromYear)
 * @method ?int getDrinkUntilYear()
 * @method void setDrinkUntilYear(?int $drinkUntilYear)
 * @method ?float getExternalRating()
 * @method void setExternalRating(?float $externalRating)
 * @method ?string getExternalRatingSource()
 * @method void setExternalRatingSource(?string $externalRatingSource)
 * @method ?string getDescription()
 * @method void setDescription(?string $description)
 * @method ?string getReferenceUrl()
 * @method void setReferenceUrl(?string $referenceUrl)
 * @method ?string getSweetness()
 * @method void setSweetness(?string $sweetness)
 * @method ?int getPhotoFrontFileId()
 * @method void setPhotoFrontFileId(?int $photoFrontFileId)
 * @method ?int getPhotoBackFileId()
 * @method void setPhotoBackFileId(?int $photoBackFileId)
 */
class Vintage extends Entity implements JsonSerializable {
	protected ?int $wineId = null;
	protected ?int $year = null;
	protected ?float $alcoholPercent = null;
	protected ?string $grapeVarieties = null;
	protected ?int $drinkFromYear = null;
	protected ?int $drinkUntilYear = null;
	protected ?float $externalRating = null;
	protected ?string $externalRatingSource = null;
	protected ?string $description = null;
	protected ?string $referenceUrl = null;
	protected ?string $sweetness = null;
	protected ?int $photoFrontFileId = null;
	protected ?int $photoBackFileId = null;

	/** Sweetness levels following the EU labelling terms; NULL = not specified. */
	public const SWEETNESS_VALUES = ['dry', 'off_dry', 'medium_sweet', 'sweet'];

	/**
	 * The two sides of the label. Front doubles as the main image in lists and
	 * tiles; back carries the details printed on the reverse (grape varieties,
	 * vineyard, bottler).
	 */
	public const PHOTO_SIDES = ['front', 'back'];

	public function __construct() {
		$this->addType('wineId', Types::INTEGER);
		$this->addType('year', Types::INTEGER);
		$this->addType('alcoholPercent', Types::FLOAT);
		$this->addType('grapeVarieties', Types::TEXT);
		$this->addType('drinkFromYear', Types::INTEGER);
		$this->addType('drinkUntilYear', Types::INTEGER);
		$this->addType('externalRating', Types::FLOAT);
		$this->addType('externalRatingSource', Types::STRING);
		$this->addType('description', Types::TEXT);
		$this->addType('referenceUrl', Types::STRING);
		$this->addType('sweetness', Types::STRING);
		$this->addType('photoFrontFileId', Types::INTEGER);
		$this->addType('photoBackFileId', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'wineId' => $this->getWineId(),
			'year' => $this->getYear(),
			'alcoholPercent' => $this->getAlcoholPercent(),
			'grapeVarieties' => $this->getGrapeVarieties(),
			'drinkFromYear' => $this->getDrinkFromYear(),
			'drinkUntilYear' => $this->getDrinkUntilYear(),
			'externalRating' => $this->getExternalRating(),
			'externalRatingSource' => $this->getExternalRatingSource(),
			'description' => $this->getDescription(),
			'referenceUrl' => $this->getReferenceUrl(),
			'sweetness' => $this->getSweetness(),
			'photoFrontFileId' => $this->getPhotoFrontFileId(),
			'photoBackFileId' => $this->getPhotoBackFileId(),
		];
	}
}
