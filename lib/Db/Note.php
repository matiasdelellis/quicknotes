<?php
/*
 * @copyright 2016-2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2016 Matias De lellis <mati86dl@gmail.com>
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

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getContent()
 * @method void setContent(string $content)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method int getColorId()
 * @method void setColorId(int $colorId)
 * @method string|null getDeletedAt()
 * @method void setDeletedAt(string|null $deletedAt)
 */
class Note extends Entity implements JsonSerializable {

	// Db Entity
	protected $userId;
	protected $title;
	protected $content;
	protected $timestamp;
	protected $colorId;
	protected $deletedAt;

	// Extra info to API. None of these are columns: they are what the note
	// looks like *to one particular user*, and are filled in by
	// NoteService::hydrate() with that user in hand. Two people asking for the
	// same note get two different answers here, which is the whole point.
	protected $color;
	protected $isPinned = false;
	protected $archivedAt;
	protected $reminderAt;
	protected $reminderNotifiedAt;
	protected $sharedWith = [];
	protected $sharedBy = null;
	protected $permissions = NoteShare::PERMISSIONS_ALL;
	protected $isOwner = true;
	protected $canLeave = false;
	protected $ownerDisplayName;
	protected $tags = [];
	protected $attachts = [];

	public function __construct() {
		// deletedAt defaults to 'string' (no addType), so QBMapper leaves the
		// raw datetime string from the column untouched.
	}

	public function setColor(string $color): void {
		$this->color = $color;
	}

	public function setIsPinned(bool $pinned): void {
		$this->isPinned = $pinned;
	}

	public function getIsPinned(): bool {
		return (bool)$this->isPinned;
	}

	/**
	 * Whether whoever is looking archived this note, as the UTC
	 * 'Y-m-d H:i:s' string it is stored as. Not a column since 0.9.3:
	 * archiving says where the note sits in one user's grid, so it lives per
	 * user in `quicknotes_note_states` and is filled in by hydrate().
	 *
	 * The trash is not like this — `deletedAt` is still a column, because it
	 * is about the note existing at all.
	 */
	public function setArchivedAt(?string $archivedAt): void {
		$this->archivedAt = $archivedAt;
	}

	public function getArchivedAt(): ?string {
		return $this->archivedAt;
	}

	/**
	 * The reminder of whoever is looking, as the UTC 'Y-m-d H:i:s' string it
	 * is stored as. Not a column since 0.9.2: a reminder is somebody deciding
	 * when *they* want to be interrupted, so it lives per user in
	 * `quicknotes_note_states` and is filled in by hydrate().
	 */
	public function setReminderAt(?string $reminderAt): void {
		$this->reminderAt = $reminderAt;
	}

	public function getReminderAt(): ?string {
		return $this->reminderAt;
	}

	public function setReminderNotifiedAt(?string $reminderNotifiedAt): void {
		$this->reminderNotifiedAt = $reminderNotifiedAt;
	}

	public function getReminderNotifiedAt(): ?string {
		return $this->reminderNotifiedAt;
	}

	/**
	 * The shares of this note, as the viewer is allowed to see them: the owner
	 * and anyone who may reshare get the list, everybody else gets nothing.
	 *
	 * @param NoteShare[] $sharedWith
	 */
	public function setSharedWith(array $sharedWith): void {
		$this->sharedWith = $sharedWith;
	}

	/**
	 * Who shared this note with the viewer, or null when it is their own.
	 */
	public function setSharedBy(?array $sharedBy): void {
		$this->sharedBy = $sharedBy;
	}

	/** What the viewer is allowed to do with this note. */
	public function setPermissions(int $permissions): void {
		$this->permissions = $permissions;
	}

	public function getPermissions(): int {
		return (int)$this->permissions;
	}

	public function setIsOwner(bool $isOwner): void {
		$this->isOwner = $isOwner;
	}

	/**
	 * Whether the viewer can walk away from this note, which needs a share
	 * made with them personally: one that reaches them through a group is not
	 * theirs to drop. They can still archive it, which is the point.
	 */
	public function setCanLeave(bool $canLeave): void {
		$this->canLeave = $canLeave;
	}

	public function getCanLeave(): bool {
		return (bool)$this->canLeave;
	}

	public function getIsOwner(): bool {
		return (bool)$this->isOwner;
	}

	public function setOwnerDisplayName(string $ownerDisplayName): void {
		$this->ownerDisplayName = $ownerDisplayName;
	}

	public function setTags(array $tags): void {
		$this->tags = $tags;
	}

	public function setAttachts(array $attachts): void {
		$this->attachts = $attachts;
	}

	public function canEdit(): bool {
		return ($this->getPermissions() & NoteShare::PERMISSION_UPDATE) !== 0;
	}

	public function canReshare(): bool {
		return ($this->getPermissions() & NoteShare::PERMISSION_SHARE) !== 0;
	}

	/**
	 * A tag for the state of the note as it is stored, so a client can tell
	 * whether the note changed under it before overwriting somebody else's
	 * work. It is what the `If-Match` of an update is compared against.
	 *
	 * Derived from the row rather than from the JSON of the response: the
	 * response also carries previews, display names and the permissions of
	 * whoever asked, none of which say anything about the note itself, and all
	 * of which would make two users disagree on the tag of the same note.
	 *
	 * The timestamp alone does not do: it only has second resolution, and two
	 * people saving the same note within the same second is exactly the case
	 * this exists for.
	 */
	public function getEtag(): string {
		return md5(implode("\0", [
			(string)$this->id,
			(string)$this->timestamp,
			(string)$this->title,
			(string)$this->content,
		]));
	}

	public function jsonSerialize(): array {
		return [
			'id'          => (int)$this->id,
			'title'       => $this->title,
			'content'     => $this->content,
			'isPinned'    => $this->getIsPinned(),
			'timestamp'   => (int)$this->timestamp,
			'color'       => $this->color,
			'tags'        => $this->tags,
			'attachments' => $this->attachts,
			// Archived by whoever asked, not by the owner.
			'archivedAt'  => $this->archivedAt,
			'deletedAt'   => $this->deletedAt,
			// The reminder of whoever asked, not of the owner: two users see
			// their own dates on the same note, or none.
			'reminderAt'  => $this->reminderAt,
			// Lets the client tell a reminder that is still pending from one
			// that already fired, without a second request.
			'reminderNotifiedAt' => $this->reminderNotifiedAt,

			// Sharing, from the point of view of whoever asked.
			'owner'       => [
				'uid'         => $this->userId,
				'displayName' => $this->ownerDisplayName ?? $this->userId,
			],
			'isOwner'     => $this->getIsOwner(),
			'canLeave'    => $this->getCanLeave(),
			'permissions' => $this->getPermissions(),
			'canEdit'     => $this->canEdit(),
			'canReshare'  => $this->canReshare(),
			'sharedWith'  => $this->sharedWith,
			// Null for an own note, {uid, displayName} for one somebody
			// shared. The grid keys the "shared with me" filter off it.
			'sharedBy'    => $this->sharedBy,
			// Own note that is shared with somebody else, i.e. what the
			// "shared with others" filter looks for.
			'sharedByMe'  => $this->getIsOwner() && count($this->sharedWith) > 0,

			'etag'        => $this->getEtag(),
		];
	}
}
