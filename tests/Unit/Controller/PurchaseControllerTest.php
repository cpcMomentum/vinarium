<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Controller;

use OCA\Vinarium\Controller\PurchaseController;
use OCA\Vinarium\Db\Bottle;
use OCA\Vinarium\Db\Purchase;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\ValidationException;
use OCA\Vinarium\Service\BottleService;
use OCA\Vinarium\Service\PurchaseService;
use OCA\Vinarium\Service\PurchaseWizardService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PurchaseControllerTest extends TestCase {
	private PurchaseService&MockObject $purchaseService;
	private BottleService&MockObject $bottleService;
	private PurchaseWizardService&MockObject $purchaseWizardService;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->purchaseService = $this->createMock(PurchaseService::class);
		$this->bottleService = $this->createMock(BottleService::class);
		$this->purchaseWizardService = $this->createMock(PurchaseWizardService::class);
		$this->request = $this->createMock(IRequest::class);
	}

	private function controller(?string $userId = 'alice'): PurchaseController {
		return new PurchaseController($this->request, $userId, $this->purchaseService, $this->bottleService, $this->purchaseWizardService);
	}

	public function testCreateAlsoCreatesBottles(): void {
		$purchase = new Purchase();
		$purchase->setId(42);
		$purchase->setQuantity(6);
		$bottle = new Bottle();
		$this->purchaseService->expects($this->once())->method('create')
			->with('alice', 7, ['quantity' => 6, 'bottleSizeMl' => 750])
			->willReturn($purchase);
		$this->bottleService->expects($this->once())->method('createBottlesForPurchase')
			->with(42, 'alice')
			->willReturn([$bottle, $bottle, $bottle, $bottle, $bottle, $bottle]);

		$response = $this->controller()->create(7, ['quantity' => 6, 'bottleSizeMl' => 750]);
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(6, $response->getData()['bottles']);
	}

	public function testCreateValidationError(): void {
		$this->purchaseService->method('create')->willThrowException(new ValidationException('bad'));
		$response = $this->controller()->create(7, ['quantity' => 0]);
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testIndexNotFoundParent(): void {
		$this->purchaseService->method('listByVintage')->willThrowException(new NotFoundException('no'));
		$response = $this->controller()->index(99);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testUnauthenticatedShow(): void {
		$response = $this->controller(null)->show(1);
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testDestroy(): void {
		$this->purchaseService->expects($this->once())->method('delete')->with(5, 'alice');
		$response = $this->controller()->destroy(5);
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}
}
