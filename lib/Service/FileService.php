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

use OCP\IURLGenerator;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class FileService {

	/**  @var string|null */
	private $userId;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IURLGenerator */
	protected $urlGenerator;

	public function __construct($userId,
	                            IRootFolder   $rootFolder,
	                            IURLGenerator $urlGenerator)
	{
		$this->userId       = $userId;
		$this->rootFolder   = $rootFolder;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Get thumbnail of the give file id
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

		return $this->urlGenerator->getAbsoluteURL('core/preview?fileId=' . $fileId .'&x=' . $sideSize . '&y=' . $sideSize . '&a=false&v=' . $file->getETag());
	}

	/**
	 * Redirects to the file list and highlight the given file id
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

		return $this->urlGenerator->linkToRoute('files.view.index', $params);
	}

}
