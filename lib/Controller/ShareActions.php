<?php declare(strict_types=1);
/*
 * @copyright 2026 Matias De lellis <mati86dl@gmail.com>
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

use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Exception\ForbiddenException;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * The share endpoints, shared by the page and the `/api/v1` controllers.
 *
 * Shares are their own resource now, and are applied the moment the user asks
 * for them. They used to travel inside the note: the dialog handed a list of
 * users back to the editor and nothing happened until the note itself was
 * saved, which meant sharing could be lost by pressing Escape, and that a note
 * open in two tabs would have the older tab silently revoke whatever the newer
 * one had shared.
 *
 * The classes using this must have `$noteService`, `$shareService` and
 * `$userId`, and are expected to be nothing but attributes over these methods.
 */
trait ShareActions {

	/**
	 * The shares of a note.
	 *
	 * Only for somebody who could act on the list: its owner, or a recipient
	 * who was given the right to pass the note on.
	 */
	protected function handleIndex(int $noteId): JSONResponse {
		$note = $this->noteService->get($this->userId, $noteId);
		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$note->getIsOwner() && !$note->canReshare()) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse($this->shareService->getSharesForNote($noteId));
	}

	/**
	 * Share a note with one user or one group.
	 */
	protected function handleCreate(int $noteId,
	                                int $shareType = NoteShare::TYPE_USER,
	                                string $shareWith = '',
	                                ?int $permissions = null): JSONResponse
	{
		$note = $this->noteService->get($this->userId, $noteId);
		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		try {
			$share = $this->shareService->create($this->userId, $note, $shareType, $shareWith, $permissions);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($share);
	}

	/**
	 * Change what a share grants — "can view" to "can edit" and back.
	 */
	protected function handleUpdate(int $shareId, int $permissions): JSONResponse {
		try {
			$share = $this->shareService->updatePermissions($this->userId, $shareId, $permissions);
		} catch (DoesNotExistException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (\InvalidArgumentException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse($share);
	}

	/**
	 * Take a share back.
	 */
	protected function handleDestroy(int $shareId): JSONResponse {
		try {
			$this->shareService->delete($this->userId, $shareId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		} catch (ForbiddenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		return new JSONResponse([]);
	}

	/**
	 * Walk away from a note somebody shared with you.
	 *
	 * Answers 404 when there is no such share, which is also the answer for a
	 * note that was shared with a group the user is in: that one is not theirs
	 * to leave, since it would take the note from everybody else too.
	 */
	protected function handleLeave(int $noteId): JSONResponse {
		if (!$this->shareService->leave($this->userId, $noteId)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse([]);
	}

	/**
	 * Who this note could still be shared with.
	 */
	protected function handleSharees(int $noteId, string $search = '', int $limit = 25): JSONResponse {
		$note = $this->noteService->get($this->userId, $noteId);
		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}
		if (!$note->getIsOwner() && !$note->canReshare()) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}

		$limit = max(1, min($limit, 50));

		return new JSONResponse($this->shareService->searchSharees($this->userId, $note, $search, $limit));
	}

}
