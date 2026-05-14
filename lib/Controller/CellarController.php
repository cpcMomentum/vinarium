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
use OCA\Vinarium\Exception\PermissionDeniedException;
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

	/** Creates the first default cellar+shelf for a new user. */
	#[NoAdminRequired]
	public function create(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$cellar = $this->cellarService->createDefaultCellar($this->userId);
		return new DataResponse($cellar, Http::STATUS_CREATED);
	}

	/** Wizard: creates a new shelf with custom level config. */
	#[NoAdminRequired]
	public function createShelf(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$name = trim((string)($this->request->getParam('name') ?? ''));
		$compartmentCount = (int)($this->request->getParam('compartmentCount') ?? 4);
		$levelsConfig = $this->request->getParam('levelsConfig') ?? [];

		if ($name === '') {
			return new DataResponse(['error' => 'Name is required'], Http::STATUS_BAD_REQUEST);
		}
		if (!is_array($levelsConfig) || $levelsConfig === []) {
			return new DataResponse(['error' => 'levelsConfig is required'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$shelf = $this->cellarService->createShelf($this->userId, $name, $compartmentCount, $levelsConfig);
			return new DataResponse($shelf, Http::STATUS_CREATED);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** Destroys a shelf and all its data. Bottles go to Parkzone. */
	#[NoAdminRequired]
	public function destroyShelf(int $shelfId): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$moved = $this->cellarService->destroyShelf($shelfId, $this->userId);
			return new DataResponse(['movedToParkzone' => $moved]);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	/** Returns slots for a compartment (used by frontend to render shelf). */
	#[NoAdminRequired]
	public function slots(int $compartmentId): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$active = $this->cellarService->getActiveCellar($this->userId);
		} catch (NotFoundException) {
			return new DataResponse(['error' => 'No cellar'], Http::STATUS_NOT_FOUND);
		}

		if (!$this->compartmentBelongsToCellar($compartmentId, $active)) {
			return new DataResponse(['error' => 'Compartment not owned'], Http::STATUS_FORBIDDEN);
		}

		return new DataResponse($this->slotMapper->findByCompartment($compartmentId));
	}

	/** Reconfigures levels+slots for a compartment. Bottles go to Parkzone. */
	#[NoAdminRequired]
	public function reconfigure(int $compartmentId): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$levelsConfig = $this->request->getParam('levelsConfig') ?? [];
		if (!is_array($levelsConfig) || $levelsConfig === []) {
			return new DataResponse(['error' => 'levelsConfig is required'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$moved = $this->cellarService->reconfigureCompartment($compartmentId, $levelsConfig, $this->userId);
			return new DataResponse(['movedToParkzone' => $moved]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	private function compartmentBelongsToCellar(int $compartmentId, array $cellarData): bool {
		foreach ($cellarData['shelves'] as $entry) {
			foreach ($entry['compartments'] as $compData) {
				if ($compData['compartment']->getId() === $compartmentId) {
					return true;
				}
			}
		}
		return false;
	}

	private function unauthorized(): DataResponse {
		return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
	}
}
