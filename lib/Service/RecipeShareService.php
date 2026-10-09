<?php

// SPDX-FileCopyrightText: 2026 Nextcloud cookbook contributors
//
// SPDX-License-Identifier: AGPL-3.0-only OR AGPL-3.0-or-later

namespace OCA\Cookbook\Service;

use OCA\Cookbook\Exception\RecipeNotFoundException;
use OCA\Cookbook\Exception\UserNotLoggedInException;
use OCA\Cookbook\Helper\UserFolderHelper;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\IManager;
use OCP\Share\IShare;

class RecipeShareService {
	public function __construct(
		private IManager $shareManager,
		private UserFolderHelper $userFolder,
		private RecipeService $recipeService,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private IUserSession $userSession,
	) {
	}

	/**
	 * @throws RecipeNotFoundException
	 * @throws UserNotLoggedInException
	 */
	public function getPublicShare(int $recipeId): ?IShare {
		return $this->findPublicShare($this->getRecipeFolder($recipeId));
	}

	/**
	 * @throws RecipeNotFoundException
	 * @throws UserNotLoggedInException
	 * @throws \Exception if the share could not be created (e.g. admin enforces passwords on links)
	 */
	public function createPublicShare(int $recipeId): IShare {
		$folder = $this->getRecipeFolder($recipeId);

		$existing = $this->findPublicShare($folder);
		if ($existing !== null) {
			return $existing;
		}

		$share = $this->shareManager->newShare();
		$share->setNode($folder)
			->setShareType(IShare::TYPE_LINK)
			->setSharedBy($this->getUserId())
			->setPermissions(Constants::PERMISSION_READ);

		return $this->shareManager->createShare($share);
	}

	/**
	 * @throws RecipeNotFoundException
	 * @throws UserNotLoggedInException
	 */
	public function deletePublicShare(int $recipeId): void {
		$share = $this->getPublicShare($recipeId);
		if ($share !== null) {
			$this->shareManager->deleteShare($share);
		}
	}

	public function getPublicUrl(IShare $share): string {
		return $this->urlGenerator->linkToRouteAbsolute('cookbook.public_recipe.show', ['token' => $share->getToken()]);
	}

	public function isReadableLinkShare(IShare $share): bool {
		return $share->getShareType() === IShare::TYPE_LINK
			&& ($share->getPermissions() & Constants::PERMISSION_READ) !== 0;
	}

	/**
	 * @throws RecipeNotFoundException
	 * @throws UserNotLoggedInException
	 */
	private function getRecipeFolder(int $recipeId): Folder {
		$file = $this->recipeService->getRecipeFileInFolder($this->userFolder->getFolder()->getById($recipeId)[0] ?? null);

		if ($file === null) {
			throw new RecipeNotFoundException($this->l->t('Recipe with ID %d not found.', [$recipeId]));
		}

		return $file->getParent();
	}

	private function findPublicShare(Folder $folder): ?IShare {
		$shares = $this->shareManager->getSharesBy($this->getUserId(), IShare::TYPE_LINK, $folder, false, -1);

		foreach ($shares as $share) {
			if ($this->isReadableLinkShare($share) && $share->getPassword() === null) {
				return $share;
			}
		}

		return null;
	}

	/**
	 * @throws UserNotLoggedInException
	 */
	private function getUserId(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new UserNotLoggedInException($this->l->t('The user is not logged in. No user configuration can be obtained.'));
		}
		return $user->getUID();
	}
}
