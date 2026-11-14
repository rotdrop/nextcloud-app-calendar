<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCP\Calendar\ICalendarQuery;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use PHPUnit\Framework\MockObject\MockObject;

class CategoriesServiceTest extends TestCase {
	private ICalendarManager&MockObject $calendarManager;
	private ISystemTagManager&MockObject $systemTagManager;

	protected function setUp(): void {
		parent::setUp();

		$this->calendarManager = $this->createMock(ICalendarManager::class);
		$this->systemTagManager = $this->createMock(ISystemTagManager::class);
	}

	public function testSearchReturnsNoSuggestionsForShortQuery(): void {
		$this->systemTagManager->expects(self::never())->method('getAllTags');
		$this->calendarManager->expects(self::never())->method('newQuery');

		$service = new CategoriesService('user123', $this->calendarManager, $this->systemTagManager);

		self::assertSame([], $service->searchCategories(' a '));
	}

	public function testSearchReturnsOnlyMatchingAssignableTagsAndCategories(): void {
		$tag = $this->createMock(ISystemTag::class);
		$tag->method('isUserAssignable')->willReturn(true);
		$tag->method('isUserVisible')->willReturn(true);
		$tag->method('getName')->willReturn('Meeting');
		$hiddenTag = $this->createMock(ISystemTag::class);
		$hiddenTag->method('isUserAssignable')->willReturn(false);
		$hiddenTag->method('isUserVisible')->willReturn(true);
		$hiddenTag->method('getName')->willReturn('Meetings hidden');
		$this->systemTagManager->expects(self::once())
			->method('getAllTags')
			->with(true, 'meet')
			->willReturn([$tag, $hiddenTag]);

		$query = $this->createMock(ICalendarQuery::class);
		$query->expects(self::once())->method('addSearchProperty')->with(ICalendarQuery::SEARCH_PROPERTY_CATEGORIES);
		$query->expects(self::once())->method('addType')->with('VEVENT');
		$query->expects(self::once())->method('setSearchPattern')->with('meet');
		$query->expects(self::once())->method('setLimit')->with(20);
		$this->calendarManager->expects(self::once())
			->method('newQuery')
			->with('principals/users/user123')
			->willReturn($query);
		$this->calendarManager->expects(self::once())
			->method('searchForPrincipal')
			->with($query)
			->willReturn([
				['objects' => [
					['CATEGORIES' => [['Meeting', []], ['Research,Meetings', []]]],
					['CATEGORIES' => [['Lab\\, North,Meetup', []]]],
				]],
			]);

		$service = new CategoriesService('user123', $this->calendarManager, $this->systemTagManager);

		self::assertSame([
			['label' => 'Meeting', 'value' => 'Meeting', 'source' => 'collaborative'],
			['label' => 'Meetup', 'value' => 'Meetup', 'source' => 'custom'],
		], $service->searchCategories(' meet '));
	}

	public function testSearchCapsReturnedSuggestions(): void {
		$tags = [];
		for ($index = 0; $index < 51; $index++) {
			$tag = $this->createMock(ISystemTag::class);
			$tag->method('isUserAssignable')->willReturn(true);
			$tag->method('isUserVisible')->willReturn(true);
			$tag->method('getName')->willReturn('Meeting ' . $index);
			$tags[] = $tag;
		}
		$this->systemTagManager->method('getAllTags')->willReturn($tags);
		$this->calendarManager->expects(self::never())->method('newQuery');

		$service = new CategoriesService('user123', $this->calendarManager, $this->systemTagManager);

		self::assertCount(50, $service->searchCategories('meet'));
	}
}
