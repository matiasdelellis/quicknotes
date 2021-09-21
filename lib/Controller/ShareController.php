<?php
/*
 * @copyright 2020 Matias De lellis <mati86dl@gmail.com>
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

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Controller;

use OCP\IRequest;

use OCA\QuickNotes\Db\NoteShare;
use OCA\QuickNotes\Db\NoteShareMapper;

use OCP\AppFramework\Db\DoesNotExistException;

class ShareController extends Controller {

	private $noteShareMapper;
	private $userId;

	public function __construct($AppName,
	                            IRequest        $request,
	                            NoteShareMapper $noteShareMapper,
	                            $userId)
	{
		parent::__construct($AppName, $request);

		$this->noteShareMapper = $noteShareMapper;
		$this->userId          = $userId;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param int $noteId
	 */
	public function destroy(int $noteId): JSONResponse {
		try {
			$noteShare = $this->noteShareMapper->findByNoteAndUser($noteId, $this->userId);
		} catch (DoesNotExistException $e) {
			return new JSONResponse([], Http::STATUS_NOT_FOUND);
		}

		$this->noteShareMapper->delete($noteShare);

		return new JSONResponse([]);
	}

}
