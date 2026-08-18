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

namespace OCA\QuickNotes\Service;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteState;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Notification\Notifier;


class ReminderServiceTest extends TestCase {

	private $service;
	private $noteMapper;
	private $noteStateMapper;
	private $shareService;
	private $notificationManager;
	private $timeFactory;
	private $logger;

	/** Fixed "now" so the tests do not depend on the wall clock. */
	private $now;

	protected function setUp(): void {
		$this->noteMapper          = $this->createMock(NoteMapper::class);
		$this->noteStateMapper     = $this->createMock(NoteStateMapper::class);
		$this->shareService        = $this->createMock(ShareService::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->timeFactory         = $this->createMock(ITimeFactory::class);
		$this->logger              = $this->createMock(LoggerInterface::class);

		$this->now = new \DateTime('2026-07-31 12:00:00', new \DateTimeZone('GMT'));
		$this->timeFactory->method('getDateTime')->willReturn($this->now);

		$this->service = new ReminderService(
			$this->noteMapper,
			$this->noteStateMapper,
			$this->shareService,
			$this->notificationManager,
			$this->timeFactory,
			$this->logger
		);
	}

	private function makeNote(int $id = 1,
	                         string $title = 'Pay the rent',
	                         string $owner = 'john'): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($owner);
		$note->setTitle($title);
		$note->setContent('A content');
		$note->setTimestamp(1700000000);
		$note->setColorId(1);
		return $note;
	}

	private function makeState(int $noteId = 1,
	                           string $userId = 'john',
	                           ?string $reminderAt = '2026-07-31 11:55:00'): NoteState {
		$state = new NoteState();
		$state->setNoteId($noteId);
		$state->setUserId($userId);
		$state->setPinned(false);
		$state->setReminderAt($reminderAt);
		return $state;
	}

	/** A notification that accepts the whole builder chain. */
	private function makeNotification(): INotification {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		return $notification;
	}

	// normalize -------------------------------------------------------------

	public function testNormalizeAcceptsUtcDatetime(): void {
		$this->assertSame(
			'2026-08-01 09:00:00',
			$this->service->normalize('2026-08-01 09:00:00')
		);
	}

	public function testNormalizeTreatsNullAndEmptyAsCancel(): void {
		$this->assertNull($this->service->normalize(null));
		$this->assertNull($this->service->normalize(''));
	}

	/**
	 * createFromFormat() rolls impossible dates over instead of failing, so
	 * these only get rejected by the re-format comparison.
	 */
	public function testNormalizeRejectsRolledOverDate(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->normalize('2026-13-45 99:99:99');
	}

	public function testNormalizeRejectsOtherFormats(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->normalize('2026-08-01T09:00:00Z');
	}

	public function testNormalizeRejectsGarbage(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->service->normalize('tomorrow-ish');
	}

	// notifyDue -------------------------------------------------------------

	public function testNotifyDueDoesNothingWithoutDueReminders(): void {
		$this->noteStateMapper->expects($this->once())
			->method('findDueReminders')
			->with($this->now)
			->willReturn([]);

		$this->notificationManager->expects($this->never())->method('notify');

		$this->assertSame(0, $this->service->notifyDue());
	}

	public function testNotifyDueNotifiesAndMarksTheReminder(): void {
		$state = $this->makeState(7);

		$this->noteStateMapper->method('findDueReminders')->willReturn([$state]);
		$this->noteMapper->method('findByIds')->with([7])->willReturn([$this->makeNote(7)]);

		$notification = $this->makeNotification();
		$notification->expects($this->once())
			->method('setObject')
			->with(Notifier::OBJECT_NOTE, '7');
		$notification->expects($this->once())
			->method('setSubject')
			->with(Notifier::SUBJECT_REMINDER, ['title' => 'Pay the rent']);

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->noteStateMapper->expects($this->once())
			->method('markReminderNotified')
			->with($state, $this->now);

		$this->assertSame(1, $this->service->notifyDue());
	}

	/**
	 * The reminder is the recipient's, not the owner's: the notification goes
	 * to whoever armed it, on somebody else's note.
	 */
	public function testNotifyDueNotifiesWhoeverArmedTheReminder(): void {
		$state = $this->makeState(7, 'bob');

		$this->noteStateMapper->method('findDueReminders')->willReturn([$state]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote(7, 'Pay the rent', 'alice')]);
		$this->shareService->method('getPermissions')->willReturn(NoteShare::PERMISSION_READ);

