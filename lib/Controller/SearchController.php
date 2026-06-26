<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Service\SearchService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SearchController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly SearchService $searchService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(string $q = ''): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->searchService->search($this->userId, $q));
	}
}
