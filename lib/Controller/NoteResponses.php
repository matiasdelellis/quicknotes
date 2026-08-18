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

use OCA\QuickNotes\Db\Note;
use OCA\QuickNotes\Exception\ConflictException;
use OCA\QuickNotes\Exception\ForbiddenException;
use OCA\QuickNotes\Service\NoteService;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

/**
 * What `NoteController` and `NoteApiController` answer with.
 *
 * The two of them are the same endpoints twice — one set for the page, one
 * under `/api/v1` with CORS — and the only difference between them is the
 * attributes on the methods. Saving a note now has enough going on (three
 * outcomes besides success, and an `If-Match` to read off the request) that
 * writing it twice would mean maintaining it twice.
 */
trait NoteResponses {

	/**
	 * A note, with its own etag on the response.
	 *
	 * The etag is derived from the stored note (`Note::getEtag()`) and not
	 * from the JSON of the response: the JSON also carries preview urls,
	 * display names and the permissions of whoever asked, so two users would
	 * get two different tags for a note that is in exactly the same state.
	 * The tag is what a later `If-Match` is compared against, so it has to
	 * mean the same thing to everybody.
	 */
	private function respondWithNote(?Note $note): JSONResponse {
		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$response = new JSONResponse($note);
		$response->setETag($note->getEtag());

		return $response;
	}

	/**
	 * The etag the client last saw, if it says.
	 *
	 * `If-Match: *` is the HTTP way of saying "as long as it exists", which is
	 * exactly the old behaviour, so it is read as no condition at all.
	 */
	private function ifMatch(): ?string {
		$header = trim($this->request->getHeader('If-Match'));
		if ($header === '' || $header === '*') {
			return null;
		}

		// Strip the quotes and a weak validator prefix, so a client that
		// echoes back what it was given verbatim is understood.
		if (strpos($header, 'W/') === 0) {
			$header = substr($header, 2);
		}

		return trim($header, '"');
	}

	/**
	 * Save a note, mapping what the service refuses to do onto HTTP.
	 *
	 * - 404: no such note, or none of this user's business.
	 * - 403: they may read it, but this share does not let them write.
	 * - 412: it changed since they last read it. The current note comes back
	 *        in the body, so the client can show what it was about to
	 *        overwrite instead of just failing.
	 */
	private function saveNote(NoteService $noteService,
	                          string  $userId,
	                          int     $id,
	                          string  $title,
	                          string  $content,
	                          ?string $color,
	                          ?bool   $isPinned,
	                          ?array  $tags,
	                          ?array  $attachments,
	                          ?array  $sharedWith): JSONResponse
	{
		try {
			$note = $noteService->update($userId, $id, $title, $content, $color,
			                             $isPinned, $tags, $attachments, $sharedWith,
			                             $this->ifMatch());
		} catch (ForbiddenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (ConflictException $e) {
			$response = new JSONResponse([
				'message' => $e->getMessage(),
				'note' => $e->getNote(),
			], Http::STATUS_PRECONDITION_FAILED);
			$response->setETag($e->getNote()->getEtag());
			return $response;
		}

		return $this->respondWithNote($note);
	}

}
