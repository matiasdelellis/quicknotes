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

	/**
	 * Bumped to 1.4 in 0.9.4, when attachments started being served by the
	 * app: `preview_url` points at `/notes/{id}/attachments/{fileId}/preview`
	 * instead of the preview endpoint of the server, and an attachment gained
	 * `download_url`, `link_url` (where clicking it should go), `user_id` (who
	 * attached it) and `is_mine`. That `user_id` also travels *back* in a save:
	 * it is what tells the attachments of the caller from everybody else's, now
	 * that anybody who can edit a note can attach to it. `redirect_url` and `deep_link_url` are null for
	 * somebody who cannot reach the file in their own Files, where they used
	 * to make the attachment disappear from the response altogether.
	 *
	 * Bumped to 1.3 in 0.9.3, when archiving became personal: `archivedAt` is
	 * the caller's, archive and unarchive answer to anybody who can see the
	 * note, the payload gained `canLeave` (whether there is a share of theirs
	 * to walk away from), and destroying a note that is not the caller's is a
	 * 404 rather than a no-op.
	 *
	 * Bumped to 1.2 in 0.9.2, when reminders became personal: `reminderAt` and
	 * `reminderNotifiedAt` are the ones of whoever asked — null on a note
	 * somebody else armed a reminder on — and the reminder endpoint answers to
	 * anybody who can see the note, not only to its owner.
	 *
	 * Bumped to 1.1 in 0.9.1 with the share rewrite. The note payload gained
	 * `permissions`, `canEdit`, `canReshare`, `isOwner`, `owner`, `sharedByMe`
	 * and `etag`, and two fields changed shape: `sharedBy` is now an object
	 * (or null) instead of a list of one, and the entries of `sharedWith` are
	 * shares — `{id, shareType, shareWith, displayName, permissions, …}` —
	 * rather than `{shared_user, display_name}`.
	 *
	 * @var string
	 */
	public const API_VERSION = '1.4';

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