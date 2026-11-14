<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Cookbook\tests\Unit\Controller;

use OCA\Cookbook\Controller\PublicRecipeController;
use OCA\Cookbook\Helper\Filter\Output\RecipeJSONOutputFilter;
use OCA\Cookbook\Helper\UserFolderHelper;
use OCA\Cookbook\Service\RecipeService;
use OCA\Cookbook\Service\RecipeShareService;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * @covers \OCA\Cookbook\Controller\PublicRecipeController
 */
class PublicRecipeControllerTest extends TestCase {
	/** @var IManager|MockObject */
	private $shareManager;
	/** @var RecipeService|MockObject */
	private $recipeService;
	/** @var RecipeJSONOutputFilter|MockObject */
	private $outputFilter;
	/** @var IURLGenerator|MockObject */
	private $urlGenerator;
	/** @var Folder|MockObject */
	private $recipeFolder;
	/** @var File|MockObject */
	private $recipeFile;
	private PublicRecipeController $sut;

	protected function setUp(): void {
		parent::setUp();

		$this->shareManager = $this->createMock(IManager::class);
		$this->recipeService = $this->createMock(RecipeService::class);
		$this->outputFilter = $this->createMock(RecipeJSONOutputFilter::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->recipeFolder = $this->createStub(Folder::class);
		$this->recipeFile = $this->createStub(File::class);
		$this->recipeFile->method('getParent')->willReturn($this->recipeFolder);

		$this->sut = new PublicRecipeController(
			'cookbook',
			$this->createStub(IRequest::class),
			$this->createStub(ISession::class),
			$this->shareManager,
			$this->recipeService,
			new RecipeShareService(
				$this->shareManager,
				$this->createStub(UserFolderHelper::class),
				$this->recipeService,
				$this->urlGenerator,
				$this->createStub(IL10N::class),
				$this->createStub(IUserSession::class),
			),
			$this->outputFilter,
			$this->urlGenerator,
		);
		$this->sut->setToken('abc');
	}

	private function setupShare(int $type = IShare::TYPE_LINK, int $permissions = Constants::PERMISSION_READ, ?string $password = null): void {
		$share = $this->createStub(IShare::class);
		$share->method('getShareType')->willReturn($type);
		$share->method('getPermissions')->willReturn($permissions);
		$share->method('getPassword')->willReturn($password);
		$share->method('getNode')->willReturn($this->recipeFolder);
		$this->shareManager->method('getShareByToken')->with('abc')->willReturn($share);
	}

	private function isPasswordProtected(): bool {
		$method = new ReflectionMethod($this->sut, 'isPasswordProtected');
		return $method->invoke($this->sut);
	}

	public function testValidToken(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->with($this->recipeFolder)->willReturn($this->recipeFile);

		$this->assertTrue($this->sut->isValidToken());
		$this->assertFalse($this->isPasswordProtected());
	}

	public function testPasswordProtectedToken(): void {
		$this->setupShare(IShare::TYPE_LINK, Constants::PERMISSION_READ, 'hash');
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);

		$this->assertTrue($this->sut->isValidToken());
		$this->assertTrue($this->isPasswordProtected());
	}

	public function testUnknownToken(): void {
		$this->shareManager->method('getShareByToken')->willThrowException(new ShareNotFound());

		$this->assertFalse($this->sut->isValidToken());
	}

	public function testNonLinkShare(): void {
		$this->setupShare(IShare::TYPE_USER);
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);

		$this->assertFalse($this->sut->isValidToken());
	}

	public function testShareWithoutReadPermission(): void {
		$this->setupShare(IShare::TYPE_LINK, Constants::PERMISSION_CREATE);
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);

		$this->assertFalse($this->sut->isValidToken());
	}

	public function testShareOfNonRecipeFolder(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn(null);

		$this->assertFalse($this->sut->isValidToken());
	}

	public function testShow(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$this->recipeService->method('parseRecipeFile')->with($this->recipeFile)->willReturn(['name' => 'Pancakes', 'description' => 'Fluffy']);
		$this->sut->isValidToken();

		$ret = $this->sut->show();

		$this->assertInstanceOf(PublicTemplateResponse::class, $ret);
		$this->assertEquals('public_recipe', $ret->getTemplateName());
		$this->assertEquals(['token' => 'abc'], $ret->getParams());
		$this->assertEquals('Pancakes', $ret->getHeaderTitle());
	}

	public function testRecipe(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$this->recipeService->method('parseRecipeFile')->willReturn(['id' => 42, 'name' => 'Pancakes']);
		$this->urlGenerator->method('linkToRoute')
			->with('cookbook.public_recipe.image', ['token' => 'abc', 'size' => 'full'])
			->willReturn('/apps/cookbook/s/abc/image?size=full');
		$this->outputFilter->method('filter')->willReturnArgument(0);
		$this->sut->isValidToken();

		$ret = $this->sut->recipe();

		$this->assertEquals(200, $ret->getStatus());
		$this->assertEquals([
			'name' => 'Pancakes',
			'printImage' => true,
			'imageUrl' => '/apps/cookbook/s/abc/image?size=full',
		], $ret->getData());
	}

	public function testRecipeInvalidJson(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$this->recipeService->method('parseRecipeFile')->willReturn(null);
		$this->sut->isValidToken();

		$this->assertEquals(404, $this->sut->recipe()->getStatus());
	}

	public function testRecipeWithoutName(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$this->recipeService->method('parseRecipeFile')->willThrowException(new \Exception('Field "name" is required'));
		$this->sut->isValidToken();

		$this->assertEquals(404, $this->sut->recipe()->getStatus());
		$this->assertEquals('', $this->sut->show()->getHeaderTitle());
	}

	public function testImage(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$image = $this->createStub(File::class);
		$this->recipeService->method('getRecipeImageFileInFolder')->with($this->recipeFolder, 'thumb')->willReturn($image);
		$this->sut->isValidToken();

		$ret = $this->sut->image('thumb');

		$this->assertInstanceOf(FileDisplayResponse::class, $ret);
	}

	public function testImageMissing(): void {
		$this->setupShare();
		$this->recipeService->method('getRecipeFileInFolder')->willReturn($this->recipeFile);
		$this->recipeService->method('getRecipeImageFileInFolder')->willThrowException(new \Exception());
		$this->sut->isValidToken();

		$this->assertEquals(404, $this->sut->image()->getStatus());
	}
}
