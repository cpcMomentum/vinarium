<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Db\SlotMapper;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Service\CellarService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class CellarController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly CellarService $cellarService,
		private readonly SlotMapper $slotMapper,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function show(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->cellarService->getActiveCellar($this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$cellar = $this->cellarService->createDefaultCellar($this->userId);
		return new DataResponse($cellar, Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function slots(int $compartmentId): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		// Ownership-Check via getActiveCellar (cheap: validates user has a cellar at all)
		try {
			$active = $this->cellarService->getActiveCellar($this->userId);
		} catch (NotFoundException) {
			return new DataResponse(['error' => 'No cellar'], Http::STATUS_NOT_FOUND);
		}

		$validIds = [];
		foreach ($active['shelves'] as $entry) {
			foreach ($entry['compartments'] as $comp) {
				$validIds[] = $comp->getId();
			}
		}
		if (!in_array($compartmentId, $validIds, true)) {
			return new DataResponse(['error' => 'Compartment not owned'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse($this->slotMapper->findByCompartment($compartmentId));
	}

	private function unauthorized(): DataResponse {
		return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
	}
}
