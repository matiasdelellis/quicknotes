<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getContent()
 * @method void setContest(string $content)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 * @method int geColorId()
 * @method void setColorId(int $colorId)
 * @method bool getPinned()
 * @method void setPinned(bool $pinned)
 */
class Note extends Entity implements JsonSerializable {

	// Db Entity
	protected $userId;
	protected $title;
	protected $content;
	protected $timestamp;
	protected $colorId;
	protected $pinned;

	// Extra info to API
	protected $color;
	protected $isPinned;
	protected $sharedWith = [];
	protected $sharedBy = [];
	protected $tags = [];
	protected $attachts = [];

	public function __construct() {
		$this->addType('pinned', 'boolean');
	}

	public function setColor(string $color): void {
		$this->color = $color;
	}

	public function setIsPinned(bool $pinned): void {
		$this->isPinned = $pinned;
	}

	public function setSharedWith(array $sharedWith): void {
		$this->sharedWith = $sharedWith;
	}

	public function setSharedBy(array $sharedBy): void {
		$this->sharedBy = $sharedBy;
	}

	public function setTags(array $tags) {
		$this->tags = $tags;
	}

	public function jsonSerialize() {
		return [
			'id'          => $this->id,
			'title'       => $this->title,
			'content'     => $this->content,
			'isPinned'    => $this->isPinned,
			'timestamp'   => $this->timestamp,
			'color'       => $this->color,
			'sharedWith'  => $this->sharedWith,
			'sharedBy'    => $this->sharedBy,
			'tags'        => $this->tags,
			'attachments' => $this->attachts
		];
	}
}
