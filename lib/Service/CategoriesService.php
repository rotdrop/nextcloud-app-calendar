<?php

declare(strict_types=1);
/**
 * Calendar App
 *
 * @copyright 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Calendar\Service;

use OCP\AppFramework\Http;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTag;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\Calendar\ICalendarQuery;
use OCP\Calendar\ICalendar;

class CategoriesService {
	/** @var null|string */
	private $userId;

	/** @var ICalendarManager */
	private $calendarManager;

	/** @var ISystemTagManager */
	private $systemTagManager;

	/** @var IDBConnection */
	private $db;

	/** @var LoggerInterface */
	private $logger;

	/** @var IL10N */
	private $l;

	private const CALENDAR_OBJECT_PROPERTIES_TABLE = 'calendarobjects_props';

	public function __construct(?string $userId,
								ICalendarManager $calendarManager,
								ISystemTagManager $systemTagManager,
								IDBConnection $db,
								LoggerInterface $logger,
								IL10N $l10n) {
		$this->userId = $userId;
		$this->calendarManager = $calendarManager;
		$this->systemTagManager = $systemTagManager;
		$this->db = $db;
		$this->logger = $logger;
		$this->l = $l10n;
	}

	/**
	 * This is a simplistic brute-force extraction of all already used
	 * categories from all events accessible to the currently logged in user.
	 *
	 * @return array
	 */
	private function getUsedCategories(): array
	{
		if (empty($this->userId)) {
			return [];
		}
		$principalUri = 'principals/users/' . $this->userId;
		$calendars = $this->calendarManager->getCalendarsForPrincipal('principals/users/' . $this->userId);
		$count = count($calendars);
		if ($count === 0) {
			return [];
		}

		$categories = [];
		$query = $this->calendarManager->newQuery($principalUri);
		$query->addSearchProperty(ICalendarQuery::SEARCH_PROPERTY_CATEGORIES);
		$calendarObjects = $this->calendarManager->searchForPrincipal($query);
		foreach ($calendarObjects as $objectInfo) {
			foreach ($objectInfo['objects'] as $calendarObject) {
				if (isset($calendarObject['CATEGORIES'])) {
					$categories[] = explode(',', $calendarObject['CATEGORIES'][0][0]);
				}
			}
		}

		// Avoid injecting "broken" categories into the UI (avoid empty
		// categories and categories surrounded by spaces)
		$categories = array_filter(array_map(fn($label) => trim($label), array_unique(array_merge(...$categories))));

		return $categories;
	}

	/**
	 * Return a grouped array with all previously used categories, all system
	 * tags and all categories found the the iCalendar RFC.
	 *
	 * @return array
	 */
	public function getCategories(): array {
		$systemTags = $this->systemTagManager->getAllTags(visibilityFilter: true);

		$systemTagCategoryLabels = [];
		/** @var ISystemTag $systemTag */
		foreach ($systemTags as $systemTag) {
			if (!$systemTag->isUserAssignable() || !$systemTag->isUserVisible()) {
				continue;
			}
			$systemTagCategoryLabels[] = $systemTag->getName();
		}
		sort($systemTagCategoryLabels);
		$systemTagCategoryLabels = array_values(array_filter(array_unique($systemTagCategoryLabels)));

		$rfcCategoryLabels = [
			$this->l->t('Anniversary'),
			$this->l->t('Appointment'),
			$this->l->t('Business'),
			$this->l->t('Education'),
			$this->l->t('Holiday'),
			$this->l->t('Meeting'),
			$this->l->t('Miscellaneous'),
			$this->l->t('Non-working hours'),
			$this->l->t('Not in office'),
			$this->l->t('Personal'),
			$this->l->t('Phone call'),
			$this->l->t('Sick day'),
			$this->l->t('Special occasion'),
			$this->l->t('Travel'),
			$this->l->t('Vacation'),
		];
		sort($rfcCategoryLabels);
		$rfcCategoryLabels = array_values(array_filter(array_unique($rfcCategoryLabels)));

		$standardCategories = array_merge($systemTagCategoryLabels, $rfcCategoryLabels);
		$customCategoryLabels = array_values(array_filter($this->getUsedCategories(), fn($label) => !in_array($label, $standardCategories)));

		$categories = [
			[
				'group' => $this->l->t('Custom Categories'),
				'options' => array_map(fn(string $label) => [ 'label' => $label, 'value' => $label ], $customCategoryLabels),
			],
			[
				'group' => $this->l->t('Collaborative Tags'),
				'options' => array_map(fn(string $label) => [ 'label' => $label, 'value' => $label ], $systemTagCategoryLabels),
			],
			[
				'group' => $this->l->t('Standard Categories'),
				'options' => array_map(fn(string $label) => [ 'label' => $label, 'value' => $label ], $rfcCategoryLabels),
			],
		];


		return $categories;
	}
}
