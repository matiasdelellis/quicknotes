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

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;

use OCP\IRequest;

use OCA\QuickNotes\Service\FileService;


class AttachmentApiController extends ApiController {

	private $fileService;
	private $userId;

	public function __construct($AppName,
	                            IRequest    $request,
	                            FileService $fileService,
	                            $userId)
	{
		parent::__construct($AppName, $request);

		$this->fileService = $fileService;
		$this->userId      = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return JSONResponse
	 */
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

		return new JSONResponse([
			'file_id'       => $fileId,
			'preview_url'   => $this->fileService->getPreviewUrl($fileId, 512),
			'redirect_url'  => $this->fileService->getRedirectToFileUrl($fileId),
			'deep_link_url' => $this->fileService->getDeepLinkUrl($fileId)
		]);
	}

}
