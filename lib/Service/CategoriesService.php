<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service;

use OCP\Calendar\ICalendarQuery;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;

class CategoriesService {
	private const MAX_RESULTS = 50;
	private const MAX_EVENTS_PER_CALENDAR = 20;
	private const MIN_SEARCH_LENGTH = 2;
	private const MAX_SEARCH_LENGTH = 100;

	public function __construct(
		private ?string $userId,
		private ICalendarManager $calendarManager,
		private ISystemTagManager $systemTagManager,
	) {
	}

	/**
	 * Search used event categories and assignable collaborative tags.
	 *
	 * The public calendar search API limits matching events per calendar, not
	 * globally. System-tag search has no result-limit parameter.
	 *
	 * @return list<array{label: string, value: string, source: string}>
	 */
	public function searchCategories(string $search): array {
		$search = trim($search);
		if ($this->userId === null || mb_strlen($search) < self::MIN_SEARCH_LENGTH) {
			return [];
		}
		$search = mb_substr($search, 0, self::MAX_SEARCH_LENGTH);

		$results = [];
		$seen = [];
		$systemTags = $this->systemTagManager->getAllTags(true, $search);
		/** @var ISystemTag $systemTag */
		foreach ($systemTags as $systemTag) {
			if (!$systemTag->isUserAssignable() || !$systemTag->isUserVisible()) {
				continue;
			}
			$this->addResult($results, $seen, $systemTag->getName(), 'collaborative');
			if (count($results) >= self::MAX_RESULTS) {
				return $results;
			}
		}

		$query = $this->calendarManager->newQuery('principals/users/' . $this->userId);
		$query->addSearchProperty(ICalendarQuery::SEARCH_PROPERTY_CATEGORIES);
		$query->addType('VEVENT');
		$query->setSearchPattern($search);
		$query->setLimit(self::MAX_EVENTS_PER_CALENDAR);

		foreach ($this->calendarManager->searchForPrincipal($query) as $event) {
			foreach ($event['objects'] ?? [] as $object) {
				foreach ($object['CATEGORIES'] ?? [] as $property) {
					$value = $property[0] ?? null;
					if (!is_string($value)) {
						continue;
					}

					$categories = $this->splitCategoryValue($value);
					foreach ($categories as $category) {
						if (mb_stripos($category, $search) === false) {
							continue;
						}
						$this->addResult($results, $seen, $category, 'custom');
						if (count($results) >= self::MAX_RESULTS) {
							return $results;
						}
					}
				}
			}
		}

		return $results;
	}

	/**
	 * The public calendar search result does not distinguish a single
	 * comma-containing category from multiple categories. Skip those ambiguous
	 * values rather than suggesting incorrect category names.
	 *
	 * @return list<string>
	 */
	private function splitCategoryValue(string $value): array {
		if (str_contains($value, ',') && !str_contains($value, '\\,')) {
			return [];
		}

		$categories = [];
		$category = '';
		$escaped = false;
		for ($i = 0, $length = strlen($value); $i < $length; $i++) {
			$character = $value[$i];
			if ($escaped) {
				$category .= $character === 'n' || $character === 'N' ? "\n" : $character;
				$escaped = false;
			} elseif ($character === '\\') {
				$escaped = true;
			} elseif ($character === ',') {
				$categories[] = $category;
				$category = '';
			} else {
				$category .= $character;
			}
		}
		if ($escaped) {
			$category .= '\\';
		}
		$categories[] = $category;

		return array_values(array_filter(array_map('trim', $categories), static fn (string $category): bool => $category !== ''));
	}

	/**
	 * @param list<array{label: string, value: string, source: string}> $results
	 * @param array<string, true> $seen
	 */
	private function addResult(array &$results, array &$seen, string $label, string $source): void {
		$label = trim($label);
		$key = mb_strtolower($label);
		if ($label === '' || isset($seen[$key])) {
			return;
		}

		$seen[$key] = true;
		$results[] = [
			'label' => $label,
			'value' => $label,
			'source' => $source,
		];
	}
}
