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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCP\IRequest;

use OCA\QuickNotes\Service\NoteService;


class NoteController extends Controller {

	use NoteResponses;

	private $noteService;
	private $userId;

	public function __construct(string $AppName,
	                            IRequest    $request,
	                            NoteService $noteService,
	                            ?string $userId)
	{
		parent::__construct($AppName, $request);

		$this->noteService = $noteService;
		$this->userId      = $userId;
	}

	#[NoAdminRequired]
	public function index(): JSONResponse {
		$notes = $this->noteService->getAll($this->userId);
		if (count($notes) === 0) {
			return new JSONResponse([]);
		}

		$lastModified = new \DateTime('now', new \DateTimeZone('GMT'));
		$timestamp = max(array_map(function($note) { return $note->getTimestamp(); }, $notes));
		$lastModified->setTimestamp($timestamp);

		$response = new JSONResponse($notes);
		$response->setETag(md5(json_encode($notes)));
		$response->setLastModified($lastModified);

		return $response;
	}

	/**
	 * The handful of notes the dashboard widget shows.
	 *
	 * Archived and trashed notes are left out. They were not until 0.9.5, which
	 * was wrong all along and became visible once archiving turned personal:
	 * taking a note out of your grid and finding it still on your dashboard is
	 * not a widget being helpful.
	 */
	#[NoAdminRequired]
	public function dashboard(): JSONResponse {
		$notes = array_filter(
			$this->noteService->getAll($this->userId),
			function ($note) {
				return is_null($note->getArchivedAt()) && is_null($note->getDeletedAt());
			}
		);

		if (count($notes) === 0) {
			return new JSONResponse([
				'notes' => []
			]);
		}

		$items = array_map(function ($note) {
			return [
				'id' => $note->getId(),
				'title' => strip_tags($note->getTitle()),
				'content' => strip_tags($note->getContent()),
				'pinned' => $note->getIsPinned(),
				'timestamp' => $note->getTimestamp(),
			];
		}, $notes);

		usort($items, function ($a, $b) {
			if ($a['pinned'] == $b['pinned'])
				return $b['timestamp'] - $a['timestamp'];
			if ($a['pinned'] && !$b['pinned'])
				return -1;
			if (!$a['pinned'] && $b['pinned'])
				return 1;
		});

		return new JSONResponse([
			'notes' => array_slice($items, 0, 7)
		]);
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	public function show(int $id): JSONResponse {
		return $this->respondWithNote($this->noteService->get($this->userId, $id));
	}

	/**
	 * @param string $title
	 * @param string $content
	 * @param string $color
	 * @param bool   $isPinned
	 * @param array  $sharedWith
	 * @param array  $tags
	 * @param array  $attachments
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function create(string $title,
		               string $content,
		               ?string $color = null,
		               bool   $isPinned = false,
		               array  $sharedWith = [],
		               array  $tags = [],
		               array  $attachments = []): JSONResponse
		{
		$note = $this->noteService->create($this->userId,
		                                   $title,
		                                   $content,
		                                   $color,
		                                   $isPinned,
		                                   $sharedWith,
		                                   $tags,
		                                   $attachments);

		return $this->respondWithNote($note);
	}

	/**
	 * Save a note. Everything but the title and the content is optional: a
	 * field that is not sent is left alone, so a client does not have to
	 * resend — and risk overwriting — what it did not mean to touch.
	 *
	 * Send the etag of the note as it was read in `If-Match` to be told (412)
	 * instead of silently overwriting somebody else's edit.
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 * @param string|null $color owner only
	 * @param bool|null   $isPinned personal to the caller
	 * @param array|null  $tags personal to the caller
	 * @param array|null  $attachments owner only
	 * @param array|null  $sharedWith owner only, superseded by the share endpoints
	 */
	#[NoAdminRequired]
	public function update(int $id,
	                       string  $title,
	                       string  $content,
	                       ?string $color = null,
	                       ?bool   $isPinned = null,
	                       ?array  $tags = null,
	                       ?array  $attachments = null,
	                       ?array  $sharedWith = null): JSONResponse
	{
		return $this->saveNote($this->noteService, $this->userId, $id, $title,
		                       $content, $color, $isPinned, $tags, $attachments,
		                       $sharedWith);
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	public function destroy(int $id): JSONResponse {
		if (!$this->noteService->destroy($this->userId, $id)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse([]);
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function archive(int $id): JSONResponse {
		return $this->respondWithNote($this->noteService->archive($this->userId, $id));
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function trash(int $id): JSONResponse {
		return $this->respondWithNote($this->noteService->trash($this->userId, $id));
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function unarchive(int $id): JSONResponse {
		return $this->respondWithNote($this->noteService->unarchive($this->userId, $id));
	}

	/**
	 * @param int $id
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function restore(int $id): JSONResponse {
		return $this->respondWithNote($this->noteService->restore($this->userId, $id));
	}

	/**
	 * @param int $id
	 * @param string|null $reminderAt UTC 'Y-m-d H:i:s', null to cancel
	 */
	#[NoAdminRequired]
	public function reminder(int $id, ?string $reminderAt = null): JSONResponse {
		try {
			$note = $this->noteService->setReminder($this->userId, $id, $reminderAt);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return $this->respondWithNote($note);
	}

}
