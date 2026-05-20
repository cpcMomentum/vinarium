<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Controller;

use OCA\Vinarium\Controller\BottleController;
use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Exception\SlotOccupiedException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\PhotoService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BottleControllerTest extends TestCase {
	private BottleService&MockObject $service;
	private IRequest&MockObject $request;
	private PhotoService&MockObject $photoService;

	protected function setUp(): void {
		$this->service = $this->createMock(BottleService::class);
		$this->request = $this->createMock(IRequest::class);
		$this->photoService = $this->createMock(PhotoService::class);
	}

	private function controller(?string $userId = 'alice'): BottleController {
		return new BottleController($this->request, $userId, $this->service, $this->photoService);
	}

	public function testIndexWithoutFilter(): void {
		$this->service->expects($this->once())->method('getFilteredBottles')
			->with('alice', [])
			->willReturn([]);
		$response = $this->controller()->index();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testIndexWithColorFilter(): void {
		$this->service->expects($this->once())->method('getFilteredBottles')
			->with('alice', ['color' => 'red'])
			->willReturn([]);
		$this->controller()->index(color: 'red');
	}

	public function testParked(): void {
		$this->service->expects($this->once())->method('getParkedBottles')->with('alice')->willReturn([]);
		$response = $this->controller()->parked();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testMoveSuccess(): void {
		$bottle = new Bottle();
		$this->service->method('moveBottle')->willReturn($bottle);
		$response = $this->controller()->move(1, 5);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testMoveOccupiedReturns409(): void {
		$this->service->method('moveBottle')->willThrowException(new SlotOccupiedException('busy'));
		$response = $this->controller()->move(1, 5);
		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
	}

	public function testMoveForeignSlotReturns403(): void {
		$this->service->method('moveBottle')->willThrowException(new PermissionDeniedException('nope'));
		$response = $this->controller()->move(1, 99);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testShowNotFound(): void {
		$this->service->method('get')->willThrowException(new NotFoundException('no'));
		$response = $this->controller()->show(99);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnauthenticatedDestroy(): void {
		$response = $this->controller(null)->destroy(1);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
