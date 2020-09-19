<?php declare(strict_types=1);
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


namespace OCA\QuickNotes\AppInfo;

use OCP\Capabilities\ICapability;
use OCP\App\IAppManager;

class Capabilities implements ICapability {

	/** @var IAppManager */
	private $appManager;

	public function __construct(IAppManager $appManager) {
		$this->appManager = $appManager;
	}

	public function getCapabilities() {
		return [
			Application::APP_ID => [
				'version' => $this->appManager->getAppVersion(Application::APP_ID),
				'api_version' => Application::API_VERSION,
			],
		];
	}

}