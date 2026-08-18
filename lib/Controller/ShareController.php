<?php
/*
 * @copyright 2020-2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2020 Matias De lellis <mati86dl@gmail.com>
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
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCP\IRequest;

use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Service\NoteService;
use OCA\QuickNotes\Service\ShareService;

/**
 * Sharing a note, for the app itself. The same endpoints live under
 * `/api/v1` in `ShareApiController`; everything they do is in `ShareActions`.
 */
class ShareController extends Controller {

	use ShareActions;

	/** @var NoteService */
	private $noteService;

	/** @var ShareService */
	private $shareService;

	/** @var string|null */
	private $userId;

	public function __construct(string $AppName,
	                            IRequest     $request,
	                            NoteService  $noteService,
	                            ShareService $shareService,
	                            ?string $userId)
	{
		parent::__construct($AppName, $request);

		$this->noteService  = $noteService;
		$this->shareService = $shareService;
		$this->userId       = $userId;
	}

	/**
	 * @param int $noteId
	 */
	#[NoAdminRequired]
	public function index(int $noteId): JSONResponse {
		return $this->handleIndex($noteId);
	}

	/**
	 * @param int $noteId
	 * @param int $shareType NoteShare::TYPE_USER or NoteShare::TYPE_GROUP
	 * @param string $shareWith uid or gid
	 * @param int|null $permissions bitmask, read only when not given
	 */
	#[NoAdminRequired]
	public function create(int $noteId,
	                       int $shareType = NoteShare::TYPE_USER,
	                       string $shareWith = '',
	                       ?int $permissions = null): JSONResponse
	{
		return $this->handleCreate($noteId, $shareType, $shareWith, $permissions);
	}

	/**
	 * @param int $shareId
	 * @param int $permissions
	 */
	#[NoAdminRequired]
	public function update(int $shareId, int $permissions): JSONResponse {
		return $this->handleUpdate($shareId, $permissions);
	}

	/**
	 * @param int $shareId
	 */
	#[NoAdminRequired]
	public function destroy(int $shareId): JSONResponse {
		return $this->handleDestroy($shareId);
	}

	/**
	 * @param int $noteId
	 */
	#[NoAdminRequired]
	public function leave(int $noteId): JSONResponse {
		return $this->handleLeave($noteId);
	}

	/**
	 * @param int $noteId
	 * @param string $search
	 * @param int $limit
	 */
	#[NoAdminRequired]
	public function sharees(int $noteId, string $search = '', int $limit = 25): JSONResponse {
		return $this->handleSharees($noteId, $search, $limit);
	}

}
