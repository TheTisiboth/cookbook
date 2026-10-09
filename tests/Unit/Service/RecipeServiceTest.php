<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\Cookbook\tests\Unit\Service;

use OCA\Cookbook\Db\RecipeDb;
use OCA\Cookbook\Helper\DownloadHelper;
use OCA\Cookbook\Helper\FileSystem\RecipeNameHelper;
use OCA\Cookbook\Helper\Filter\JSON\JSONFilter;
use OCA\Cookbook\Helper\ImageService\ImageSize;
use OCA\Cookbook\Helper\UserConfigHelper;
use OCA\Cookbook\Helper\UserFolderHelper;
use OCA\Cookbook\Service\HtmlDownloadService;
use OCA\Cookbook\Service\ImageService;
use OCA\Cookbook\Service\RecipeExtractionService;
use OCA\Cookbook\Service\RecipeService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\Cookbook\Service\RecipeService
 */
class RecipeServiceTest extends TestCase {
	/** @var ImageService|MockObject */
	private $imageService;
	private RecipeService $dut;

	protected function setUp(): void {
		parent::setUp();

		$this->imageService = $this->createMock(ImageService::class);

		$l = $this->createStub(IL10N::class);
		$l->method('t')->willReturnArgument(0);

		$this->dut = new RecipeService(
			'alice',
			$this->createStub(IRootFolder::class),
			$this->createStub(RecipeDb::class),
			$this->createStub(UserConfigHelper::class),
			$this->createStub(UserFolderHelper::class),
			$this->imageService,
			$this->createStub(RecipeNameHelper::class),
			$l,
			$this->createStub(LoggerInterface::class),
			$this->createStub(HtmlDownloadService::class),
			$this->createStub(RecipeExtractionService::class),
			$this->createStub(JSONFilter::class),
			$this->createStub(DownloadHelper::class),
		);
	}

	private function createFile(string $name): File {
		$file = $this->createStub(File::class);
		$file->method('getType')->willReturn('file');
		$file->method('getName')->willReturn($name);
		return $file;
	}

	public function testGetRecipeFileInFolder(): void {
		$recipe = $this->createFile('recipe.json');
		$folder = $this->createStub(Folder::class);
		$folder->method('getDirectoryListing')->willReturn([$this->createFile('full.jpg'), $recipe]);

		$this->assertSame($recipe, $this->dut->getRecipeFileInFolder($folder));
	}

	public function testGetRecipeFileInFolderWithoutRecipe(): void {
		$folder = $this->createStub(Folder::class);
		$folder->method('getDirectoryListing')->willReturn([$this->createFile('full.jpg')]);

		$this->assertNull($this->dut->getRecipeFileInFolder($folder));
	}

	public function testGetRecipeFileInFolderOfNonFolder(): void {
		$this->assertNull($this->dut->getRecipeFileInFolder($this->createFile('recipe.json')));
		$this->assertNull($this->dut->getRecipeFileInFolder(null));
	}

	public function testGetRecipeImageFileInFolder(): void {
		$folder = $this->createStub(Folder::class);
		$full = $this->createStub(File::class);
		$thumb = $this->createStub(File::class);
		$thumb16 = $this->createStub(File::class);
		$this->imageService->method('getImageAsFile')->with($folder)->willReturn($full);
		$this->imageService->method('getThumbnailAsFile')->willReturnMap([
			[$folder, ImageSize::THUMBNAIL, $thumb],
			[$folder, ImageSize::MINI_THUMBNAIL, $thumb16],
		]);

		$this->assertSame($full, $this->dut->getRecipeImageFileInFolder($folder, 'full'));
		$this->assertSame($thumb, $this->dut->getRecipeImageFileInFolder($folder, 'thumb'));
		$this->assertSame($thumb16, $this->dut->getRecipeImageFileInFolder($folder, 'thumb16'));
	}

	public function testGetRecipeImageFileInFolderInvalidSize(): void {
		$this->expectException(\Exception::class);
		$this->dut->getRecipeImageFileInFolder($this->createStub(Folder::class), 'huge');
	}
}