		$notification = $this->makeNotification();
		$notification->expects($this->once())->method('setUser')->with('bob');
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->assertSame(1, $this->service->notifyDue());
	}

	/**
	 * Access can be lost after a reminder is armed — a group share taken back,
	 * or leaving the group. Reminding somebody of a note they can no longer
	 * open is worse than not reminding them, so it is dropped instead.
	 */
	public function testNotifyDueDropsAReminderOnANoteTheUserLostAccessTo(): void {
		$state = $this->makeState(7, 'bob');

		$this->noteStateMapper->method('findDueReminders')->willReturn([$state]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote(7, 'Pay the rent', 'alice')]);
		$this->shareService->method('getPermissions')->willReturn(0);

		$this->notificationManager->expects($this->never())->method('notify');
		$this->noteStateMapper->expects($this->once())
			->method('setReminder')
			->with('bob', 7, null);

		$this->assertSame(0, $this->service->notifyDue());
	}

	/** The owner never has to be checked against the shares. */
	public function testNotifyDueDoesNotAskAboutTheOwner(): void {
		$this->noteStateMapper->method('findDueReminders')->willReturn([$this->makeState(7, 'john')]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote(7, 'Pay the rent', 'john')]);

		$this->shareService->expects($this->never())->method('getPermissions');
		$this->notificationManager->method('createNotification')->willReturn($this->makeNotification());

		$this->assertSame(1, $this->service->notifyDue());
	}

	/**
	 * The title is rich text in the database, and it has to reach the
	 * notification as plain text.
	 */
	public function testNotifyDueStripsMarkupFromTheTitle(): void {
		$this->noteStateMapper->method('findDueReminders')->willReturn([$this->makeState(7)]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote(7, '<b>Pay</b> the&nbsp;rent')]);

		$notification = $this->makeNotification();
		$notification->expects($this->once())
			->method('setSubject')
			->with(Notifier::SUBJECT_REMINDER, ['title' => 'Pay the&nbsp;rent']);

		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->service->notifyDue();
	}

	/**
	 * A reminder that cannot be notified must not be marked, so the next run
	 * retries it — and must not take the rest of the batch down with it.
	 */
	public function testNotifyDueKeepsGoingAfterAFailure(): void {
		$second = $this->makeState(2);

		$this->noteStateMapper->method('findDueReminders')
			->willReturn([$this->makeState(1), $second]);
		$this->noteMapper->method('findByIds')
			->willReturn([$this->makeNote(1), $this->makeNote(2)]);

		$this->notificationManager->method('createNotification')->willReturn($this->makeNotification());
		$this->notificationManager->method('notify')
			->willReturnOnConsecutiveCalls(
				$this->throwException(new \RuntimeException('push is down')),
				null
			);

		// Only the second one gets marked.
		$this->noteStateMapper->expects($this->once())
			->method('markReminderNotified')
			->with($second, $this->now);

		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(1, $this->service->notifyDue());
	}

	/** A reminder whose note vanished under it is skipped, not fatal. */
	public function testNotifyDueSkipsAReminderWithoutANote(): void {
		$this->noteStateMapper->method('findDueReminders')->willReturn([$this->makeState(7)]);
		$this->noteMapper->method('findByIds')->willReturn([]);

		$this->notificationManager->expects($this->never())->method('notify');

		$this->assertSame(0, $this->service->notifyDue());
	}

	public function testNotifyDueFlushesWhenItDeferred(): void {
		$this->noteStateMapper->method('findDueReminders')->willReturn([$this->makeState()]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote()]);
		$this->notificationManager->method('createNotification')->willReturn($this->makeNotification());

		$this->notificationManager->method('defer')->willReturn(true);
		$this->notificationManager->expects($this->once())->method('flush');

		$this->service->notifyDue();
	}

	public function testNotifyDueDoesNotFlushWhenAlreadyDeferred(): void {
		$this->noteStateMapper->method('findDueReminders')->willReturn([$this->makeState()]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote()]);
		$this->notificationManager->method('createNotification')->willReturn($this->makeNotification());

		// Somebody up the stack is already batching: flushing here would
		// cut their batch short.
		$this->notificationManager->method('defer')->willReturn(false);
		$this->notificationManager->expects($this->never())->method('flush');

		$this->service->notifyDue();
	}

	// dismiss ---------------------------------------------------------------

	public function testDismissMarksTheNotificationProcessed(): void {
		$notification = $this->makeNotification();

		$notification->expects($this->once())->method('setUser')->with('john');
		$notification->expects($this->once())
			->method('setObject')
			->with(Notifier::OBJECT_NOTE, '42');
		// Scoped to the reminder: the same user can also hold a "shared with
		// you" notification about this very note, and it is not ours to drop.
		$notification->expects($this->once())
			->method('setSubject')
			->with(Notifier::SUBJECT_REMINDER);

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$this->service->dismiss('john', 42);
	}

	public function testDismissForNoteReachesEverybodyWhoArmedOne(): void {
		$this->noteStateMapper->method('findRemindersForNote')
			->with(42)
			->willReturn([$this->makeState(42, 'john'), $this->makeState(42, 'bob')]);

		$notification = $this->makeNotification();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$users = [];
		$notification->method('setUser')->willReturnCallback(function ($user) use (&$users, $notification) {
			$users[] = $user;
			return $notification;
		});

		$this->notificationManager->expects($this->exactly(2))->method('markProcessed');

		$this->service->dismissForNote(42);

		$this->assertSame(['john', 'bob'], $users);
	}

	// the calendar ----------------------------------------------------------

	public function testNotesWithRemindersCarryTheDateOfTheUserAsking(): void {
		$this->noteStateMapper->method('findRemindersOf')
			->with('bob')
			->willReturn([$this->makeState(7, 'bob', '2026-08-02 08:00:00')]);
		$this->noteMapper->method('findByIds')->willReturn([$this->makeNote(7, 'Pay the rent', 'alice')]);

		$notes = $this->service->findNotesWithRemindersOf('bob');

		$this->assertCount(1, $notes);
		$this->assertSame('2026-08-02 08:00:00', $notes[0]->getReminderAt());
	}

	public function testNotesWithRemindersSkipsTheTrash(): void {
		$trashed = $this->makeNote(7);
		$trashed->setDeletedAt('2026-07-30 10:00:00');

		$this->noteStateMapper->method('findRemindersOf')->willReturn([$this->makeState(7)]);
		$this->noteMapper->method('findByIds')->willReturn([$trashed]);

		$this->assertSame([], $this->service->findNotesWithRemindersOf('john'));
	}

}
