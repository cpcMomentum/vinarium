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
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\PhotoService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BottleController extends Controller {

	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly BottleService $bottleService,
		private readonly PhotoService $photoService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function index(?string $status = null, ?string $color = null, ?int $year = null, ?int $producerId = null, ?int $drinkUntilYearBefore = null): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$filter = array_filter([
			'status' => $status,
			'color' => $color,
			'year' => $year,
			'producerId' => $producerId,
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
	public function details(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->bottleService->getDetails($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function uploadPhoto(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
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
			$bottle = $this->bottleService->get($id, $this->userId);
			$content = file_get_contents($file['tmp_name']);
			if ($content === false) {
				return new DataResponse(['error' => 'Datei konnte nicht gelesen werden'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
			$mimeType = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
			$fileId = $this->photoService->saveBottlePhoto($this->userId, $id, $content, $mimeType);
			$bottle->setPhotoFileId($fileId);
			$this->bottleService->update($bottle);
			return new DataResponse(['photo_file_id' => $fileId]);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function deletePhoto(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$bottle = $this->bottleService->get($id, $this->userId);
			$this->photoService->deleteBottlePhoto($this->userId, $id);
			$bottle->setPhotoFileId(null);
			$this->bottleService->update($bottle);
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function getPhoto(int $id): DataResponse|DataDisplayResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$this->bottleService->get($id, $this->userId);
			$photo = $this->photoService->serveBottlePhoto($this->userId, $id);
			$response = new DataDisplayResponse($photo['content'], Http::STATUS_OK, ['Content-Type' => $photo['mimeType']]);
			$response->cacheFor(3600);
			return $response;
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
	public function swap(int $id, int $targetBottleId): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$result = $this->bottleService->swapBottles($id, $targetBottleId, $this->userId);
			return new DataResponse($result);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}
	}

	#[NoAdminRequired]
	public function restore(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			return new DataResponse($this->bottleService->restoreBottle($id, $this->userId));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	#[NoAdminRequired]
	public function gift(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$recipient = trim((string)($this->request->getParam('recipient') ?? ''));
		if ($recipient === '') {
			return new DataResponse(['error' => 'Recipient is required'], Http::STATUS_BAD_REQUEST);
		}
		$date = $this->request->getParam('date');
		$occasion = $this->request->getParam('occasion');
		try {
			return new DataResponse($this->bottleService->giftBottle(
				$id, $this->userId, $recipient,
				is_string($date) ? $date : null,
				is_string($occasion) ? $occasion : null,
			));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function lose(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		$date = $this->request->getParam('date');
		$reason = $this->request->getParam('reason');
		try {
			return new DataResponse($this->bottleService->loseBottle(
				$id, $this->userId,
				is_string($date) ? $date : null,
				is_string($reason) ? $reason : null,
			));
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (PermissionDeniedException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (ValidationException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function giftRecipients(): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		return new DataResponse($this->bottleService->getGiftRecipients($this->userId));
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		if ($this->userId === null) {
			return $this->unauthorized();
		}
		try {
			$this->bottleService->delete($id, $this->userId);
			$this->photoService->deleteBottlePhoto($this->userId, $id);
			return new DataResponse(null, Http::STATUS_NO_CONTENT);
		} catch (NotFoundException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	private function unauthorized(): DataResponse {
		return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
	}
}
