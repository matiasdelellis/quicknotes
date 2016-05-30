<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Share extends Entity implements JsonSerializable {

	protected $noteId;
	protected $sharedUser;
	protected $sharedGroup;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'note' => $this->noteId,
			'user' => $this->sharedUser,
			'group' => $this->sharedGroup
		];
	}
}
