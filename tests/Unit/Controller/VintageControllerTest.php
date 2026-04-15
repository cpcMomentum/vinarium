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
use OCA\Vinarium\Service\VintageService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VintageControllerTest extends TestCase {
	private VintageService&MockObject $service;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->service = $this->createMock(VintageService::class);
		$this->request = $this->createMock(IRequest::class);
	}

	private function controller(?string $userId = 'alice'): VintageController {
		return new VintageController($this->request, $userId, $this->service);
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
}
