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
	protected $redirectUrl;
	protected $deepLinkUrl;

	public function setPreviewUrl($previewUrl) {
		$this->previewUrl = $previewUrl;
	}

	public function setRedirectUrl($redirectUrl) {
		$this->redirectUrl = $redirectUrl;
	}

	public function setDeepLinkUrl($deepLinkUrl) {
		$this->deepLinkUrl = $deepLinkUrl;
	}

	public function jsonSerialize() {
		return [
			'id'            => $this->id,
			'note_id'       => $this->noteId,
			'file_id'       => $this->fileId,
			'created_at'    => $this->createdAt,
			'preview_url'   => $this->previewUrl,
			'redirect_url'  => $this->redirectUrl,
			'deep_link_url' => $this->deepLinkUrl
		];
	}
}
