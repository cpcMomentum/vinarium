<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Controller;

use OCA\Vinarium\Controller\VintageController;
use OCA\Vinarium\Db\Vintage;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\PhotoService;
use OCA\Vinarium\Service\VintageService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VintageControllerTest extends TestCase {
	private VintageService&MockObject $service;
	private IRequest&MockObject $request;
	private PhotoService&MockObject $photoService;

	protected function setUp(): void {
		$this->service = $this->createMock(VintageService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->photoService = $this->createMock(PhotoService::class);
	}

	private function controller(?string $userId = 'alice'): VintageController {
		return new VintageController($this->request, $userId, $this->service, $this->photoService);
	}

	public function testIndexByWine(): void {
		$vintage = new Vintage();
		$vintage->setYear(2020);
		$this->service->method('listByWine')->with(3, 'alice')->willReturn([$vintage]);

		$response = $this->controller()->index(3);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testCreateValidationErrorReturns400(): void {
		$this->service->method('create')->willThrowException(new ValidationException('bad year'));
		$response = $this->controller()->create(3, 1800);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateParentNotFoundReturns404(): void {
		$this->service->method('create')->willThrowException(new NotFoundException('wine gone'));
		$response = $this->controller()->create(3, 2020);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUpdate(): void {
		$vintage = new Vintage();
		$vintage->setYear(2021);
		$this->service->method('update')->willReturn($vintage);

		$response = $this->controller()->update(5, ['year' => 2021]);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroyNotFound(): void {
		$this->service->method('delete')->willThrowException(new NotFoundException('gone'));
		$response = $this->controller()->destroy(5);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnauthenticatedShow(): void {
		$response = $this->controller(null)->show(1);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	// --- Label photos (#190) ---

	public function testUploadPhotoRequiresAuthentication(): void {
		$response = $this->controller(null)->uploadPhoto(1, 'front');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testUploadPhotoWithoutAFileIsRejected(): void {
		$this->request->method('getUploadedFile')->willReturn([]);

		$response = $this->controller()->uploadPhoto(1, 'front');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUploadPhotoRejectsSomethingThatWasNotUploaded(): void {
		// is_uploaded_file() ist im CLI-Kontext immer false, der Test belegt also
		// nur, dass die Pruefung stattfindet — nicht, dass sie genau diesen Pfad
		// abweist. Ihr Zweck im Betrieb ist es, ein tmp_name auf einen beliebigen
		// Serverpfad zurueckzuweisen.
		$this->request->method('getUploadedFile')->willReturn([
			'tmp_name' => '/etc/passwd', 'error' => UPLOAD_ERR_OK, 'size' => 10,
		]);

		$response = $this->controller()->uploadPhoto(1, 'front');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testDeletePhotoRequiresAuthentication(): void {
		$response = $this->controller(null)->deletePhoto(1, 'front');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDeletePhotoReleasesTheFileOnceNothingReferencesIt(): void {
		$this->service->method('clearPhoto')->willReturn(500);
		$this->service->method('countPhotoReferences')->with(500, 'alice')->willReturn(0);
		$this->photoService->expects($this->once())->method('deletePhotoFile')->with('alice', 500);

		$response = $this->controller()->deletePhoto(1, 'front');
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function testDeletePhotoKeepsTheFileWhileItIsStillReferenced(): void {
		// Same file on both sides: clearing one must not remove it from storage.
		$this->service->method('clearPhoto')->willReturn(500);
		$this->service->method('countPhotoReferences')->with(500, 'alice')->willReturn(1);
		$this->photoService->expects($this->never())->method('deletePhotoFile');

		$this->controller()->deletePhoto(1, 'front');
	}

	public function testDeletePhotoOnAnEmptySideTouchesNoFile(): void {
		$this->service->method('clearPhoto')->willReturn(null);
		$this->photoService->expects($this->never())->method('deletePhotoFile');

		$response = $this->controller()->deletePhoto(1, 'front');
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}

	public function testDeletePhotoOnAForeignVintageIsNotFound(): void {
		$this->service->method('clearPhoto')->willThrowException(new NotFoundException('nope'));

		$response = $this->controller()->deletePhoto(1, 'front');
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testDeletePhotoRejectsAnUnknownSide(): void {
		$this->service->method('clearPhoto')->willThrowException(new \InvalidArgumentException('bad side'));

		$response = $this->controller()->deletePhoto(1, 'links');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	/*
	 * Der Erfolgsfall von getPhoto() ist container-frei nicht pruefbar:
	 * Response::cacheFor() und getHeaders() loesen ITimeFactory und IRequest ueber
	 * OCP\Server auf, was eine laufende Nextcloud voraussetzt. Ein globaler
	 * OC-Shim waere der falsche Weg — er wuerde die bewusste Skip-Abfrage in
	 * AppFrameworkTest aushebeln und dort Tests gegen einen Fake-Container laufen
	 * lassen. Abgedeckt wird der Pfad stattdessen im Browser-Test (#216).
	 */

	public function testGetPhotoWithoutAStoredPhotoIsNotFound(): void {
		$this->service->method('getPhotoFileId')->willReturn(null);
		$this->photoService->expects($this->never())->method('serveLabelPhotoByFileId');

		$response = $this->controller()->getPhoto(1, 'back');
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testGetPhotoRequiresAuthentication(): void {
		$response = $this->controller(null)->getPhoto(1, 'front');
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDeletingAVintageReleasesBothLabelFiles(): void {
		$this->service->method('getPhotoFileId')->willReturnMap([
			[1, 'alice', 'front', 500],
			[1, 'alice', 'back', 600],
		]);
		$this->service->method('countPhotoReferences')->willReturn(0);
		$released = [];
		$this->photoService->method('deletePhotoFile')
			->willReturnCallback(function (string $u, int $id) use (&$released): bool {
				$released[] = $id;
				return true;
			});

		$response = $this->controller()->destroy(1);
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
		$this->assertSame([500, 600], $released);
	}
}
