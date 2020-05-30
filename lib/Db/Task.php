<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Note extends Entity implements JsonSerializable {

	protected $description;
	protected $done;
	protected $ordering;
	protected $noteId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'description' => $this->description,
			'done' => $this->done,
			'ordering' => $this->ordering,
			'noteId' => $this->noteId
		];
	}
}