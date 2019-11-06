<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class NoteTag extends Entity implements JsonSerializable {

	protected $noteId;
	protected $tagId
	protected $userId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'noteid' => $this->noteId,
			'tagid' => $this->tagId,
			'userid' => $this->userId
		];
	}

}