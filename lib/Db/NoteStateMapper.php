<?php
/*
 * @copyright 2026 Matias De lellis <mati86dl@gmail.com>
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

namespace OCA\QuickNotes\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;

use OCA\QuickNotes\Db\NoteState;

/**
 * @method NoteState insert(NoteState $state)
 * @method NoteState update(NoteState $state)
 * @method NoteState delete(NoteState $state)
 */
class NoteStateMapper extends QBMapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'quicknotes_note_states', NoteState::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function find(string $userId, int $noteId): NoteState {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	/**
	 * Everything this user made of anything, keyed by note id.
	 *
	 * `getAll()` needs the pin, the reminder and the archive state of every
	 * note it returns; they all live on the same row, so one query answers all
	 * three for the whole list.
	 *
	 * @return array<int, NoteState>
	 */
	public function findAllForUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		$states = [];
		foreach ($this->findEntities($qb) as $state) {
			$states[(int)$state->getNoteId()] = $state;
		}

		return $states;
	}

	/**
	 * Pin or unpin a note for one user.
	 */
	public function setPinned(string $userId, int $noteId, bool $pinned): void {
		$this->write($userId, $noteId, function (NoteState $state) use ($pinned) {
			$state->setPinned($pinned);
		}, $pinned);
	}

	/**
	 * Archive or unarchive a note for one user.
	 *
	 * Personal since 0.9.3: it says where the note sits in *this* user's grid,
	 * so the owner tidying up does not tidy up for the people it was shared
	 * with — and, more to the point, somebody who was given a note through a
	 * group share can get it out of their list even though the share is not
	 * theirs to leave.
	 *
	 * @param string|null $archivedAt UTC 'Y-m-d H:i:s', or null to unarchive
	 */
	public function setArchived(string $userId, int $noteId, ?string $archivedAt): void {
		$this->write($userId, $noteId, function (NoteState $state) use ($archivedAt) {
			$state->setArchivedAt($archivedAt);
		}, !is_null($archivedAt));
	}

	/**
	 * Arm, move or cancel the reminder of one user on one note.
	 *
	 * Writing a reminder always clears `reminder_notified_at`, so moving one
	 * that already fired arms it again instead of staying silent. Pass null to
	 * cancel.
	 */
	public function setReminder(string $userId, int $noteId, ?string $reminderAt): void {
		$this->write($userId, $noteId, function (NoteState $state) use ($reminderAt) {
			$state->setReminderAt($reminderAt);
			$state->setReminderNotifiedAt(null);
		}, !is_null($reminderAt));
	}

	/**
	 * Record that the notification for a reminder went out.
	 */
	public function markReminderNotified(NoteState $state, \DateTime $when): void {
		$state->setReminderNotifiedAt($when->format('Y-m-d H:i:s'));
		$this->update($state);
	}

	/**
	 * Every reminder of one user, whether it already fired or not, ordered by
	 * date. Feeds the virtual calendar, which wants them in that order.
	 *
	 * @return NoteState[]
	 */
	public function findRemindersOf(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNotNull('reminder_at')
			)
			->orderBy('reminder_at', 'ASC');
		return $this->findEntities($qb);
	}

	/**
	 * Everybody who armed a reminder on a note. Called when the note stops
	 * being something to be reminded about — the trash, or deletion — so that
	 * the notifications already sitting in their lists can be withdrawn.
	 *
	 * @return NoteState[]
	 */
	public function findRemindersForNote(int $noteId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where(
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->isNotNull('reminder_at')
			);
		return $this->findEntities($qb);
	}

	/**
	 * The reminders that have fallen due and have not been notified yet.
	 *
	 * Joined against the notes so that a note in the trash is skipped, the way
	 * it was skipped when the columns lived on the note itself: sending to the
	 * trash cancels the reminders of everybody. Archived notes are *not*
	 * skipped — archiving takes a note out of the active list, it is not a way
	 * to cancel a reminder.
	 *
	 * @return NoteState[]
	 */
	public function findDueReminders(\DateTime $now): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('s.*')
			->from($this->getTableName(), 's')
			->innerJoin('s', 'quicknotes_notes', 'n', $qb->expr()->eq('s.note_id', 'n.id'))
			->where(
				$qb->expr()->isNotNull('s.reminder_at'),
				$qb->expr()->isNull('s.reminder_notified_at'),
				$qb->expr()->isNull('n.deleted_at'),
				$qb->expr()->lte('s.reminder_at', $qb->createNamedParameter(
					$now->format('Y-m-d H:i:s'),
					IQueryBuilder::PARAM_STR
				))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Change one thing about what a user made of a note, creating the row if
	 * this is the first thing they make of it and dropping it again once it
	 * says nothing at all.
	 *
	 * @param callable $change applied to the row
	 * @param bool $creates whether the change is worth creating a row for
	 */
	private function write(string $userId, int $noteId, callable $change, bool $creates): void {
		try {
			$state = $this->find($userId, $noteId);
		} catch (DoesNotExistException $e) {
			if (!$creates) {
				return;
			}
			$state = new NoteState();
			$state->setUserId($userId);
			$state->setNoteId($noteId);
			$state->setPinned(false);
			$change($state);
			$this->insert($state);
			return;
		}

		$change($state);

		if ($state->isEmpty()) {
			$this->delete($state);
			return;
		}

		$this->update($state);
	}

	/**
	 * Forget everything every user made of a note. Called when the note goes.
	 */
	public function deleteByNoteId(int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	/**
	 * Forget what one user made of a note. Called when they leave a share:
	 * the note is not theirs to keep pinned any more.
	 */
	public function deleteForUser(string $userId, int $noteId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where(
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('note_id', $qb->createNamedParameter($noteId, IQueryBuilder::PARAM_INT))
			)
			->executeStatement();
	}

}
