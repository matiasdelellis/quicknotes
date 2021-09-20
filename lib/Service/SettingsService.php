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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\QuickNotes\Service;

use OCA\QuickNotes\AppInfo\Application;

use OCP\IConfig;

class SettingsService {

	/**
	 * Settings keys and default values.
	 */
	const COLOR_FOR_NEW_NOTES_KEY = 'default_color';
	const DEFAULT_COLOR_FOR_NEW_NOTES = '#F7EB96';

	const ATTACHMENTS_FOLDER_KEY = 'attachments_folder';
	const DEFAULT_ATTACHMENTS_FOLDER = 'Quicknotes';

	/** @var IConfig Config */
	private $config;

	/**  @var string|null */
	private $userId;

	/**
	 * @param IConfig $config
	 * @param string $userId
	 */
	public function __construct(IConfig $config,
	                            $userId)
	{
		$this->config = $config;
		$this->userId = $userId;
	}


	public function getColorForNewNotes(): string {
		return $this->config->getUserValue($this->userId, Application::APP_ID, self::COLOR_FOR_NEW_NOTES_KEY, self::DEFAULT_COLOR_FOR_NEW_NOTES);
	}

	public function setColorForNewNotes(string $color): void {
		$this->config->setUserValue($this->userId, Application::APP_ID, self::COLOR_FOR_NEW_NOTES_KEY, $color);
	}

	public function getAttachmentsFolder(): string {
		return $this->config->getUserValue($this->userId, Application::APP_ID, self::ATTACHMENTS_FOLDER_KEY, self::DEFAULT_ATTACHMENTS_FOLDER);
	}

	public function setAttachmentsFolder(string $folder): void {
		$this->config->setUserValue($this->userId, Application::APP_ID, self::ATTACHMENTS_FOLDER_KEY, $folder);
	}

}
