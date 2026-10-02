<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Service\WhatsNewService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * „Was ist neu?"-Fenster (#284). Liefert die noch nicht gesehenen Eintraege der
 * laufenden Version und nimmt die Quittung entgegen.
 *
 * Anders als in der Vorlage (RechnungsWerk) gibt es hier kein Freigabe-Gate:
 * Vinarium ist eine private App, jedes Konto fuehrt seinen eigenen Keller.
 */
class WhatsNewController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly WhatsNewService $whatsNewService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Eintraege, die dieser Nutzer noch nicht gesehen hat. Leere Liste heisst:
	 * kein Fenster.
	 */
	#[NoAdminRequired]
	public function index(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->whatsNewService->getPending($this->userId));
	}

	/**
	 * Alle bisherigen Neuerungen, nach Version gruppiert (#298) — fuer den
	 * dauerhaften Menue-Eintrag „Neuerungen". Beruehrt keine Marke.
	 */
	#[NoAdminRequired]
	public function all(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->whatsNewService->getAll());
	}

	/** Quittiert das Fenster: die laufende Version gilt als gesehen. */
	#[NoAdminRequired]
	public function seen(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		$this->whatsNewService->markSeen($this->userId);
		return new DataResponse([], Http::STATUS_NO_CONTENT);
	}
}
