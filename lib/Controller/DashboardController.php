<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Service\DashboardService;
use OCA\Vinarium\Service\ExportService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class DashboardController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly DashboardService $dashboardService,
		private readonly ExportService $exportService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function stats(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->dashboardService->getStats($this->userId));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function exportCsv(): DataDownloadResponse {
		$csv = $this->exportService->exportCsv($this->userId ?? '');
		return new DataDownloadResponse(
			$csv,
			'vinarium-export-' . date('Y-m-d') . '.csv',
			'text/csv; charset=utf-8',
		);
	}
}
