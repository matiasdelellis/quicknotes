<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Attach extends Entity implements JsonSerializable {
	protected $userId;
	protected $noteId;
	protected $fileId;
	protected $createdAt;
	protected $previewUrl;

	public function setPreviewUrl($previewUrl) {
		$this->previewUrl = $previewUrl;
	}

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'note_id' => $this->noteId,
			'file_id' => $this->fileId,
			'created_at' => $this->createdAt,
			'preview_url' => $this->previewUrl,
		];
	}
}
