<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Cookbook\tests\Unit\Controller;

use OCA\Cookbook\Controller\ShareController;
use OCA\Cookbook\Exception\RecipeNotFoundException;
use OCA\Cookbook\Service\RecipeShareService;
use OCP\IRequest;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Cookbook\Controller\ShareController
 * @covers \OCA\Cookbook\Exception\RecipeNotFoundException
 */
class ShareControllerTest extends TestCase {
	/** @var RecipeShareService|MockObject */
	private $shareService;
	private ShareController $sut;

	protected function setUp(): void {
		parent::setUp();

		$this->shareService = $this->createMock(RecipeShareService::class);
		$this->sut = new ShareController('cookbook', $this->createStub(IRequest::class), $this->shareService);
	}

	private function createShare(): IShare {
		$share = $this->createStub(IShare::class);
		$share->method('getToken')->willReturn('abc');
		$this->shareService->method('getPublicUrl')->with($share)->willReturn('https://example.com/apps/cookbook/s/abc');
		return $share;
	}

	public function testShow(): void {
		$share = $this->createShare();
		$this->shareService->expects($this->once())->method('getPublicShare')->with(123)->willReturn($share);

		$ret = $this->sut->show(123);

		$this->assertEquals(200, $ret->getStatus());
		$this->assertEquals(['token' => 'abc', 'url' => 'https://example.com/apps/cookbook/s/abc'], $ret->getData());
	}

	public function testShowNotShared(): void {
		$this->shareService->method('getPublicShare')->willReturn(null);

		$ret = $this->sut->show(123);

		$this->assertEquals(200, $ret->getStatus());
		$this->assertNull($ret->getData());
	}

	public function testShowNotFound(): void {
		$this->shareService->method('getPublicShare')->willThrowException(new RecipeNotFoundException('not found'));

		$ret = $this->sut->show(123);

		$this->assertEquals(404, $ret->getStatus());
		$this->assertEquals(['msg' => 'not found'], $ret->getData());
	}

	public function testCreate(): void {
		$share = $this->createShare();
		$this->shareService->expects($this->once())->method('createPublicShare')->with(123)->willReturn($share);

		$ret = $this->sut->create(123);

		$this->assertEquals(200, $ret->getStatus());
		$this->assertEquals('abc', $ret->getData()['token']);
	}

	public function testCreateRejectedByPolicy(): void {
		$this->shareService->method('createPublicShare')->willThrowException(new \Exception('Passwords are enforced for link and mail shares'));

		$ret = $this->sut->create(123);

		$this->assertEquals(422, $ret->getStatus());
		$this->assertEquals(['msg' => 'Passwords are enforced for link and mail shares'], $ret->getData());
	}

	public function testDestroy(): void {
		$this->shareService->expects($this->once())->method('deletePublicShare')->with(123);

		$ret = $this->sut->destroy(123);

		$this->assertEquals(200, $ret->getStatus());
		$this->assertNull($ret->getData());
	}

	public function testDestroyNotFound(): void {
		$this->shareService->method('deletePublicShare')->willThrowException(new RecipeNotFoundException('not found'));

		$this->assertEquals(404, $this->sut->destroy(123)->getStatus());
	}
}
