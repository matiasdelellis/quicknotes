<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getNoteId()
 * @method void setNoteId(int $noteId)
 * @method int getTagId()
 * @method void setTagId(int $tagId)
 */
class NoteTag extends Entity implements JsonSerializable {

	protected $noteId;
	protected $tagId;
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