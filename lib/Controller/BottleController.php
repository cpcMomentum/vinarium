<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Exception\SlotOccupiedException;
use OCA\Vinarium\Service\BottleService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BottleController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly BottleService $bottleService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(?string $status = null, ?string $color = null, ?int $year = null, ?int $drinkUntilYearBefore = null): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$filter = array_filter([
			'status' => $status,
			'color' => $color,
			'year' => $year,
			'drinkUntilYearBefore' => $drinkUntilYearBefore,
		], static fn ($v): bool => $v !== null && $v !== '');
		return new DataResponse($this->bottleService->getFilteredBottles($this->userId, $filter));
	}

	#[NoAdminRequired]
	public function parked(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		return new DataResponse($this->bottleService->getParkedBottles($this->userId));
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->bottleService->get($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function move(int $id, ?int $slotId = null): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->bottleService->moveBottle($id, $slotId, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (SlotOccupiedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
	}

	#[NoAdminRequired]
	public function consume(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->bottleService->consumeBottle($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$this->bottleService->delete($id, $this->userId);
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	private function unauthorized(): DataResponse {
		return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
	}
}
