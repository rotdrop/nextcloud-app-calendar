<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Controller;

use OCA\Calendar\Service\CategoriesService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class CategoryController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private CategoriesService $categoriesService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function searchCategories(string $search): JSONResponse {
		return new JSONResponse($this->categoriesService->searchCategories($search));
	}
}
