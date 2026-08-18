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

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Service\ReminderService;

use OCP\Calendar\ICalendar;
use OCP\Constants;
use OCP\IL10N;

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;

/**
 * The reminders of one user, seen as a read-only calendar.
 *
 * One calendar per principal, and since 0.9.2 reminders are personal, so this
 * shows each user the dates *they* armed — on their own notes and on the ones
 * shared with them — and never anybody else's.
 *
 * Nothing is stored: every read derives the events from the notes and the
 * reminders on the spot. That is the whole point of going through a provider instead of
 * writing real events into a calendar of the user — editing or deleting a
 * note is reflected immediately, and there is no second copy to keep in sync
 * (and no way to keep it in sync either: OCP\Calendar has no update or delete
 * API, only ICreateFromString).
 */
class NotesCalendar implements ICalendar {

	/** Identifies the calendar within the principal. */
	public const URI = 'quicknotes';

	/** How long a reminder lasts in the calendar grid. It is a point in time,
	 *  but a zero length event is invisible in most clients. */
	private const EVENT_MINUTES = 30;

	/** Notes can be long; the event description is not the place for all of it. */
	private const MAX_DESCRIPTION_LENGTH = 500;

	/** @var ReminderService */
	private $reminderService;

	/** @var IL10N */
	private $l10n;

	/** @var string */
	private $userId;

	public function __construct(ReminderService $reminderService,
	                            IL10N           $l10n,
	                            string          $userId)
	{
		$this->reminderService = $reminderService;
		$this->l10n            = $l10n;
		$this->userId          = $userId;
	}

	public function getKey(): string {
		// Unique across the instance, so it carries the user.
		return self::URI . '-' . $this->userId;
	}

	public function getUri(): string {
		return self::URI;
	}

	public function getDisplayName(): ?string {
		return $this->l10n->t('Quick notes');
	}

	public function getDisplayColor(): ?string {
		// The yellow of a new note, so it is recognisable next to the others.
		return '#F7EB96';
	}

	public function getPermissions(): int {
		// Derived from the notes, so there is nothing to write back to. The
		// wrapper of the dav app only advertises write support for calendars
		// that also implement ICreateFromString, which this one does not.
		return Constants::PERMISSION_READ;
	}

	public function isDeleted(): bool {
		return false;
	}

	/**
	 * @param string $pattern text to look for, empty to match everything
	 * @param array $searchProperties properties the pattern applies to
	 * @param array $options 'timerange', 'uid', 'types' — see ICalendar
	 * @param int|null $limit
	 * @param int|null $offset
	 *
	 * @return VEvent[]
	 */
	public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
		// This calendar only ever holds events, so a query for anything else
		// has nothing to say about it.
		$types = $options['types'] ?? [];
		if (!empty($types) && !in_array('VEVENT', $types, true)) {
			return [];
		}

		// Each note comes carrying this user's own reminder date.
		$notes = $this->reminderService->findNotesWithRemindersOf($this->userId);

		$events = [];
		foreach ($notes as $note) {
			$start = $this->reminderDateTime($note);
			if ($start === null) {
				continue;
			}

			if (!$this->matchesTimerange($start, $options)) {
				continue;
			}

			if (!$this->matchesIdentity($note, $pattern, $searchProperties, $options)) {
				continue;
			}

			if (!$this->matchesPattern($note, $pattern, $searchProperties)) {
				continue;
			}

			$events[] = $this->toEvent($note, $start);
		}

		// The reminders come ordered by date, which is the order
		// ICalendar::search() is expected to return.
		if ($offset !== null || $limit !== null) {
			$events = array_slice($events, $offset ?? 0, $limit);
		}

