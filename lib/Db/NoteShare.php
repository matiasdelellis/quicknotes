<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class NoteShare extends Entity implements JsonSerializable {

	protected $noteId;
	protected $sharedUser;
	protected $sharedGroup;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'noteid' => $this->noteId,
			'shareduser' => $this->sharedUser,
			'sharedgroup' => $this->sharedGroup
		];
	}
}
