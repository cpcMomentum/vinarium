<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Controller;

use OCA\Vinarium\AppInfo\Application;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\PhotoService;
use OCA\Vinarium\Service\VintageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class VintageController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly VintageService $vintageService,
		private readonly PhotoService $photoService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(int $wineId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->vintageService->listByWine($wineId, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Bottles in storage per vintage, as a map keyed by vintage id.
	 *
	 * Routed above show() in routes.php — otherwise /vintages/{id} swallows
	 * the literal path segment "stock".
	 */
	#[NoAdminRequired]
	public function stock(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->vintageService->stockByVintage($this->userId));
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->vintageService->get($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function create(int $wineId, int $year, array $data = []): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$vintage = $this->vintageService->create($this->userId, $wineId, $year, $data);
			return new DataResponse($vintage, Http::STATUS_CREATED);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(int $id, array $data = []): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->vintageService->update($id, $this->userId, $data));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			// Read the label references before the row goes, then release the files
			// once nothing points at them — otherwise they linger in the user's
			// storage with no way left to reach them.
			$orphanCandidates = [
				$this->vintageService->getPhotoFileId($id, $this->userId, 'front'),
				$this->vintageService->getPhotoFileId($id, $this->userId, 'back'),
			];
			$this->vintageService->delete($id, $this->userId);
			foreach ($orphanCandidates as $fileId) {
				$this->deleteIfOrphaned($fileId);
			}
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	// --- Label photos (front / back, stored on the vintage) ---

	#[NoAdminRequired]
	public function uploadPhoto(int $id, string $side): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		$file = $this->request->getUploadedFile('photo');
		if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
			return new DataResponse(['error' => 'Keine Datei übermittelt'], Http::STATUS_BAD_REQUEST);
		}
		if (!is_uploaded_file($file['tmp_name'])) {
			return new DataResponse(['error' => 'Ungültiger Upload'], Http::STATUS_BAD_REQUEST);
		}
		if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
			return new DataResponse(['error' => 'Datei zu groß. Maximum: 10 MB.'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$content = file_get_contents($file['tmp_name']);
			if ($content === false) {
				return new DataResponse(['error' => 'Datei konnte nicht gelesen werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			$mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
			// get() first so an unknown or foreign vintage is rejected before a file
			// is written that nothing would ever reference.
			$this->vintageService->get($id, $this->userId);
			$newFileId = $this->photoService->saveVintagePhoto($this->userId, $id, $side, $content, $mimeType);
			$replaced = $this->vintageService->setPhoto($id, $this->userId, $side, $newFileId);
			$this->deleteIfOrphaned($replaced);
			return new DataResponse(['fileId' => $newFileId, 'side' => $side]);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function deletePhoto(int $id, string $side): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$this->deleteIfOrphaned($this->vintageService->clearPhoto($id, $this->userId, $side));
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getPhoto(int $id, string $side): DataResponse|DataDisplayResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$fileId = $this->vintageService->getPhotoFileId($id, $this->userId, $side);
			if ($fileId === null) {
				return new DataResponse(['error' => 'Kein Foto vorhanden'], Http::STATUS_NOT_FOUND);
			}
			$photo = $this->photoService->serveLabelPhotoByFileId($this->userId, $fileId);
			$response = new DataDisplayResponse($photo['content'], Http::STATUS_OK, ['Content-Type' => $photo['mimeType']]);
			$response->cacheFor(3600);
			return $response;
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Remove a file from storage once the last vintage stopped referencing it.
	 * A null id means nothing was replaced, so there is nothing to clean up.
	 */
	private function deleteIfOrphaned(?int $fileId): void {
		if ($fileId === null || $this->userId === null) {
			return;
		}
		if ($this->vintageService->countPhotoReferences($fileId, $this->userId) === 0) {
			$this->photoService->deletePhotoFile($this->userId, $fileId);
		}
	}
}
