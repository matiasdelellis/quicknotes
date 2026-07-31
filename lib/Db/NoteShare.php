<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getNoteId()
 * @method void setNoteId(int $noteId)
 * @method string getSharedUser()
 * @method void setSharedUser(string $userId)
 * @method string getSharedGroup()
 * @method void setSharedGroup(string $groupId)
 */
class NoteShare extends Entity implements JsonSerializable {

	protected $noteId;
	protected $sharedUser;
	protected $sharedGroup;

	protected $userId;
	// Not a column: filled in from the user manager when the note is
	// hydrated. It used to be declared as `displayname`, which made the
	// setter below create a dynamic property — deprecated since PHP 8.2.
	protected $displayName;

	public function setUserId (string $userId): void {
		$this->userId = $userId;
	}

	public function setDisplayName (string $displayName): void {
		$this->displayName = $displayName;
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'note_id' => $this->noteId,
			'shared_user' => $this->sharedUser,
			'shared_group' => $this->sharedGroup,
			'user_id' => $this->userId,
			'display_name' => $this->displayName
		];
	}

}
