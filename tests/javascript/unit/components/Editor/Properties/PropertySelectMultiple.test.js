/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import PropertySelectMultiple from '@/components/Editor/Properties/PropertySelectMultiple.vue'
import { searchCategories } from '@/services/categoryService.ts'

vi.mock('@/services/categoryService.ts', () => ({
	searchCategories: vi.fn(),
}))

describe('components/Editor/Properties/PropertySelectMultiple', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows remote category sources in separate groups with local defaults', () => {
		const options = PropertySelectMultiple.computed.options.call({
			remoteOptions: [
				{ label: 'Meeting', value: 'Meeting', source: 'collaborative' },
				{ label: 'Work', value: 'Work', source: 'custom' },
			],
			propModel: { options: [{ label: 'Personal', value: 'Personal' }] },
			selectionData: [{ label: 'Existing', value: 'Existing' }],
			customLabelBuffer: [],
			customLabelHeading: 'Custom Categories',
			collaborativeLabelHeading: 'Collaborative Tags',
		})

		expect(options).toEqual([
			{ label: 'Custom Categories', isGroupHeading: true, value: null },
			{ label: 'Work', value: 'Work', source: 'custom' },
			{ label: 'Collaborative Tags', isGroupHeading: true, value: null },
			{ label: 'Meeting', value: 'Meeting', source: 'collaborative' },
			{ label: 'Personal', value: 'Personal' },
			{ label: 'Existing', value: 'Existing' },
		])
	})

	it('does not request suggestions for short search text', () => {
		const debouncedSearch = vi.fn()
		debouncedSearch.clear = vi.fn()
		const component = {
			searchQuery: '',
			searchRequestId: 0,
			remoteOptions: [],
			isLoadingSuggestions: false,
			debouncedSearch,
		}

		PropertySelectMultiple.methods.searchOptions.call(component, 'c')

		expect(debouncedSearch).not.toHaveBeenCalled()
		expect(component.remoteOptions).toEqual([])
	})

	it('loads suggestions and discards stale responses', async () => {
		let resolveSearch
		let signal
		searchCategories.mockImplementation((query, abortSignal) => {
			signal = abortSignal
			return new Promise((resolve) => {
				resolveSearch = resolve
			})
		})
		const component = {
			searchQuery: 'meet',
			searchRequestId: 1,
			remoteOptions: [],
			isLoadingSuggestions: false,
			debouncedSearch: Object.assign(vi.fn(), { clear: vi.fn() }),
		}

		const pendingSearch = PropertySelectMultiple.methods.loadCategorySuggestions.call(component, 'meet', 1)
		PropertySelectMultiple.methods.searchOptions.call(component, 'meeting')
		resolveSearch([{ label: 'Meeting', value: 'Meeting', source: 'custom' }])
		await pendingSearch

		expect(component.remoteOptions).toEqual([])
		expect(component.isLoadingSuggestions).toBe(false)
		expect(signal.aborted).toBe(true)
	})
})
