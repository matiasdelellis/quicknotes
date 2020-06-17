<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class NoteShare extends Entity implements JsonSerializable {

	protected $noteId;
	protected $userId;
	protected $sharedUser;
	protected $sharedGroup;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'user_id' => $this->userId,
			'note_id' => $this->noteId,
			'shared_user' => $this->sharedUser,
			'shared_group' => $this->sharedGroup
		];
	}

}
