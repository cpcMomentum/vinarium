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
use OCA\Vinarium\Service\TastingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TastingController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly TastingService $tastingService,
		private readonly PhotoService $photoService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function details(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->tastingService->getDetails($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function all(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->tastingService->listAll($this->userId));
	}

	#[NoAdminRequired]
	public function stats(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		return new DataResponse($this->tastingService->getStats($this->userId));
	}

	#[NoAdminRequired]
	public function byBottle(int $bottleId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			return new DataResponse($this->tastingService->listByBottle($bottleId, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function create(int $bottleId, array $data = []): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$tasting = $this->tastingService->create($this->userId, $bottleId, $data);
			return new DataResponse($tasting, Http::STATUS_CREATED);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** Consume bottle + create tasting in one call */
	#[NoAdminRequired]
	public function consume(int $bottleId, array $data = []): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$result = $this->tastingService->consumeWithTasting($this->userId, $bottleId, $data);
			return new DataResponse($result, Http::STATUS_CREATED);
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
			$tasting = $this->tastingService->update($id, $this->userId, $data);
			return new DataResponse($tasting);
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
			$this->tastingService->delete($id, $this->userId);
			$this->photoService->deleteTastingFolder($this->userId, $id);
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function uploadPhoto(int $id): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		$file = $this->request->getUploadedFile('photo');
		if (empty($file) || !isset($file['tmp_name'])) {
			return new DataResponse(['error' => 'Keine Datei hochgeladen'], Http::STATUS_BAD_REQUEST);
		}
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return new DataResponse(['error' => 'Upload-Fehler'], Http::STATUS_BAD_REQUEST);
		}
		if (!is_uploaded_file($file['tmp_name'])) {
			return new DataResponse(['error' => 'Ungültiger Upload'], Http::STATUS_BAD_REQUEST);
		}
		try {
			$tasting = $this->tastingService->get($id, $this->userId);
			$mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
			$content = file_get_contents($file['tmp_name']);
			if ($content === false) {
				return new DataResponse(['error' => 'Datei konnte nicht gelesen werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			$fileId = $this->photoService->saveTastingPhoto($this->userId, $id, $content, $mimeType);
			$existing = $tasting->getPhotoFileIds() ?? [];
			$existing[] = $fileId;
			$tasting->setPhotoFileIds($existing);
			$this->tastingService->save($tasting);
			return new DataResponse(['photo_file_ids' => $existing], Http::STATUS_CREATED);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function deletePhoto(int $id, int $fileId): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
		}
		try {
			$tasting = $this->tastingService->get($id, $this->userId);
			$this->photoService->deleteTastingPhoto($this->userId, $id, $fileId);
			$existing = array_values(array_filter(
				$tasting->getPhotoFileIds() ?? [],
				fn($fid) => (int)$fid !== $fileId
			));
			$tasting->setPhotoFileIds($existing ?: null);
			$this->tastingService->save($tasting);
			return new DataResponse(['photo_file_ids' => $existing]);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
