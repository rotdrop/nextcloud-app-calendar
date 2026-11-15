/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import HttpClient from '@nextcloud/axios'
import { linkTo } from '@nextcloud/router'

export interface CategorySuggestion {
	label: string
	value: string
	source: 'custom' | 'collaborative'
}

/**
 * Search for category suggestions on the server.
 *
 * @param search The category text to search for
 * @param signal Abort signal for superseded searches
 */
export async function searchCategories(search: string, signal?: AbortSignal): Promise<CategorySuggestion[]> {
	const response = await HttpClient.post<CategorySuggestion[]>(
		linkTo('calendar', 'index.php') + '/v1/autocompletion/categories',
		{ search },
		{ signal },
	)
	return response.data
}
