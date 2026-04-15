<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Controller;

use OCA\Vinarium\Controller\WineController;
use OCA\Vinarium\Db\Wine;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\WineService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WineControllerTest extends TestCase {
	private WineService&MockObject $service;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->service = $this->createMock(WineService::class);
		$this->request = $this->createMock(IRequest::class);
	}

	private function controller(?string $userId = 'alice'): WineController {
		return new WineController($this->request, $userId, $this->service);
	}

	public function testIndexByProducer(): void {
		$wine = new Wine();
		$wine->setName('R');
		$this->service->method('listByProducer')->with(7, 'alice')->willReturn([$wine]);

		$response = $this->controller()->index(7);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testIndexNotFoundProducer(): void {
		$this->service->method('listByProducer')->willThrowException(new NotFoundException('p'));

		$response = $this->controller()->index(7);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateWithInvalidColor(): void {
		$this->service->method('create')->willThrowException(new ValidationException('bad color'));

		$response = $this->controller()->create(1, 'X', 'purple');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateRejectsEmptyName(): void {
		$this->service->expects($this->never())->method('create');
		$response = $this->controller()->create(1, '   ', Wine::COLOR_RED);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testShow(): void {
		$wine = new Wine();
		$wine->setName('R');
		$this->service->method('get')->willReturn($wine);

		$response = $this->controller()->show(1);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testDestroy(): void {
		$this->service->expects($this->once())->method('delete')->with(1, 'alice');
		$response = $this->controller()->destroy(1);
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}
}
