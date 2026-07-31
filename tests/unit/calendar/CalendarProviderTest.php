<?php
/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2026
 */

namespace OCA\QuickNotes\Calendar;

use OCP\IL10N;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Service\SettingsService;


class CalendarProviderTest extends TestCase {

	private $provider;
	private $settingsService;

	protected function setUp(): void {
		$this->settingsService = $this->createMock(SettingsService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->provider = new CalendarProvider(
			$this->createMock(NoteMapper::class),
			$this->settingsService,
			$l10n
		);
	}

	private function enabled(bool $enabled): void {
		$this->settingsService->method('isCalendarEnabled')->willReturn($enabled);
	}

	public function testOffersTheCalendarWhenTheUserAskedForIt(): void {
		$this->enabled(true);

		$calendars = $this->provider->getCalendars('principals/users/john');

		$this->assertCount(1, $calendars);
		$this->assertSame('quicknotes', $calendars[0]->getUri());
	}

	public function testOffersNothingWhenTheSettingIsOff(): void {
		$this->enabled(false);

		$this->assertSame([], $this->provider->getCalendars('principals/users/john'));
	}

	public function testReadsTheSettingOfThePrincipalNotOfTheSession(): void {
		// On the CalDAV path there is no logged in user, so the uid has to come
		// out of the principal.
		$this->settingsService->expects($this->once())
			->method('isCalendarEnabled')
			->with('john')
			->willReturn(true);

		$this->provider->getCalendars('principals/users/john');
	}

	public function testAnsweringAFilteredQueryThatWantsOursOnly(): void {
		$this->enabled(true);

		$this->assertCount(1, $this->provider->getCalendars('principals/users/john', ['quicknotes']));
		$this->assertSame([], $this->provider->getCalendars('principals/users/john', ['personal']));
	}

	/**
	 * A query for somebody else's calendars must not even reach the config or
	 * the database.
	 */
	public function testIgnoresPrincipalsThatAreNotUsers(): void {
		$this->settingsService->expects($this->never())->method('isCalendarEnabled');

		$this->assertSame([], $this->provider->getCalendars('principals/system/system'));
		$this->assertSame([], $this->provider->getCalendars('principals/groups/admin'));
		$this->assertSame([], $this->provider->getCalendars('principals/calendar-rooms/room-1'));
		$this->assertSame([], $this->provider->getCalendars('principals/users/'));
	}

}
