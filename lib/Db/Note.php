<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Note extends Entity implements JsonSerializable {

	protected $title;
	protected $content;
	protected $pinned;
	protected $timestamp;
	protected $colorId;
	protected $userId;
	protected $sharedWith;
	protected $isShared;
	protected $tags;
	protected $attachts;

	protected $color;
	protected $isPinned;

	public function setColor($color) {
		$this->color = $color;
	}

	public function setIsPinned($pinned) {
		$this->isPinned = $pinned;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content,
			'pinned' => $this->pinned,
			'ispinned' => $this->isPinned,
			'timestamp' => $this->timestamp,
			'colorid' => $this->colorId,
			'color' => $this->color,
			'userid' => $this->userId,
			'sharedwith' => $this->sharedWith,
			'isshared' => $this->isShared,
			'tags' => $this->tags,
			'attachts' => $this->attachts
		];
	}
}
