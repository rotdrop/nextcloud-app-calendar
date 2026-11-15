<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="display" class="property-select-multiple">
		<component
			:is="icon"
			:title="info"
			:size="20"
			:name="readableName"
			class="property-select-multiple__icon"
			:class="{ 'property-select-multiple__icon--hidden': !showIcon }" />

		<div
			class="property-select-multiple__input"
			:class="{ 'property-select-multiple__input--readonly': isReadOnly }">
			<NcSelect
				v-if="!isReadOnly"
				:modelValue="selectionData"
				:options="options"
				:searchable="true"
				:placeholder="placeholder"
				:name="readableName"
				:multiple="true"
				:selectable="isSelectable"
				:taggable="true"
				:noWrap="false"
				:deselectFromDropdown="false"
				:createOption="(label) => ({ value: label, label })"
				:inputId="readableName + '-select-multiple-input'"
				:ariaLabelCombobox="readableName"
				:inputLabel="readableName"
				:ariaLabelListbox="readableName"
				:loading="isLoadingSuggestions"
				label="label"
				@search="searchOptions"
				@option:selecting="tag"
				@option:deselected="unselectValue">
				<template v-if="coloredOptions" #option="scope">
					<PropertySelectMultipleColoredOption :option="scope" />
				</template>
				<template v-if="coloredOptions" #selected-option-container="scope">
					<PropertySelectMultipleColoredOption :option="scope.option" :closeable="true" @deselect="unselectValue" />
				</template>
			</NcSelect>
			<!-- eslint-disable-next-line vue/singleline-html-element-content-newline -->
			<div v-else class="property-select-multiple-colored-tag-wrapper">
				<PropertySelectMultipleColoredTag
					v-for="singleValue in value"
					:key="singleValue.value"
					:option="singleValue" />
			</div>
		</div>
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { getLocale, translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import debounce from 'debounce'
import InformationVariant from 'vue-material-design-icons/InformationVariant.vue'
import PropertySelectMultipleColoredOption from '@/components/Editor/Properties/PropertySelectMultipleColoredOption.vue'
import PropertySelectMultipleColoredTag from '@/components/Editor/Properties/PropertySelectMultipleColoredTag.vue'
import PropertyMixin from '@/mixins/PropertyMixin.js'
import { searchCategories } from '@/services/categoryService.ts'
import logger from '@/utils/logger.js'

export default {
	name: 'PropertySelectMultiple',
	components: {
		PropertySelectMultipleColoredOption,
		PropertySelectMultipleColoredTag,

		NcSelect,
		InformationVariant,
	},

	mixins: [
		PropertyMixin,
	],

	props: {
		coloredOptions: {
			type: Boolean,
			default: false,
		},

		customLabelHeading: {
			type: String,
			default: 'Custom Categories',
		},

		collaborativeLabelHeading: {
			type: String,
			default: 'Collaborative Tags',
		},
	},

	emits: ['removeSingleValue', 'addSingleValue'],

	data() {
		return {
			selectionData: [],
			customLabelBuffer: [],
			remoteOptions: [],
			searchQuery: '',
			searchRequestId: 0,
			isLoadingSuggestions: false,
			debouncedSearch: null,
			searchAbortController: null,
		}
	},

	computed: {
		display() {
			return !(this.isReadOnly && this.selectionData.length === 0)
		},

		options() {
			const options = []
			const customOptions = this.remoteOptions.filter((option) => option.source === 'custom')
			const collaborativeOptions = this.remoteOptions.filter((option) => option.source === 'collaborative')
			const sortOptions = (items) => items.sort((a, b) => a.label.localeCompare(
				b.label,
				getLocale().replace('_', '-'),
				{ sensitivity: 'base' },
			))
			if (customOptions.length > 0) {
				options.push({ label: this.customLabelHeading, isGroupHeading: true, value: null })
				options.push(...sortOptions(customOptions))
			}
			if (collaborativeOptions.length > 0) {
				options.push({ label: this.collaborativeLabelHeading, isGroupHeading: true, value: null })
				options.push(...sortOptions(collaborativeOptions))
			}
			options.push(...this.propModel.options)

			for (const category of this.selectionData) {
				if (!options.some((option) => option.value === category.value)) {
					options.push(category)
				}
			}
			for (const category of this.customLabelBuffer) {
				if (!options.some((option) => option.value === category.value)) {
					options.push(category)
				}
			}

			return options
		},
	},

	watch: {
		value() {
			this.updateSelectionData()
		},
	},

	created() {
		this.updateSelectionData()
		this.debouncedSearch = debounce(this.loadCategorySuggestions, 300)
	},

	beforeUnmount() {
		this.debouncedSearch.clear()
		this.searchAbortController?.abort()
		this.searchRequestId++
	},

	methods: {
		searchOptions(query) {
			this.searchAbortController?.abort()
			this.searchAbortController = null
			this.searchQuery = query.trim()
			this.searchRequestId++
			this.remoteOptions = []
			this.isLoadingSuggestions = false
			this.debouncedSearch.clear()

			if (this.searchQuery.length >= 2) {
				this.debouncedSearch(this.searchQuery, this.searchRequestId)
			}
		},

		async loadCategorySuggestions(query, requestId) {
			const abortController = new AbortController()
			this.searchAbortController = abortController
			this.isLoadingSuggestions = true
			try {
				const options = await searchCategories(query, abortController.signal)
				if (requestId === this.searchRequestId && query === this.searchQuery) {
					this.remoteOptions = options
				}
			} catch (error) {
				if (requestId === this.searchRequestId && !abortController.signal.aborted) {
					logger.error('Could not load category suggestions', { error })
					showError(t('calendar', 'Could not load category suggestions'))
				}
			} finally {
				if (requestId === this.searchRequestId) {
					this.isLoadingSuggestions = false
					this.searchAbortController = null
				}
			}
		},

		isSelectable(option) {
			return !option.isGroupHeading
		},

		updateSelectionData() {
			for (const category of (this.value ?? [])) {
				// Create and select pseudo option if is not yet known
				const option = this.options.find((option) => option.value === category)
					?? { label: category, value: category }
				if (this.selectionData.findIndex((option) => option.value === category) === -1) {
					this.selectionData.push(option)
				}
			}
		},

		unselectValue(value) {
			if (!value) {
				return
			}

			this.$emit('removeSingleValue', value.value)

			this.selectionData.splice(this.selectionData.findIndex((option) => option.value === value.value), 1)

			// store removed custom options to keep it in the option list
			const options = this.propModel.options.slice()
			if (!options.find((option) => option.value === value.value)) {
				if (!this.customLabelBuffer) {
					this.customLabelBuffer = []
				}
				this.customLabelBuffer.push(value)
			}
		},

		tag(value) {
			if (!value) {
				return
			}

			// budget deselectFromDropdown since the vue-select implementation doesn't work
			if (this.selectionData.find((option) => option.value === value.value)) {
				this.selectionData.splice(this.selectionData.findIndex((option) => option.value === value.value), 1)
			}

			this.selectionData.push(value)
			this.$emit('addSingleValue', value.value)
		},
	},
}
</script>
