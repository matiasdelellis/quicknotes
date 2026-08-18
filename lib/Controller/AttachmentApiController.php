<?php
/*
 * @copyright 2016-2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2016 Matias De lellis <mati86dl@gmail.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\QuickNotes\Controller;

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

use OCP\AppFramework\Http\RedirectResponse;

use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IPreview;
use OCP\IRequest;

use OCA\QuickNotes\Db\Attach;
use OCA\QuickNotes\Db\AttachMapper;
use OCA\QuickNotes\Service\FileService;
use OCA\QuickNotes\Service\NoteService;
use OCA\QuickNotes\Service\ShareService;

use Psr\Log\LoggerInterface;


class AttachmentApiController extends ApiController {

	/** How long a browser may keep a preview. The url carries the note and
	 *  the file, and a file that changes keeps its id, so this is a
	 *  compromise: long enough to be worth caching, short enough that an
	 *  edited attachment does not look stale all afternoon. */
	private const PREVIEW_CACHE_SECONDS = 60 * 60;

	private $fileService;
	private $noteService;
	private $attachMapper;
	private $shareService;
	private $previewManager;
	private $mimeTypeDetector;
	private $logger;
	private $userId;

	public function __construct(string $AppName,
	                            IRequest     $request,
	                            FileService  $fileService,
	                            NoteService  $noteService,
	                            AttachMapper $attachMapper,
	                            ShareService $shareService,
	                            IPreview     $previewManager,
	                            IMimeTypeDetector $mimeTypeDetector,
	                            LoggerInterface $logger,
	                            ?string $userId)
	{
		parent::__construct($AppName, $request);

		$this->fileService    = $fileService;
		$this->noteService    = $noteService;
		$this->attachMapper   = $attachMapper;
		$this->shareService   = $shareService;
		$this->previewManager = $previewManager;
		$this->mimeTypeDetector = $mimeTypeDetector;
		$this->logger         = $logger;
		$this->userId         = $userId;
	}

	/**
	 * The thumbnail of an attachment.
	 *
	 * Access is granted by the *note*, not by the file: whoever can see the
	 * note can see what is attached to it, which is what people mean when they
	 * say sharing a note should share its attachments. The file is read with
	 * the authority of whoever attached it — nothing is shared in Files — so
	 * this endpoint is the only thing standing between a recipient and
	 * somebody else's storage. It answers 404 to anything that is not a file
	 * attached to a note they can see.
	 *
	 * No CORS attribute on purpose, unlike the rest of this controller: these
	 * two are loaded by the browser as an <img> and a link, and the CORS
	 * middleware of the server insists on basic auth once an Origin header is
	 * involved.
	 *
	 * @param int $noteId note the file is attached to
	 * @param int $fileId file to preview
	 * @param int $x width to ask the preview manager for
	 * @param int $y height
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function preview(int $noteId, int $fileId, int $x = 512, int $y = 512): Response {
		$file = $this->resolveAttachment($noteId, $fileId);
		if (is_null($file)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		if (!$this->previewManager->isAvailable($file)) {
			return $this->mimeIconResponse($file);
		}

		$x = max(32, min($x, 1024));
		$y = max(32, min($y, 1024));

		try {
			$preview = $this->previewManager->getPreview($file, $x, $y, true);
		} catch (\Throwable $e) {
			// Worth a line in the log: from the outside this is
			// indistinguishable from a file nobody is allowed to see.
			$this->logger->info('quicknotes: no preview for the attachment ' . $fileId
				. ' of note ' . $noteId . ': ' . $e->getMessage(), ['exception' => $e]);
			return $this->mimeIconResponse($file);
		}

		$response = new FileDisplayResponse($preview, Http::STATUS_OK, [
			'Content-Type' => $preview->getMimeType(),
		]);
		$response->cacheFor(self::PREVIEW_CACHE_SECONDS, false, true);

		return $response;
	}

	/**
	 * The attachment itself.
	 *
	 * Same access check as the preview. For the people a note was shared with
	 * this is the only way to the bytes: the file is not in their Files, and
	 * this app deliberately does not put it there.
	 *
	 * @param int $noteId note the file is attached to
	 * @param int $fileId file to download
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function download(int $noteId, int $fileId): Response {
		$file = $this->resolveAttachment($noteId, $fileId);
		if (is_null($file)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$response = new FileDisplayResponse($file, Http::STATUS_OK, [
			'Content-Type' => $file->getMimeType(),
		]);

		// Set after constructing, because FileDisplayResponse writes its own
		// `inline` disposition over whatever it was handed. Attachment and not
		// inline: this serves arbitrary files of another user from the origin
		// of the instance, and an html or svg one has no business rendering
		// there. The preview above is safe the other way round — the preview
		// manager only ever produces an image.
		$response->addHeader(
			'Content-Disposition',
			'attachment; filename="' . rawurlencode($file->getName()) . '"'
		);

		return $response;
	}

	/**
	 * The icon of the file type, for a file there is no preview of.
	 *
	 * Not a 404: `/core/preview` answers previewless files with the mimetype
	 * icon — its `forceIcon` defaults to true — and that is what the grid used
	 * to show for a pdf or a zip before the app started serving previews
	 * itself. A blank tile instead of an icon is a regression nobody asked for.
	 */
	private function mimeIconResponse(File $file): Response {
		$response = new RedirectResponse(
			$this->mimeTypeDetector->mimeTypeIcon($file->getMimetype())
		);
		$response->cacheFor(self::PREVIEW_CACHE_SECONDS, false, true);
		return $response;
	}

	/**
	 * The file behind an attachment, if the caller is allowed to see it.
	 *
	 * Four questions, in order: can this user see the note, is this file really
	 * attached to *that* note, can the person who attached it still see the
	 * note themselves, and is the file still where they left it. A no to any of
	 * them is a 404 — including for a note that exists but is none of their
	 * business, which is how the rest of the app answers too.
	 */
	private function resolveAttachment(int $noteId, int $fileId): ?File {
		$note = $this->noteService->get($this->userId, $noteId);
		if (is_null($note)) {
			return null;
		}

		try {
			$attach = $this->attachMapper->findByNoteAndFileId($noteId, $fileId);
		} catch (DoesNotExistException $e) {
			return null;
		}

		// Somebody who is out of the note does not go on lending it a file.
		// The same rule `NoteService::hydrate()` applies when it lists them.
		if ($attach->getUserId() !== $note->getUserId()
		    && !$this->shareService->canSee($attach->getUserId(), $note)) {
			return null;
		}

		return $this->fileService->getFileOf($attach->getUserId(), $attach->getFileId());
	}

	/**
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function upload() {
		$files = $this->request->files;

		if (count($files) !== 1) {
			return new JSONResponse([],Http::STATUS_BAD_REQUEST);
		}

		$file = array_pop($files);

		if (!empty($file) && array_key_exists('error', $file) && $file['error'] !== UPLOAD_ERR_OK) {
			return new JSONResponse([],Http::STATUS_BAD_REQUEST);
		}

		$fileId = $this->fileService->upload($file['name'], file_get_contents($file['tmp_name']));

		return new JSONResponse($this->getAttachmentInfo($fileId));
	}

	/**
	 * Describe an already existing file so it can be attached to a note.
	 *
	 * The files picker only returns a path, and the `OC.Files` javascript
	 * client that used to resolve it into a file id was removed in
	 * Nextcloud 34, so the lookup happens here.
	 *
	 * @param string $path path of the file, relative to the user folder
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function info(string $path) {
		$fileId = $this->fileService->getFileIdByPath($path);

		if (is_null($fileId)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		return new JSONResponse($this->getAttachmentInfo($fileId));
	}

	/**
	 * What a client needs about a file it has just picked, before there is an
	 * attachment to serve. The urls are the ones of the server, resolved
	 * against the caller — who necessarily has the file, they just chose it.
	 */
	private function getAttachmentInfo(int $fileId): array {
		$file = $this->fileService->getFileOf((string)$this->userId, $fileId);

		return [
			'file_id'       => $fileId,
			'basename'      => is_null($file) ? null : $file->getName(),
			'mime'          => is_null($file) ? null : $file->getMimetype(),
			'has_preview'   => !is_null($file) && $this->previewManager->isAvailable($file),
			'preview_url'   => $this->fileService->getPreviewUrl($fileId, 512),
			'redirect_url'  => $this->fileService->getRedirectToFileUrl($fileId),
			'deep_link_url' => $this->fileService->getDeepLinkUrl($fileId)
		];
	}

}
