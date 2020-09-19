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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCP\IRequest;

use OCA\QuickNotes\Service\NoteService;


class NoteController extends Controller {

	private $noteService;
	private $userId;

	public function __construct($AppName,
	                            IRequest    $request,
	                            NoteService $noteService,
	                            $userId)
	{
		parent::__construct($AppName, $request);

		$this->noteService = $noteService;
		$this->userId      = $userId;
	}

	/**
	 * @NoAdminRequired
	 */
	public function index(): JSONResponse {
		$notes = $this->noteService->getAll($this->userId);
		$etag = md5(json_encode($notes));

		$lastModified = new \DateTime(null, new \DateTimeZone('GMT'));
		$timestamp = max(array_map(function($note) { return $note->getTimestamp(); }, $notes));
		$lastModified->setTimestamp($timestamp);

		$response = new JSONResponse($notes);
		$response->setETag($etag);
		$response->setLastModified($lastModified);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function show(int $id): JSONResponse {
		$note = $this->noteService->get($this->userId, $id);
		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$etag = md5(json_encode($note));

		$response = new JSONResponse($note);
		$response->setETag($etag);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $title
	 * @param string $content
	 * @param string $color
	 * @param bool   $isPinned
	 * @param array  $sharedWith
	 * @param array  $tags
	 * @param array  $attachments
	 */
	public function create(string $title,
		               string $content,
		               string $color = null,
		               bool   $isPinned = false,
		               array  $sharedWith = [],
		               array  $tags = [],
		               array  $attachments = [])
		{
		$note = $this->noteService->create($this->userId,
		                                   $title,
		                                   $content,
		                                   $color,
		                                   $isPinned,
		                                   $sharedWith,
		                                   $tags,
		                                   $attachments);

		$etag = md5(json_encode($note));

		$response = new JSONResponse($note);
		$response->setETag($etag);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 * @param string $title
	 * @param string $content
	 * @param string $color
	 * @param bool   $isPinned
	 * @param array  $tags
	 * @param array  $sharedWith
	 * @param array  $attachments
	 */
	public function update(int $id,
	                       string $title,
	                       string $content,
	                       string $color,
	                       bool   $isPinned,
	                       array  $tags,
	                       array  $sharedWith,
	                       array  $attachments): JSONResponse
	{
		$note = $this->noteService->update($this->userId,
		                                   $id,
		                                   $title,
		                                   $content,
		                                   $color,
		                                   $isPinned,
		                                   $tags,
		                                   $sharedWith,
		                                   $attachments);

		if (is_null($note)) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$etag = md5(json_encode($note));

		$response = new JSONResponse($note);
		$response->setETag($etag);

		return $response;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $id
	 */
	public function destroy(int $id): JSONResponse {
		$this->noteService->destroy($this->userId, $id);
		return new JSONResponse([]);
	}

}
