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

use OCP\AppFramework\App;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IServerContainer;


class Application extends App {

	/** @var string */
	public const APP_ID = 'quicknotes';

	/** @var string */
	public const API_VERSION = '1.0';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(): void {
		$this->registerNavigationEntry();
		$this->registerCapabilities();
	}

	private function registerNavigationEntry(): void {
		$container = $this->getContainer();
		$server = $container->getServer();

		$server->getNavigationManager()->add(static function () use ($container) {
			$urlGenerator = $container->query(IURLGenerator::class);
			$l10n = $container->query(IL10N::class);
			return [
				'id' => 'quicknotes',
				'order' => 10,
				'href' => $urlGenerator->linkToRoute('quicknotes.page.index'),
				'icon' => $urlGenerator->imagePath('quicknotes', 'app.svg'),
				'name' => $l10n->t('Quick notes'),
			];
		});
	}

	private function registerCapabilities(): void {
		$container = $this->getContainer();
		$container->registerCapability(Capabilities::class);
	}

}