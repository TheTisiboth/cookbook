<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

namespace OCA\Cookbook\Controller;

use OCA\Cookbook\Exception\RecipeNotFoundException;
use OCA\Cookbook\Service\RecipeShareService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\Share\IShare;

class ShareController extends Controller {
	public function __construct(
		$AppName,
		IRequest $request,
		private RecipeShareService $shareService,
	) {
		parent::__construct($AppName, $request);
	}

	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		return $this->respond(fn () => $this->serialize($this->shareService->getPublicShare($id)));
	}

	#[NoAdminRequired]
	public function create(int $id): JSONResponse {
		return $this->respond(fn () => $this->serialize($this->shareService->createPublicShare($id)));
	}

	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		return $this->respond(fn () => $this->shareService->deletePublicShare($id));
	}

	private function respond(callable $action): JSONResponse {
		try {
			return new JSONResponse($action());
		} catch (RecipeNotFoundException $e) {
			return new JSONResponse(['msg' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (\Exception $e) {
			return new JSONResponse(['msg' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
	}

	private function serialize(?IShare $share): ?array {
		if ($share === null) {
			return null;
		}

		return [
			'token' => $share->getToken(),
			'url' => $this->shareService->getPublicUrl($share),
		];
	}
}
