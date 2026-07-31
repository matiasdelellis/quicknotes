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

use OCP\Config\IUserConfig;

class SettingsService {

	/**
	 * Settings keys and default values.
	 */
	const COLOR_FOR_NEW_NOTES_KEY = 'default_color';
	const DEFAULT_COLOR_FOR_NEW_NOTES = '#F7EB96';

	const ATTACHMENTS_FOLDER_KEY = 'attachments_folder';
	const DEFAULT_ATTACHMENTS_FOLDER = 'Quicknotes';

	/**
	 * Whether the notes with a reminder are published as a read-only
	 * calendar. Off by default: it shows up inside the Calendar app and in
	 * every CalDAV client the user has, which is not something to turn on
	 * behind their back.
	 */
	const CALENDAR_ENABLED_KEY = 'calendar_enabled';
	const DEFAULT_CALENDAR_ENABLED = false;

	/** @var IUserConfig */
	private $userConfig;

	/**  @var string|null */
	private $userId;

	public function __construct(IUserConfig $userConfig,
	                            ?string $userId)
	{
		$this->userConfig = $userConfig;
		$this->userId = $userId;
	}


	public function getColorForNewNotes(): string {
		return $this->userConfig->getValueString($this->getUserId(), Application::APP_ID, self::COLOR_FOR_NEW_NOTES_KEY, self::DEFAULT_COLOR_FOR_NEW_NOTES);
	}

	public function setColorForNewNotes(string $color): void {
		$this->userConfig->setValueString($this->getUserId(), Application::APP_ID, self::COLOR_FOR_NEW_NOTES_KEY, $color);
	}

	public function getAttachmentsFolder(): string {
		return $this->userConfig->getValueString($this->getUserId(), Application::APP_ID, self::ATTACHMENTS_FOLDER_KEY, self::DEFAULT_ATTACHMENTS_FOLDER);
	}

	public function setAttachmentsFolder(string $folder): void {
		$this->userConfig->setValueString($this->getUserId(), Application::APP_ID, self::ATTACHMENTS_FOLDER_KEY, $folder);
	}

	/**
	 * Whether the virtual calendar is published for a user.
	 *
	 * Takes an explicit user id because the calendar provider runs on the
	 * CalDAV path, where there is no logged in user to read it from — the
	 * principal of the request is all there is. The settings page calls it
	 * with no argument and gets the current user, like every other getter
	 * here.
	 */
	public function isCalendarEnabled(?string $userId = null): bool {
		return $this->userConfig->getValueBool(
			$userId ?? $this->getUserId(),
			Application::APP_ID,
			self::CALENDAR_ENABLED_KEY,
			self::DEFAULT_CALENDAR_ENABLED
		);
	}

	public function setCalendarEnabled(bool $enabled): void {
		$this->userConfig->setValueBool($this->getUserId(), Application::APP_ID, self::CALENDAR_ENABLED_KEY, $enabled);
	}

	/**
	 * IUserConfig, unlike the deprecated IConfig, insists on a real user id.
	 * Every caller runs behind an authenticated route, so a null user id here
	 * means the service was wired up wrong.
	 */
	private function getUserId(): string {
		if ($this->userId === null) {
			throw new \RuntimeException('Quick notes settings require a logged in user.');
		}
		return $this->userId;
	}

}
