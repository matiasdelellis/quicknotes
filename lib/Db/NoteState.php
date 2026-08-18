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

use OCP\AppFramework\Db\Entity;

/**
 * What one user made of one note, as opposed to what the note *is*.
 *
 * A note has a single title, a single content and a single colour, and
 * everybody who can see it sees the same ones. Whether it sits at the top of
 * the grid is not like that: it is a decision of whoever is looking, and a
 * shared note that the owner pinned has no business being pinned for the
 * fifteen other people it was shared with.
 *
 * Tags were already personal — `quicknotes_note_tags` carries a `user_id` and
 * always did. Pinning moved here in 0.9.1, the reminder in 0.9.2 and archiving
 * in 0.9.3, for the same reason: a reminder is somebody deciding when *they* want to be
 * interrupted, and there is no sense in which the owner of a note gets to
 * decide that for the fifteen people it was shared with. Archiving is the same
 * kind of thing said about the grid: "get this out of my list".
 *
 * The trash is deliberately *not* here. `deleted_at` stays on the note,
 * because it is about whether the note goes on existing at all — which is the
 * owner's to decide, and everybody else's to live with.
 *
 * One reminder per note and user — the unique index on (note_id, user_id) says
 * so. Several reminders on the same note by the same person would need a table
 * of their own; this is the shape the app offers.
 *
 * A row only exists once a user pinned or armed something, and goes away again
 * when both are gone: no row means no pin and no reminder.
 *
 * `reminderAt` and `reminderNotifiedAt` are left without an `addType()` on
 * purpose, the same convention the note columns followed, so QBMapper hands
 * back the raw UTC 'Y-m-d H:i:s' string of the column untouched.
 *
 * @method int getNoteId()
 * @method void setNoteId(int $noteId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method bool getPinned()
 * @method void setPinned(bool $pinned)
 * @method string|null getReminderAt()
 * @method void setReminderAt(string|null $reminderAt)
 * @method string|null getReminderNotifiedAt()
 * @method void setReminderNotifiedAt(string|null $reminderNotifiedAt)
 * @method string|null getArchivedAt()
 * @method void setArchivedAt(string|null $archivedAt)
 */
class NoteState extends Entity {

	protected $noteId;
	protected $userId;
	protected $pinned;
	protected $reminderAt;
	protected $reminderNotifiedAt;
	protected $archivedAt;

	public function __construct() {
		$this->addType('noteId', 'integer');
		$this->addType('pinned', 'boolean');
	}

	/**
	 * Whether this row still says anything. One that does not is deleted
	 * rather than kept around saying nothing.
	 */
	public function isEmpty(): bool {
		return !$this->getPinned()
		    && is_null($this->reminderAt)
		    && is_null($this->archivedAt);
	}

}
