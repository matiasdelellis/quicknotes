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

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'note_id' => $this->noteId,
			'shared_user' => $this->sharedUser,
			'shared_group' => $this->sharedGroup
		];
	}

}
