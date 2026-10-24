<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Cookbook\tests\Unit\Service;

use OCA\Cookbook\Exception\RecipeNotFoundException;
use OCA\Cookbook\Helper\UserFolderHelper;
use OCA\Cookbook\Service\RecipeService;
use OCA\Cookbook\Service\RecipeShareService;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\Cookbook\Service\RecipeShareService
 * @covers \OCA\Cookbook\Exception\RecipeNotFoundException
 */
class RecipeShareServiceTest extends TestCase {
	/** @var IManager|MockObject */
	private $shareManager;
	/** @var RecipeService|MockObject */
	private $recipeService;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var Folder|MockObject */
	private $userFolder;
	/** @var Folder|MockObject */
	private $recipeFolder;
	private RecipeShareService $sut;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = $this->createMock(IManager::class);
		$this->recipeService = $this->createMock(RecipeService::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->recipeFolder = $this->createMock(Folder::class);

		$userFolderHelper = $this->createStub(UserFolderHelper::class);
		$userFolderHelper->method('getFolder')->willReturn($this->userFolder);

		$l = $this->createStub(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$user = $this->createStub(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createStub(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$this->sut = new RecipeShareService(
			$this->shareManager,
			$userFolderHelper,
			$this->recipeService,
			$this->urlGenerator,
			$l,
			$userSession,
		);
	}

	private function setupRecipeFolder(): void {
		$this->userFolder->method('getById')->with(123)->willReturn([$this->recipeFolder]);
		$recipeFile = $this->createStub(File::class);
		$recipeFile->method('getParent')->willReturn($this->recipeFolder);
		$this->recipeService->method('getRecipeFileInFolder')->with($this->recipeFolder)->willReturn($recipeFile);
	}

	private function createShare(?string $password = null, int $permissions = Constants::PERMISSION_READ): IShare {
		$share = $this->createStub(IShare::class);
		$share->method('getShareType')->willReturn(IShare::TYPE_LINK);
		$share->method('getPassword')->willReturn($password);
		$share->method('getPermissions')->willReturn($permissions);
		return $share;
	}

	public function testGetPublicShareIgnoresPasswordProtectedShares(): void {
		$this->setupRecipeFolder();
		$protected = $this->createShare('hash');
		$public = $this->createShare();
		$this->shareManager->method('getSharesBy')
			->with('alice', IShare::TYPE_LINK, $this->recipeFolder, false, -1)
			->willReturn([$protected, $public]);

		$this->assertSame($public, $this->sut->getPublicShare(123));
	}

	public function testGetPublicShareIgnoresSharesWithoutReadPermission(): void {
		$this->setupRecipeFolder();
		$this->shareManager->method('getSharesBy')->willReturn([$this->createShare(null, Constants::PERMISSION_CREATE)]);

		$this->assertNull($this->sut->getPublicShare(123));
	}

	public function testGetPublicShareNone(): void {
		$this->setupRecipeFolder();
		$this->shareManager->method('getSharesBy')->willReturn([$this->createShare('hash')]);

		$this->assertNull($this->sut->getPublicShare(123));
	}

	public function testGetPublicShareUnknownRecipe(): void {
		$this->userFolder->method('getById')->willReturn([]);

		$this->expectException(RecipeNotFoundException::class);
		$this->sut->getPublicShare(123);
	}

	public function testGetPublicShareFolderWithoutRecipe(): void {
		$this->userFolder->method('getById')->willReturn([$this->recipeFolder]);
		$this->recipeService->method('getRecipeFileInFolder')->willReturn(null);

		$this->expectException(RecipeNotFoundException::class);
		$this->sut->getPublicShare(123);
	}

	public function testCreatePublicShareReusesExisting(): void {
		$this->setupRecipeFolder();
		$existing = $this->createShare();
		$this->shareManager->method('getSharesBy')->willReturn([$existing]);
		$this->shareManager->expects($this->never())->method('createShare');

		$this->assertSame($existing, $this->sut->createPublicShare(123));
	}

	public function testCreatePublicShare(): void {
		$this->setupRecipeFolder();
		$this->shareManager->method('getSharesBy')->willReturn([]);

		$newShare = $this->createMock(IShare::class);
		$newShare->expects($this->once())->method('setNode')->with($this->recipeFolder)->willReturnSelf();
		$newShare->expects($this->once())->method('setShareType')->with(IShare::TYPE_LINK)->willReturnSelf();
		$newShare->expects($this->once())->method('setSharedBy')->with('alice')->willReturnSelf();
		$newShare->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_READ)->willReturnSelf();
		$this->shareManager->method('newShare')->willReturn($newShare);

		$created = $this->createShare();
		$this->shareManager->expects($this->once())->method('createShare')->with($newShare)->willReturn($created);

		$this->assertSame($created, $this->sut->createPublicShare(123));
	}

	public function testDeletePublicShare(): void {
		$this->setupRecipeFolder();
		$share = $this->createShare();
		$this->shareManager->method('getSharesBy')->willReturn([$share]);
		$this->shareManager->expects($this->once())->method('deleteShare')->with($share);

		$this->sut->deletePublicShare(123);
	}

	public function testDeletePublicShareNotShared(): void {
		$this->setupRecipeFolder();
		$this->shareManager->method('getSharesBy')->willReturn([]);
		$this->shareManager->expects($this->never())->method('deleteShare');

		$this->sut->deletePublicShare(123);
	}

	public function testGetPublicUrl(): void {
		$share = $this->createStub(IShare::class);
		$share->method('getToken')->willReturn('abc');
		$this->urlGenerator->expects($this->once())->method('linkToRouteAbsolute')
			->with('cookbook.public_recipe.show', ['token' => 'abc'])
			->willReturn('https://example.com/apps/cookbook/s/abc');

		$this->assertEquals('https://example.com/apps/cookbook/s/abc', $this->sut->getPublicUrl($share));
	}

	public function testIsReadableLinkShare(): void {
		$this->assertTrue($this->sut->isReadableLinkShare($this->createShare()));
		$this->assertTrue($this->sut->isReadableLinkShare($this->createShare('hash')));
		$this->assertFalse($this->sut->isReadableLinkShare($this->createShare(null, Constants::PERMISSION_CREATE)));

		$userShare = $this->createStub(IShare::class);
		$userShare->method('getShareType')->willReturn(IShare::TYPE_USER);
		$userShare->method('getPermissions')->willReturn(Constants::PERMISSION_READ);
		$this->assertFalse($this->sut->isReadableLinkShare($userShare));
	}
}
