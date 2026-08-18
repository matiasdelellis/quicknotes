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

use OCA\QuickNotes\Db\AttachMapper;
use OCA\QuickNotes\Db\ColorMapper;
use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Db\NoteStateMapper;
use OCA\QuickNotes\Db\NoteTagMapper;
use OCA\QuickNotes\Db\TagMapper;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;

use PHPUnit\Framework\TestCase;

/**
 * Emptying the trash.
 *
 * It is the one action of the app that destroys more than one note at a time,
 * so what matters is that each of them goes through the same door as "Delete
 * permanently" — shares, tags, personal state and attachments included — and
 * that nothing outside the trash is touched.
 */
class NoteServiceTest extends TestCase {

	private $noteService;
	private $noteMapper;
	private $noteTagMapper;
	private $noteStateMapper;
	private $colorMapper;
	private $attachMapper;
	private $tagMapper;
	private $reminderService;
	private $shareService;
	private $userId = 'john';

	protected function setUp(): void {
		$this->noteMapper      = $this->createMock(NoteMapper::class);
		$this->noteTagMapper   = $this->createMock(NoteTagMapper::class);
		$this->noteStateMapper = $this->createMock(NoteStateMapper::class);
		$this->colorMapper     = $this->createMock(ColorMapper::class);
		$this->attachMapper    = $this->createMock(AttachMapper::class);
		$this->tagMapper       = $this->createMock(TagMapper::class);
		$this->reminderService = $this->createMock(ReminderService::class);
		$this->shareService    = $this->createMock(ShareService::class);

		$this->noteService = new NoteService(
			$this->noteMapper,
			$this->noteTagMapper,
			$this->noteStateMapper,
			$this->colorMapper,
			$this->attachMapper,
			$this->tagMapper,
			$this->createMock(FileService::class),
			$this->createMock(SettingsService::class),
			$this->reminderService,
			$this->shareService,
			$this->createMock(IUserManager::class)
		);

		// The colour of every note in these tests is still in use by another
		// one, so no colour is pruned and the ColorMapper is never asked.
		$this->noteMapper->method('colorIdCount')->willReturn(1);
		$this->colorMapper->expects($this->never())->method('delete');
	}

	private function makeNote(int $id): Note {
		$note = new Note();
		$note->setId($id);
		$note->setUserId($this->userId);
		$note->setTitle('note ' . $id);
		$note->setContent('a content');
		$note->setColorId(1);
		return $note;
	}

	public function testEmptyTrashDestroysEveryNoteInIt(): void {
		$notes = [$this->makeNote(1), $this->makeNote(2), $this->makeNote(3)];

		$this->noteMapper->expects($this->once())
			->method('findDeletedByUser')
			->with($this->userId)
			->willReturn($notes);
		$this->noteMapper->method('find')
			->willReturnCallback(function (int $id, string $userId) use ($notes) {
				$this->assertSame($this->userId, $userId);
				foreach ($notes as $note) {
					if ($note->getId() === $id) {
						return $note;
					}
				}
				throw new DoesNotExistException('no such note');
			});

		$this->noteMapper->expects($this->exactly(3))->method('delete');

		$this->assertSame(3, $this->noteService->emptyTrash($this->userId));
	}

	/** Everything that hangs off a note goes with it, note by note. */
	public function testEmptyTrashTakesTheSharesTagsStateAndAttachmentsWithIt(): void {
		$note = $this->makeNote(7);

		$this->noteMapper->method('findDeletedByUser')->willReturn([$note]);
		$this->noteMapper->method('find')->willReturn($note);

		$this->shareService->expects($this->once())->method('deleteForNote')->with(7);
		$this->reminderService->expects($this->once())->method('dismissForNote')->with(7);
		$this->noteTagMapper->expects($this->once())->method('deleteByNoteId')->with(7);
		$this->noteStateMapper->expects($this->once())->method('deleteByNoteId')->with(7);
		$this->attachMapper->expects($this->once())->method('deleteByNoteId')->with(7);

		$this->assertSame(1, $this->noteService->emptyTrash($this->userId));
	}

	/** An empty trash is not an error, and nothing is destroyed. */
	public function testEmptyTrashOnAnEmptyTrash(): void {
		$this->noteMapper->method('findDeletedByUser')->willReturn([]);

		$this->noteMapper->expects($this->never())->method('delete');
		$this->shareService->expects($this->never())->method('deleteForNote');

		$this->assertSame(0, $this->noteService->emptyTrash($this->userId));
	}

	/**
	 * A note that is gone by the time its turn comes — the retention job got
	 * there first, or another tab did — is not counted, and does not take the
	 * rest of the trash down with it.
	 */
	public function testEmptyTrashSkipsWhatIsAlreadyGone(): void {
		$notes = [$this->makeNote(1), $this->makeNote(2)];

		$this->noteMapper->method('findDeletedByUser')->willReturn($notes);
		$this->noteMapper->method('find')
			->willReturnCallback(function (int $id) use ($notes) {
				if ($id === 1) {
					throw new DoesNotExistException('purged in the meantime');
				}
				return $notes[1];
			});

		$this->noteMapper->expects($this->once())->method('delete');

		$this->assertSame(1, $this->noteService->emptyTrash($this->userId));
	}

}
