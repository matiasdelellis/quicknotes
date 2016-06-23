<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Note extends Entity implements JsonSerializable {

	protected $title;
	protected $content;
	protected $timestamp;
	protected $colorId;
	protected $userId;
	protected $sharedWith;
	protected $isShared;

	protected $color;

	public function setColor($color) {
		$this->color = $color;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content,
			'timestamp' => $this->timestamp,
			'colorid' => $this->colorId,
			'color' => $this->color,
			'userid' => $this->userId,
			'sharedwith' => $this->sharedWith,
			'isshared' => $this->isShared
		];
	}
}
