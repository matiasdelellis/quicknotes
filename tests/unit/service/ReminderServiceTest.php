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
use OCA\QuickNotes\Notification\Notifier;


class ReminderServiceTest extends TestCase {

	private $service;
	private $noteMapper;
	private $notificationManager;
	private $timeFactory;
	private $logger;

	/** Fixed "now" so the tests do not depend on the wall clock. */
	private $now;

	protected function setUp(): void {
		$this->noteMapper          = $this->createMock(NoteMapper::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->timeFactory         = $this->createMock(ITimeFactory::class);
		$this->logger              = $this->createMock(LoggerInterface::class);

		$this->now = new \DateTime('2026-07-31 12:00:00', new \DateTimeZone('GMT'));
		$this->timeFactory->method('getDateTime')->willReturn($this->now);

		$this->service = new ReminderService(
			$this->noteMapper,
			$this->notificationManager,
			$this->timeFactory,
			$this->logger
		);
	}

	private function makeNote(int $id = 1,
	                         string $title = 'Pay the rent',
	                         ?string $reminderAt = '2026-07-31 11:55:00'): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId('john');
		$note->setTitle($title);
		$note->setContent('A content');
		$note->setTimestamp(1700000000);
		$note->setColorId(1);
		$note->setPinned(false);
		$note->setReminderAt($reminderAt);
		return $note;
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
		$this->noteMapper->expects($this->once())
			->method('findDueReminders')
			->with($this->now)
			->willReturn([]);

		$this->notificationManager->expects($this->never())->method('notify');

		$this->assertSame(0, $this->service->notifyDue());
	}

	public function testNotifyDueNotifiesAndMarksTheNote(): void {
		$note = $this->makeNote(7);

		$this->noteMapper->expects($this->once())
			->method('findDueReminders')
			->willReturn([$note]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();

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

		$this->noteMapper->expects($this->once())
			->method('markReminderNotified')
			->with(7, $this->now);

		$this->assertSame(1, $this->service->notifyDue());
	}

	/**
	 * The title is rich text in the database, and it has to reach the
	 * notification as plain text.
	 */
	public function testNotifyDueStripsMarkupFromTheTitle(): void {
		$note = $this->makeNote(7, '<b>Pay</b> the&nbsp;rent');

		$this->noteMapper->method('findDueReminders')->willReturn([$note]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();

		$notification->expects($this->once())
			->method('setSubject')
			->with(Notifier::SUBJECT_REMINDER, ['title' => 'Pay the&nbsp;rent']);

		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->service->notifyDue();
	}

	/**
	 * A note that cannot be notified must not be marked, so the next run
	 * retries it — and must not take the rest of the batch down with it.
	 */
	public function testNotifyDueKeepsGoingAfterAFailure(): void {
		$this->noteMapper->method('findDueReminders')
			->willReturn([$this->makeNote(1), $this->makeNote(2)]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->method('notify')
			->willReturnOnConsecutiveCalls(
				$this->throwException(new \RuntimeException('push is down')),
				null
			);

		// Only the second one gets marked.
		$this->noteMapper->expects($this->once())
			->method('markReminderNotified')
			->with(2, $this->now);

		$this->logger->expects($this->once())->method('warning');

		$this->assertSame(1, $this->service->notifyDue());
	}

	public function testNotifyDueFlushesWhenItDeferred(): void {
		$this->noteMapper->method('findDueReminders')->willReturn([$this->makeNote()]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		$this->notificationManager->method('defer')->willReturn(true);
		$this->notificationManager->expects($this->once())->method('flush');

		$this->service->notifyDue();
	}

	public function testNotifyDueDoesNotFlushWhenAlreadyDeferred(): void {
		$this->noteMapper->method('findDueReminders')->willReturn([$this->makeNote()]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);

		// Somebody up the stack is already batching: flushing here would
		// cut their batch short.
		$this->notificationManager->method('defer')->willReturn(false);
		$this->notificationManager->expects($this->never())->method('flush');

		$this->service->notifyDue();
	}

	// dismiss ---------------------------------------------------------------

	public function testDismissMarksTheNotificationProcessed(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();

		$notification->expects($this->once())->method('setUser')->with('john');
		$notification->expects($this->once())
			->method('setObject')
			->with(Notifier::OBJECT_NOTE, '42');

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('markProcessed')
			->with($notification);

		$this->service->dismiss('john', 42);
	}

}
