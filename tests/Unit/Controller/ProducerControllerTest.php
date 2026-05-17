<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 cpcMomentum
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Vinarium\Tests\Unit\Controller;

use OCA\Vinarium\Controller\ProducerController;
use OCA\Vinarium\Db\Producer;
use OCA\Vinarium\Exception\NotFoundException;
use OCA\Vinarium\Exception\PermissionDeniedException;
use OCA\Vinarium\Service\ProducerService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProducerControllerTest extends TestCase {
	private ProducerService&MockObject $service;
	private IRequest&MockObject $request;

	protected function setUp(): void {
		$this->service = $this->createMock(ProducerService::class);
		$this->request = $this->createMock(IRequest::class);
	}

	private function controller(?string $userId = 'alice'): ProducerController {
		return new ProducerController($this->request, $userId, $this->service);
	}

	public function testIndexReturnsList(): void {
		$producer = new Producer();
		$producer->setName('W1');
		$this->service->expects($this->once())->method('list')->with('alice')->willReturn([$producer]);

		$response = $this->controller()->index();
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData());
	}

	public function testIndexWithoutUserReturns401(): void {
		$response = $this->controller(null)->index();
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testShowReturnsProducer(): void {
		$producer = new Producer();
		$producer->setName('W1');
		$this->service->method('get')->with(5, 'alice')->willReturn($producer);

		$response = $this->controller()->show(5);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('W1', $response->getData()->getName());
	}

	public function testShowNotFoundReturns404(): void {
		$this->service->method('get')->willThrowException(new NotFoundException('nope'));

		$response = $this->controller()->show(999);
		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
	}

	public function testCreateReturns201(): void {
		$producer = new Producer();
		$producer->setName('Neu');
		$this->service->expects($this->once())->method('create')
			->with('alice', 'Neu', null, null, null, null)
			->willReturn($producer);

		$response = $this->controller()->create('Neu');
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testCreateRejectsEmptyName(): void {
		$this->service->expects($this->never())->method('create');

		$response = $this->controller()->create('');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testUpdatePermissionDeniedReturns403(): void {
		$this->service->method('update')->willThrowException(new PermissionDeniedException('no'));

		$response = $this->controller()->update(1, ['name' => 'X']);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testDestroyReturns204(): void {
		$this->service->expects($this->once())->method('delete')->with(1, 'alice');

		$response = $this->controller()->destroy(1);
		$this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
	}
}
