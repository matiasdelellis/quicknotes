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

namespace OCA\QuickNotes\Service;

use OCA\QuickNotes\AppInfo\Application;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Notification\Notifier;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager as INotificationManager;

use Psr\Log\LoggerInterface;

/**
 * Everything notification-shaped about note reminders.
 *
 * Deliberately depends on NoteMapper and not on NoteService, so that
 * NoteService can depend on this one without a cycle.
 */
class ReminderService {

	/** Format the reminder columns are stored in (UTC). */
	public const DATE_FORMAT = 'Y-m-d H:i:s';

	/** Notification subjects are not the place for an essay of a title. */
	private const MAX_TITLE_LENGTH = 100;

	/** @var NoteMapper */
	private $noteMapper;

	/** @var INotificationManager */
	private $notificationManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(NoteMapper           $noteMapper,
	                            INotificationManager $notificationManager,
	                            ITimeFactory         $timeFactory,
	                            LoggerInterface      $logger)
	{
		$this->noteMapper          = $noteMapper;
		$this->notificationManager = $notificationManager;
		$this->timeFactory         = $timeFactory;
		$this->logger              = $logger;
	}

	/**
	 * Validate a reminder date coming from a client and normalise it to the
	 * string stored in the column. Null passes through: it means "cancel".
	 *
	 * @param string|null $reminderAt UTC 'Y-m-d H:i:s'
	 *
	 * @throws \InvalidArgumentException if the string is not a UTC datetime
	 *                                   in that exact format
	 */
	public function normalize(?string $reminderAt): ?string {
		if (is_null($reminderAt) || $reminderAt === '') {
			return null;
		}

		$parsed = \DateTime::createFromFormat(
			self::DATE_FORMAT,
			$reminderAt,
			new \DateTimeZone('GMT')
		);

		// createFromFormat() is lenient — it happily reads '2026-13-45
		// 99:99:99' and rolls it over. Re-formatting and comparing is what
		// actually rejects nonsense.
		if ($parsed === false || $parsed->format(self::DATE_FORMAT) !== $reminderAt) {
			throw new \InvalidArgumentException(
				'Reminder must be a UTC datetime formatted as ' . self::DATE_FORMAT
			);
		}

		return $reminderAt;
	}

	/**
	 * Notify every reminder that has fallen due, and mark it as notified.
	 *
	 * @return int the number of notifications queued
	 */
	public function notifyDue(): int {
		$now = $this->timeFactory->getDateTime('now', new \DateTimeZone('GMT'));

		$notes = $this->noteMapper->findDueReminders($now);
		if (count($notes) === 0) {
			return 0;
		}

		// One transaction-ish round trip to the push service instead of one
		// per note.
		$deferred = $this->notificationManager->defer();

		$sent = 0;
		try {
			foreach ($notes as $note) {
				try {
					$this->notify($note);
				} catch (\Throwable $e) {
					// Leave the row unnotified so the next run retries it,
					// and let the rest of the batch through.
					$this->logger->warning(
						'quicknotes: could not notify the reminder of note ' . $note->getId(),
						['exception' => $e]
					);
					continue;
				}

				$this->noteMapper->markReminderNotified($note->getId(), $now);
				$sent++;
			}
		} finally {
			if ($deferred) {
				$this->notificationManager->flush();
			}
		}

		return $sent;
	}

	/**
	 * Withdraw any pending reminder notification for a note. Called when the
	 * reminder is moved or cancelled, and when the note goes to the trash or
	 * is deleted for good — otherwise a notification for a date that no
	 * longer exists sits in the user's list forever.
	 */
	public function dismiss(string $userId, int $noteId): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($userId)
			->setObject(Notifier::OBJECT_NOTE, (string)$noteId);

		$this->notificationManager->markProcessed($notification);
	}

	private function notify(Note $note): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($note->getUserId())
			->setDateTime($this->reminderDateTime($note))
			->setObject(Notifier::OBJECT_NOTE, (string)$note->getId())
			->setSubject(Notifier::SUBJECT_REMINDER, [
				'title' => $this->plainTitle($note),
			]);

		$this->notificationManager->notify($notification);
	}

	/**
	 * The reminder date as a DateTime, since setDateTime() takes nothing
	 * else. Falls back to now if the column somehow holds something the
	 * format does not cover, so a single odd row cannot break the batch.
	 */
	private function reminderDateTime(Note $note): \DateTime {
		$parsed = \DateTime::createFromFormat(
			self::DATE_FORMAT,
			(string)$note->getReminderAt(),
			new \DateTimeZone('GMT')
		);

		if ($parsed === false) {
			return $this->timeFactory->getDateTime('now', new \DateTimeZone('GMT'));
		}

		return $parsed;
	}

	/**
	 * Note titles carry the same basic rich text as the body, so they get
	 * flattened the way the dashboard widget already flattens them.
	 */
	private function plainTitle(Note $note): string {
		$title = trim(strip_tags((string)$note->getTitle()));

		if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
			$title = mb_substr($title, 0, self::MAX_TITLE_LENGTH - 1) . '…';
		}

		return $title;
	}

}