		return $events;
	}

	/**
	 * The reminder column as a DateTime, or null when it holds something the
	 * stored format does not cover — one odd row must not break the calendar.
	 */
	private function reminderDateTime(Note $note): ?\DateTimeImmutable {
		$parsed = \DateTimeImmutable::createFromFormat(
			ReminderService::DATE_FORMAT,
			(string)$note->getReminderAt(),
			new \DateTimeZone('UTC')
		);
		return $parsed === false ? null : $parsed;
	}

	/**
	 * Both ends are optional, and an event that merely overlaps the window
	 * counts as inside it.
	 */
	private function matchesTimerange(\DateTimeImmutable $start, array $options): bool {
		$end = $start->add(new \DateInterval('PT' . self::EVENT_MINUTES . 'M'));

		$from = $options['timerange']['start'] ?? null;
		if ($from !== null && $end < $from) {
			return false;
		}

		$to = $options['timerange']['end'] ?? null;
		if ($to !== null && $start > $to) {
			return false;
		}

		return true;
	}

	/**
	 * A lookup for one specific object rather than a text search. It reaches
	 * this in three shapes, and all of them have to be honoured:
	 *
	 *   - the 'uid' option;
	 *   - the pattern, with 'UID' among the searched properties;
	 *   - the pattern as a *file name*, with 'X-FILENAME' among them.
	 *
	 * That last one is how AppCalendar::getChild() of the dav app resolves a
	 * single event, and it tries it first. Answering it with everything — as
	 * "no such property, so nothing to filter on" would — makes every .ics url
	 * of the calendar return the whole calendar, because the wrapper takes the
	 * non-empty result and never reaches its own fallback by uid.
	 *
	 * These events carry no X-FILENAME, so the name to compare against is the
	 * one CalendarObject::getName() derives: the uid plus '.ics'.
	 */
	private function matchesIdentity(Note $note, string $pattern, array $searchProperties, array $options): bool {
		$uid = $options['uid'] ?? null;

		if ($uid === null && $pattern !== '') {
			if (in_array('UID', $searchProperties, true)) {
				$uid = $pattern;
			} elseif (in_array('X-FILENAME', $searchProperties, true)) {
				$uid = preg_replace('/\.ics$/', '', $pattern);
			}
		}

		return $uid === null || $uid === $this->uidFor($note);
	}

	/**
	 * Free text matching. With no properties given the pattern applies to
	 * both the title and the body, which is what a user searching a calendar
	 * expects; otherwise only the properties this calendar actually fills are
	 * considered.
	 */
	private function matchesPattern(Note $note, string $pattern, array $searchProperties): bool {
		if ($pattern === '') {
			return true;
		}

		// For these two the pattern is an identifier, not free text, and
		// matchesIdentity() has already applied it.
		if (in_array('UID', $searchProperties, true) ||
		    in_array('X-FILENAME', $searchProperties, true)) {
			return true;
		}

		$haystacks = [];
		if (empty($searchProperties) || in_array('SUMMARY', $searchProperties, true)) {
			$haystacks[] = $this->plainText($note->getTitle());
		}
		if (empty($searchProperties) || in_array('DESCRIPTION', $searchProperties, true)) {
			$haystacks[] = $this->plainText($note->getContent());
		}

		foreach ($haystacks as $haystack) {
			if (mb_stripos($haystack, $pattern) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Stable across reads, so a client that syncs this calendar keeps
	 * recognising the same note as the same event.
	 */
	private function uidFor(Note $note): string {
		return 'quicknotes-note-' . $note->getId();
	}

	private function toEvent(Note $note, \DateTimeImmutable $start): VEvent {
		$summary = $this->plainText($note->getTitle());
		if ($summary === '') {
			$summary = $this->l10n->t('Untitled note');
		}

		$description = $this->plainText($note->getContent());
		if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
			$description = mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH - 1) . '…';
		}

		// The event has to be built inside a VCalendar: a bare VEvent has no
		// document to resolve property classes against, so DTSTART would not
		// come out as a date-time.
		$vcalendar = new VCalendar();
		/** @var VEvent $event */
		$event = $vcalendar->createComponent('VEVENT');

		$event->UID = $this->uidFor($note);
		$event->SUMMARY = $summary;
		$event->DTSTART = $start;
		$event->DTEND = $start->add(new \DateInterval('PT' . self::EVENT_MINUTES . 'M'));
		$event->TRANSP = 'TRANSPARENT';

		if ($description !== '') {
			$event->DESCRIPTION = $description;
		}

		// Notes carry a single edit timestamp, which is the best answer for
		// both of these.
		$stamp = (new \DateTimeImmutable('@' . $note->getTimestamp()))
			->setTimezone(new \DateTimeZone('UTC'));
		$event->DTSTAMP = $stamp;
		$event->{'LAST-MODIFIED'} = $stamp;

		// No VALARM on purpose. These events never reach the `calendarobjects`
		// table, so the ReminderService of the dav app would not act on one
		// anyway — but a CalDAV client would, and the user would get the
		// notification twice: once from quicknotes, once from the client.

		return $event;
	}

	/** Titles and bodies hold the same basic rich text the editor produces. */
	private function plainText(?string $html): string {
		return trim(strip_tags((string)$html));
	}

}
