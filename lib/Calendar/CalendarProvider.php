<?php declare(strict_types=1);
/*
 * @copyright 2016-2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\QuickNotes\Calendar;

use OCA\QuickNotes\Service\ReminderService;
use OCA\QuickNotes\Service\SettingsService;

use OCP\Calendar\ICalendarProvider;
use OCP\IL10N;

/**
 * Publishes the notes with a reminder as a calendar.
 *
 * Registered with `registerCalendarProvider()`, which puts the calendar in
 * reach of `OCP\Calendar\IManager` for other apps and — through the
 * `AppCalendarPlugin` of the dav app — of CalDAV, and so of the Calendar app
 * and of any client the user has.
 *
 * Off unless the user asked for it, see SettingsService::isCalendarEnabled().
 */
class CalendarProvider implements ICalendarProvider {

	/** Prefix of the principals this provider answers for. */
	private const USER_PRINCIPAL_PREFIX = 'principals/users/';

	/** @var ReminderService */
	private $reminderService;

	/** @var SettingsService */
	private $settingsService;

	/** @var IL10N */
	private $l10n;

	public function __construct(ReminderService $reminderService,
	                            SettingsService $settingsService,
	                            IL10N           $l10n)
	{
		$this->reminderService = $reminderService;
		$this->settingsService = $settingsService;
		$this->l10n            = $l10n;
	}

	/**
	 * @param string $principalUri e.g. 'principals/users/alice'
	 * @param array $calendarUris when given, only these uris are wanted
	 *
	 * @return NotesCalendar[] empty when this provider has nothing to offer
	 */
	public function getCalendars(string $principalUri, array $calendarUris = []): array {
		// Only real users have notes: group and system principals, and the
		// calendar-room / calendar-resource ones, are none of our business.
		if (!str_starts_with($principalUri, self::USER_PRINCIPAL_PREFIX)) {
			return [];
		}

		$userId = substr($principalUri, strlen(self::USER_PRINCIPAL_PREFIX));
		if ($userId === '') {
			return [];
		}

		// A filtered query that does not ask for ours is answered with nothing,
		// before touching the config or the database.
		if (!empty($calendarUris) && !in_array(NotesCalendar::URI, $calendarUris, true)) {
			return [];
		}

		if (!$this->settingsService->isCalendarEnabled($userId)) {
			return [];
		}

		return [new NotesCalendar($this->reminderService, $this->l10n, $userId)];
	}

}
