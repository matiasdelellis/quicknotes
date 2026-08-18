<?php declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\QuickNotes\Service;

use OCP\AppFramework\Utility\ITimeFactory;

use OCP\IPreview;
use OCP\IURLGenerator;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;


use OCA\QuickNotes\Service\SettingsService;

class FileService {

	/**  @var string|null */
	private $userId;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IPreview */
	private $previewManager;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var SettingsService */
	private $settingsService;

	public function __construct(?string $userId,
	                            IRootFolder     $rootFolder,
	                            IURLGenerator   $urlGenerator,
	                            IPreview        $previewManager,
	                            ITimeFactory    $timeFactory,
	                            SettingsService $settingsService)
	{
		$this->userId          = $userId;
		$this->rootFolder      = $rootFolder;
		$this->urlGenerator    = $urlGenerator;
		$this->previewManager  = $previewManager;
		$this->timeFactory     = $timeFactory;
		$this->settingsService = $settingsService;
	}

	/**
	 * The file behind an attachment, read from the storage of whoever
	 * attached it rather than of whoever is asking.
	 *
	 * This is the whole of the "sharing a note shares its attachments" story:
	 * the app resolves the file with the attacher's authority and serves it
	 * through its own endpoints, having already asked whether the caller may
	 * see the *note*. Nothing is shared in Files, so there is nothing to keep
	 * in sync and nothing to clean up when the note stops being shared.
	 *
	 * @return File|null null when the file is gone, or when the user it
	 *         belonged to no longer has it
	 */
	public function getFileOf(string $userId, int $fileId): ?File {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Throwable $e) {
			// No such user any more; their storage is not there to read.
			return null;
		}

		$file = current($userFolder->getById($fileId));

		return ($file instanceof File) ? $file : null;
	}

	/**
	 * Whether there is a real thumbnail behind the preview url of this file, or
	 * whether it will fall back to the icon of the file type.
	 *
	 * The client needs to know, because the two are laid out differently: a
	 * photo is cropped to fill its tile of the mosaic, an icon is centred at
	 * its own size — stretching a pdf icon to cover a tile looks like a bug.
	 */
	public function hasPreview(File $file): bool {
		return $this->previewManager->isAvailable($file);
	}

	/**
	 * Where the app serves the thumbnail of an attachment from.
	 *
	 * Deliberately not `/core/preview`, which checks the *viewer* against the
	 * file: for somebody the note was shared with that answers 404, which is
	 * exactly the hole this closes. The note id is part of the url because it
	 * is what the endpoint checks access against.
	 */
	public function getAttachmentPreviewUrl(int $noteId, int $fileId, int $sideSize): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('quicknotes.AttachmentApi.preview', [
				'noteId' => $noteId,
				'fileId' => $fileId,
				'x' => $sideSize,
				'y' => $sideSize,
			])
		);
	}

	/**
	 * Where the app serves the file itself from. Same access check as the
	 * preview, and the only way a recipient has of getting the bytes.
	 */
	public function getAttachmentDownloadUrl(int $noteId, int $fileId): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('quicknotes.AttachmentApi.download', [
				'noteId' => $noteId,
				'fileId' => $fileId,
			])
		);
	}

	/**
	 * Get thumbnail of the give file id, through the preview endpoint of the
	 * server. Only good for a file the *current* user can reach, which is why
	 * it is what the attach dialog uses on a file just picked, and not what a
	 * note carries once it is saved.
	 *
	 * @param int $fileId file id to show
	 * @param int $sideSize side lenght to show
	 */
	public function getPreviewUrl(int $fileId, int $sideSize): ?string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$file = current($userFolder->getById($fileId));

		if (!($file instanceof File)) {
			return null;
		}

		return $this->urlGenerator->getAbsoluteURL('index.php/core/preview?fileId=' . $fileId .'&x=' . $sideSize . '&y=' . $sideSize . '&a=false&v=' . $file->getETag());
	}

	/**
	 * Redirects to the file list and highlight the given file id.
	 *
	 * Resolved against the *viewer*, and null when they cannot reach the file:
	 * pointing somebody at a path in somebody else's Files is worse than not
	 * offering the link at all. The attachment falls back to its download url.
	 *
	 * @param int $fileId file id to show
	 */
	public function getRedirectToFileUrl(int $fileId): ?string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$file = current($userFolder->getById($fileId));

		if (!($file instanceof File)) {
			return null;
		}

		$params = [];
		$params['dir'] = $userFolder->getRelativePath($file->getParent()->getPath());
		$params['scrollto'] = $file->getName();

		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('files.view.index', $params)
		);
	}

	/**
	 * Get a deep link that can open directly on clients to the given file id
	 *
	 * @param int $fileId file id to open
	 */
	public function getDeepLinkUrl(int $fileId): ?string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$file = current($userFolder->getById($fileId));

		if (!($file instanceof File)) {
			return null;
		}

//		return "nc://directlink/f/" . $fileId;
		return $this->urlGenerator->getAbsoluteURL(
			"/f/" . $fileId
		);
	}

	/**
	 * Resolve a path relative to the user folder into a file id.
	 *
	 * Nextcloud 34 dropped the legacy `OC.Files` javascript client, so the
	 * file picker only hands back a path and the lookup has to happen here.
	 *
	 * @param string $path path as returned by the files picker
	 */
	public function getFileIdByPath(string $path): ?int {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		try {
			$file = $userFolder->get($path);
		} catch (NotFoundException $e) {
			return null;
		}

		if (!($file instanceof File)) {
			return null;
		}

		return $file->getId();
	}

	/**
	 * Upload attachment and return fileId
	 */
	public function upload($fileName, $fileContent): int {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);

		$ts = $this->timeFactory->getTime();
		$dt = new \DateTime();
		$dt->setTimestamp($ts);

		$secureFileName = $dt->format('YmdHis') . ' - ' . $fileName;
		$attachmentsFolder = $this->getAttachmentsFolder();

		$file = $attachmentsFolder->newFile($secureFileName);
		$file->putContent($fileContent);

		return $file->getId();
	}

	/**
	 * @return \OCP\Files\Folder
	 */
	private function getAttachmentsFolder() {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$attachmentsFolder = $this->settingsService->getAttachmentsFolder();

		try {
			$attachmentsFolderNode = $userFolder->get($attachmentsFolder);
		} catch (NotFoundException $e) {
			$attachmentsFolderNode = $userFolder->newFolder($attachmentsFolder);
		}

		return $attachmentsFolderNode;
	}
}
