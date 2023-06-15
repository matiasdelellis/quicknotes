<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getName()
 * @method void setName(string $name)
 */
class Tag extends Entity implements JsonSerializable {

	protected $userId;
	protected $name;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'userid' => $this->userId,
			'name' => $this->name
		];
	}

}