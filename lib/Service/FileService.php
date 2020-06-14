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

use OCP\Files\IRootFolder;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;

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

	public function getPreviewUrl($fileId, $sideSize): string {
		$userFolder = $this->rootFolder->getUserFolder($this->userId);
		$node = current($userFolder->getById($fileId));
		$path = $userFolder->getRelativePath($node->getPath());

		return $this->urlGenerator->linkToRouteAbsolute('core.Preview.getPreview', [
			'file' => $path,
			'x' => $sideSize,
			'y' => $sideSize
		]);
	}

}
