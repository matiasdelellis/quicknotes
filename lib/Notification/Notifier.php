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

namespace OCA\QuickNotes\Notification;

use OCA\QuickNotes\AppInfo\Application;

use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the notification that `NoteReminderJob` queues when a note
 * reminder falls due.
 *
 * Registered from `Application::register()` with
 * `registerNotifierService()`.
 */
class Notifier implements INotifier {

	/** Subject of the "a note reminder is due" notification. */
	public const SUBJECT_REMINDER = 'note_reminder';

	/** Subject of the "somebody shared a note with you" notification. */
	public const SUBJECT_SHARE = 'note_shared';

	/** Object type every quicknotes notification is attached to. */
	public const OBJECT_NOTE = 'note';

	/** @var IFactory */
	private $l10nFactory;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IFactory      $l10nFactory,
	                            IURLGenerator $urlGenerator)
	{
		$this->l10nFactory  = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Quick notes');
	}

	/**
	 * The manager offers every notification to every notifier, so anything
	 * that is not ours has to be declined by throwing. Since Nextcloud 30
	 * that means `UnknownNotificationException`, not
	 * `\InvalidArgumentException`.
	 *
	 * @throws UnknownNotificationException
	 */
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		if (!in_array($notification->getSubject(), [self::SUBJECT_REMINDER, self::SUBJECT_SHARE], true)) {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);

		$parameters = $notification->getSubjectParameters();
		$title = $parameters['title'] ?? '';
		if ($title === '') {
			$title = $l->t('Untitled note');
		}

		// Must be black or colourless: clients invert it themselves when the
		// background calls for it.
		$notification->setIcon($this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')
		));

		// `?n=<id>` is the deep link the app already understands — the same
		// one NoteSearchProvider hands out.
		$notification->setLink($this->urlGenerator->linkToRouteAbsolute(
			'quicknotes.page.index',
			['n' => $notification->getObjectId()]
		));

		// parsedSubject is mandatory: without it the manager throws
		// IncompleteParsedNotificationException. richSubject is what current
		// clients actually render.
		$note = [
			'type' => 'highlight',
			'id'   => $notification->getObjectId(),
			'name' => $title,
		];

		if ($notification->getSubject() === self::SUBJECT_SHARE) {
			$sharedBy = $parameters['sharedBy'] ?? '';
			$sharedByName = $parameters['sharedByDisplayName'] ?? $sharedBy;

			$notification->setParsedSubject(
				$l->t('%1$s shared the note %2$s with you', [$sharedByName, $title])
			);
			$notification->setRichSubject(
				$l->t('{user} shared the note {note} with you'),
				[
					'user' => [
						'type' => 'user',
						'id'   => $sharedBy,
						'name' => $sharedByName,
					],
					'note' => $note,
				]
			);

			return $notification;
		}

		$notification->setParsedSubject($l->t('Reminder: %s', [$title]));
		$notification->setRichSubject(
			$l->t('Reminder: {note}'),
			[
				'note' => $note,
			]
		);

		return $notification;
	}

}
