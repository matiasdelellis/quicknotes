<?php
declare(strict_types=1);
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\QuickNotes\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

use OCA\QuickNotes\Service\SettingsService;


class SettingsController extends Controller {

	/** @var SettingsService */
	private $settingsService;

	/** @var string */
	private $userId;

	const STATE_OK = 0;
	const STATE_FALSE = 1;
	const STATE_SUCCESS = 2;
	const STATE_ERROR = 3;

	public function __construct ($appName,
	                             IRequest        $request,
	                             SettingsService $settingsService,
	                             $userId)
	{
		parent::__construct($appName, $request);

		$this->appName         = $appName;
		$this->settingsService = $settingsService;
		$this->userId          = $userId;
	}

	/**
	 * @NoAdminRequired
	 * @param $type
	 * @param $value
	 * @return JSONResponse
	 */
	public function setUserValue($type, $value) {
		$status = self::STATE_SUCCESS;

		switch ($type) {
			case SettingsService::COLOR_FOR_NEW_NOTES_KEY:
				$this->settingsService->setColorForNewNotes($value);
				break;
			default:
				$status = self::STATE_ERROR;
				break;
		}

		// Response
		$result = [
			'status' => $status,
			'value' => $value
		];

		return new JSONResponse($result);
	}

	/**
	 * @NoAdminRequired
	 * @param $type
	 * @return JSONResponse
	 */
	public function getUserValue($type) {
		$status = self::STATE_OK;
		$value ='nodata';

		switch ($type) {
			case SettingsService::COLOR_FOR_NEW_NOTES_KEY:
				$value = $this->settingsService->getColorForNewNotes();
				break;
			default:
				$status = self::STATE_FALSE;
				break;
		}

		$result = [
			'status' => $status,
			'value' => $value
		];

		return new JSONResponse($result);
	}

}
