<?php
namespace OCA\QuickNotes\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getNoteId()
 * @method void setNoteId(int $noteId)
 * @method int getFileId()
 * @method void setFileId(int $fileId)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 */

class Attach extends Entity implements JsonSerializable {
	protected $userId;
	protected $noteId;
	protected $fileId;
	protected $createdAt;

	// Not columns. Where the file can be seen, from the point of view of
	// whoever asked: the preview and the download go through the app, which
	// only asks whether they can see the *note*; the two Files links only
	// exist for somebody who can reach the file in their own Files.
	protected $basename;
	protected $mime;
	protected $previewUrl;
	protected $downloadUrl;
	protected $redirectUrl;
	protected $deepLinkUrl;
	protected $isMine = false;
	protected $hasPreview = false;

	/**
	 * The name and the mime type of the file, so a client can label it and
	 * decide what to do with it — `OCA.Viewer` needs both, and they are the
	 * only way of telling an image from a spreadsheet without asking the
	 * server again.
	 */
	public function setBasename(?string $basename): void {
		$this->basename = $basename;
	}

	public function setMime(?string $mime): void {
		$this->mime = $mime;
	}

	public function setPreviewUrl(?string $previewUrl): void {
		$this->previewUrl = $previewUrl;
	}

	public function setDownloadUrl(?string $downloadUrl): void {
		$this->downloadUrl = $downloadUrl;
	}

	public function setRedirectUrl(?string $redirectUrl): void {
		$this->redirectUrl = $redirectUrl;
	}

	public function setDeepLinkUrl(?string $deepLinkUrl): void {
		$this->deepLinkUrl = $deepLinkUrl;
	}

	/**
	 * Whether the viewer is the one who attached this. Only they can take it
	 * off the note: it is their file, and removing somebody else's from a
	 * shared note is not part of editing it.
	 */
	public function setIsMine(bool $isMine): void {
		$this->isMine = $isMine;
	}

	/**
	 * Whether the thumbnail is a real preview of the file or the icon of its
	 * type. The layout of the mosaic differs; see css/style.css.
	 */
	public function setHasPreview(bool $hasPreview): void {
		$this->hasPreview = $hasPreview;
	}

	public function jsonSerialize(): array {
		return [
			'id'            => $this->id,
			'note_id'       => $this->noteId,
			'file_id'       => $this->fileId,
			'created_at'    => $this->createdAt,
			'user_id'       => $this->userId,
			'is_mine'       => (bool)$this->isMine,
			'has_preview'   => (bool)$this->hasPreview,
			'basename'      => $this->basename,
			'mime'          => $this->mime,
			'preview_url'   => $this->previewUrl,
			'download_url'  => $this->downloadUrl,
			'redirect_url'  => $this->redirectUrl,
			'deep_link_url' => $this->deepLinkUrl,
			// Where clicking the thumbnail goes: the file in Files for
			// somebody who has it there, the download for everybody else. It
			// saves the templates a branch they cannot express.
			'link_url'      => $this->redirectUrl ?? $this->downloadUrl
		];
	}
}
