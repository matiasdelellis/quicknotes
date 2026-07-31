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

namespace OCA\QuickNotes\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

use OCA\QuickNotes\Service\ReminderService;

/**
 * Sends the notification for every note reminder that has fallen due.
 *
 * Accuracy is bounded by the Nextcloud cron itself, which runs every five
 * minutes at best — and only while somebody is browsing if the instance is
 * on AJAX cron. A reminder is therefore "around" its time, never exactly on
 * it. That is the same guarantee the Calendar app gives.
 */
class NoteReminderJob extends TimedJob {

	/** @var ReminderService */
	private $reminderService;

	public function __construct(ITimeFactory    $time,
	                            ReminderService $reminderService)
	{
		parent::__construct($time);
		$this->reminderService = $reminderService;

		// As often as the cron can realistically deliver.
		$this->setInterval(5 * 60);
		// Unlike PurgeOldTrashJob, this one must not be deferred to a quiet
		// moment: a reminder that shows up an hour late is worthless.
		$this->setTimeSensitivity(self::TIME_SENSITIVE);
	}

	protected function run($argument): void {
		$this->reminderService->notifyDue();
	}

}
