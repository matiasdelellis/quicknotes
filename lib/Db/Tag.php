<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Tag extends Entity implements JsonSerializable {

	protected $name;
	protected $userId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'name' => $this->name,
			'userid' => $this->userId
		];
	}

}