<?php declare(strict_types=1);
/*
 * @copyright 2016-2022 Matias De lellis <mati86dl@gmail.com>
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
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\INavigationManager;

use Psr\Container\ContainerInterface;

use OCA\QuickNotes\Calendar\CalendarProvider;
use OCA\QuickNotes\Dashboard\NotesWidget;
use OCA\QuickNotes\Listeners\BeforeTemplateRenderedListener;
use OCA\QuickNotes\Notification\Notifier;
use OCA\QuickNotes\Search\NoteSearchProvider;

class Application extends App implements IBootstrap {

	/** @var string */
	public const APP_ID = 'quicknotes';

	/** @var string */
	public const API_VERSION = '1.0';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerSearchProvider(NoteSearchProvider::class);
		$context->registerCapability(Capabilities::class);
		$context->registerDashboardWidget(NotesWidget::class);
		$context->registerNotifierService(Notifier::class);
		$context->registerCalendarProvider(CalendarProvider::class);
		$context->registerEventListener(
			BeforeTemplateRenderedEvent::class,
			BeforeTemplateRenderedListener::class
		);
	}

	public function boot(IBootContext $context): void {
		$this->registerNavigationEntry($context->getAppContainer());
	}

	/**
	 * Nextcloud 34 trimmed IServerContainer down to a handful of methods, so
	 * the services are resolved from the app container instead. The entry
	 * itself stays lazy: nothing is instantiated until the navigation is
	 * actually rendered.
	 */
	private function registerNavigationEntry(ContainerInterface $container): void {
		$container->get(INavigationManager::class)->add(static function () use ($container) {
			/** @var IURLGenerator $urlGenerator */
			$urlGenerator = $container->get(IURLGenerator::class);
			/** @var IL10N $l10n */
			$l10n = $container->get(IL10N::class);
			return [
				'id' => self::APP_ID,
				'order' => 10,
				'href' => $urlGenerator->linkToRoute('quicknotes.page.index'),
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'name' => $l10n->t('Quick notes')
			];
		});
	}

}