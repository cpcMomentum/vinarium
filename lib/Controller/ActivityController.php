<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\ActivityService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class ActivityController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly ActivityService $activityService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(
		?string $type = null,
		?string $from = null,
		?string $to = null,
		?int $limit = null,
		?int $offset = null,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->activityService->stream($this->userId, array_filter([
				'type' => $type,
				'from' => $from,
				'to' => $to,
				'limit' => $limit,
				'offset' => $offset,
			], static fn ($v): bool => $v !== null && $v !== '')));
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
