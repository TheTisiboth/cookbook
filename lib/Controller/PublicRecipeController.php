<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

namespace OCA\Cookbook\Controller;

use OCA\Cookbook\Helper\Filter\Output\RecipeJSONOutputFilter;
use OCA\Cookbook\Service\RecipeService;
use OCA\Cookbook\Service\RecipeShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\PublicShareController;
use OCP\Files\File;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager;
use OCP\Share\IShare;
use OCP\Util;

class PublicRecipeController extends PublicShareController {
	private ?IShare $share = null;
	private ?File $recipeFile = null;

	public function __construct(
		string $AppName,
		IRequest $request,
		ISession $session,
		private IManager $shareManager,
		private RecipeService $recipeService,
		private RecipeShareService $shareService,
		private RecipeJSONOutputFilter $outputFilter,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($AppName, $request, $session);
	}

	#[\Override]
	public function isValidToken(): bool {
		try {
			$share = $this->shareManager->getShareByToken($this->getToken());
		} catch (ShareNotFound $e) {
			return false;
		}

		if (!$this->shareService->isReadableLinkShare($share)) {
			return false;
		}

		$file = $this->recipeService->getRecipeFileInFolder($share->getNode());
		if ($file === null) {
			return false;
		}

		$this->share = $share;
		$this->recipeFile = $file;
		return true;
	}

	#[\Override]
	protected function isPasswordProtected(): bool {
		return $this->share?->getPassword() !== null;
	}

	#[\Override]
	protected function getPasswordHash(): ?string {
		return $this->share?->getPassword();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function show(): PublicTemplateResponse {
		$recipe = $this->parseRecipe() ?? [];
		$name = (string)($recipe['name'] ?? '');

		Util::addScript($this->appName, 'cookbook-public');
		Util::addStyle($this->appName, 'cookbook-public');

		$response = new PublicTemplateResponse($this->appName, 'public_recipe', [
			'token' => $this->getToken(),
		]);
		$response->setHeaderTitle($name);
		$response->setFooterVisible(false);

		return $response;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function recipe(): JSONResponse {
		$json = $this->parseRecipe();

		if ($json === null) {
			return new JSONResponse(null, Http::STATUS_NOT_FOUND);
		}

		unset($json['id']);
		$json['printImage'] = true;
		$json['imageUrl'] = $this->urlGenerator->linkToRoute('cookbook.public_recipe.image', ['token' => $this->getToken(), 'size' => 'full']);

		return new JSONResponse($this->outputFilter->filter($json), Http::STATUS_OK);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function image(string $size = 'full'): Response {
		try {
			$file = $this->recipeService->getRecipeImageFileInFolder($this->recipeFile->getParent(), $size);
		} catch (\Exception $e) {
			return new JSONResponse(null, Http::STATUS_NOT_FOUND);
		}

		return new FileDisplayResponse($file, Http::STATUS_OK, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
	}

	private function parseRecipe(): ?array {
		try {
			return $this->recipeService->parseRecipeFile($this->recipeFile);
		} catch (\Exception $e) {
			return null;
		}
	}
}
