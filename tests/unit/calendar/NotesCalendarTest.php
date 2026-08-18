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

use OCP\Constants;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Service\ReminderService;


class NotesCalendarTest extends TestCase {

	private $calendar;
	private $reminderService;

	protected function setUp(): void {
		$this->reminderService = $this->createMock(ReminderService::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->calendar = new NotesCalendar($this->reminderService, $l10n, 'john');
	}

	private function makeNote(int $id,
	                         ?string $reminderAt,
	                         string $title = 'Pay the rent',
	                         string $content = 'Before it is due'): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId('john');
		$note->setTitle($title);
		$note->setContent($content);
		$note->setTimestamp(1785500000);
		$note->setColorId(1);
		$note->setReminderAt($reminderAt);
		return $note;
	}

	private function withNotes(array $notes): void {
		$this->reminderService->method('findNotesWithRemindersOf')
			->with('john')
			->willReturn($notes);
	}

	private function uids(array $events): array {
		return array_map(fn($event) => (string)$event->UID, $events);
	}

	// metadata --------------------------------------------------------------

	public function testIsReadOnly(): void {
		$this->assertSame(Constants::PERMISSION_READ, $this->calendar->getPermissions());
	}

	public function testKeyCarriesTheUserSoItIsUniqueOnTheInstance(): void {
		$this->assertSame('quicknotes-john', $this->calendar->getKey());
		$this->assertSame('quicknotes', $this->calendar->getUri());
	}

	public function testIsNeverInTheTrash(): void {
		$this->assertFalse($this->calendar->isDeleted());
	}

	// the events ------------------------------------------------------------

	public function testBuildsOneEventPerNote(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
		]);

		$events = $this->calendar->search('');

		$this->assertSame(['quicknotes-note-1', 'quicknotes-note-2'], $this->uids($events));
	}

	public function testEventSpansTheReminderInUtc(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00')]);

		$event = $this->calendar->search('')[0];

		$this->assertSame('20260801T090000Z', $event->DTSTART->getValue());
		$this->assertSame('20260801T093000Z', $event->DTEND->getValue());
	}

	/**
	 * Titles and bodies hold the rich text of the editor, and an ICS property
	 * is plain text.
	 */
	public function testStripsMarkup(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00', '<b>Pay</b> the rent', '<p>Before it is due</p>')]);

		$event = $this->calendar->search('')[0];

		$this->assertSame('Pay the rent', $event->SUMMARY->getValue());
		$this->assertSame('Before it is due', $event->DESCRIPTION->getValue());
	}

	public function testUntitledNoteStillGetsASummary(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00', '', '')]);

		$event = $this->calendar->search('')[0];

		$this->assertSame('Untitled note', $event->SUMMARY->getValue());
		$this->assertNull($event->DESCRIPTION);
	}

	/**
	 * A CalDAV client would fire its own alarm on top of the notification
	 * quicknotes already sends, so these events carry none.
	 */
	public function testCarriesNoAlarm(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00')]);

		$this->assertNull($this->calendar->search('')[0]->VALARM);
	}

	public function testSkipsARowWhoseDateCannotBeRead(): void {
		$this->withNotes([
			$this->makeNote(1, 'not a date'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
		]);

		$this->assertSame(['quicknotes-note-2'], $this->uids($this->calendar->search('')));
	}

	// filtering -------------------------------------------------------------

	public function testFiltersByTimerange(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-09-05 18:30:00'),
		]);

		$events = $this->calendar->search('', [], ['timerange' => [
			'start' => new \DateTimeImmutable('2026-08-01 00:00:00'),
			'end' => new \DateTimeImmutable('2026-08-31 23:59:59'),
		]]);

		$this->assertSame(['quicknotes-note-1'], $this->uids($events));
	}

	public function testAnOpenEndedTimerangeOnlyBoundsOneSide(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-09-05 18:30:00'),
		]);

		$events = $this->calendar->search('', [], ['timerange' => [
			'start' => new \DateTimeImmutable('2026-09-01 00:00:00'),
		]]);

		$this->assertSame(['quicknotes-note-2'], $this->uids($events));
	}

	public function testOnlyAnswersQueriesForEvents(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00')]);

		$this->assertCount(1, $this->calendar->search('', [], ['types' => ['VEVENT']]));
		$this->assertCount(0, $this->calendar->search('', [], ['types' => ['VTODO']]));
		// No types at all means no restriction.
		$this->assertCount(1, $this->calendar->search('', [], []));
	}

	public function testFindsByUidOption(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
		]);

		$events = $this->calendar->search('', [], ['uid' => 'quicknotes-note-2']);

		$this->assertSame(['quicknotes-note-2'], $this->uids($events));
	}

	public function testFindsByUidAsSearchProperty(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
		]);

		$events = $this->calendar->search('quicknotes-note-2', ['UID']);

		$this->assertSame(['quicknotes-note-2'], $this->uids($events));
	}

	/**
	 * AppCalendar::getChild() of the dav app resolves a single event this way,
	 * and it tries it *before* falling back to the uid. Answering it with
	 * everything makes every .ics url of the calendar serve the whole
	 * calendar, since the wrapper takes the first non-empty result.
	 */
	public function testFindsByFilenameSoOneUrlIsOneEvent(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
		]);

		$events = $this->calendar->search('quicknotes-note-2.ics', ['X-FILENAME']);

		$this->assertSame(['quicknotes-note-2'], $this->uids($events));
	}

	public function testAnUnknownFilenameFindsNothing(): void {
		$this->withNotes([$this->makeNote(1, '2026-08-01 09:00:00')]);

		$this->assertCount(0, $this->calendar->search('quicknotes-note-99.ics', ['X-FILENAME']));
	}

	public function testMatchesFreeTextOnTitleAndBody(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00', 'Pay the rent', 'before it is due'),
			$this->makeNote(2, '2026-08-05 18:30:00', 'Buy milk', 'and bread'),
		]);

		$this->assertSame(['quicknotes-note-1'], $this->uids($this->calendar->search('rent')));
		$this->assertSame(['quicknotes-note-2'], $this->uids($this->calendar->search('bread')));
		// Case insensitive, like the rest of the calendar search.
		$this->assertSame(['quicknotes-note-2'], $this->uids($this->calendar->search('MILK')));
		$this->assertCount(0, $this->calendar->search('mortgage'));
	}

	public function testRestrictsFreeTextToTheGivenProperties(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00', 'Pay the rent', 'before it is due'),
		]);

		$this->assertCount(1, $this->calendar->search('rent', ['SUMMARY']));
		$this->assertCount(0, $this->calendar->search('due', ['SUMMARY']));
		$this->assertCount(1, $this->calendar->search('due', ['DESCRIPTION']));
	}

	public function testPaginates(): void {
		$this->withNotes([
			$this->makeNote(1, '2026-08-01 09:00:00'),
			$this->makeNote(2, '2026-08-05 18:30:00'),
			$this->makeNote(3, '2026-08-09 08:00:00'),
		]);

		$this->assertSame(['quicknotes-note-1', 'quicknotes-note-2'],
			$this->uids($this->calendar->search('', [], [], 2)));
		$this->assertSame(['quicknotes-note-3'],
			$this->uids($this->calendar->search('', [], [], null, 2)));
		$this->assertSame(['quicknotes-note-2'],
			$this->uids($this->calendar->search('', [], [], 1, 1)));
	}

}
