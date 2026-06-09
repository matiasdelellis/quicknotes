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

namespace OCA\QuickNotes\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

use OCA\QuickNotes\Db\NoteMapper;
use OCA\QuickNotes\Service\NoteService;

/**
 * Hourly cleanup that hard-deletes notes which have been in the trash
 * for more than one week. Notes that are still active or just archived
 * are left alone.
 *
 * Notes are soft-deleted (moved to the trash) by `NoteService::trash()`,
 * which sets `deleted_at` on the row. A real DELETE only happens on
 * explicit "Delete permanently" from the trash view. This job takes
 * care of notes the user forgot to purge.
 */
class PurgeOldTrashJob extends TimedJob {

	/** Number of days a note is kept in the trash before being purged. */
	public const RETENTION_DAYS = 7;

	/** @var NoteMapper */
	private $noteMapper;

	/** @var NoteService */
	private $noteService;

	public function __construct(ITimeFactory $time,
	                            NoteMapper   $noteMapper,
	                            NoteService  $noteService) {
		parent::__construct($time);
		$this->noteMapper  = $noteMapper;
		$this->noteService = $noteService;

		// Run once per hour.
		$this->setInterval(60 * 60);
		// Slight clock skew between the cron runner and the DB is fine
		// for a weekly cutoff, so this can run alongside other jobs.
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$cutoff = new \DateTime('-' . self::RETENTION_DAYS . ' days', new \DateTimeZone('GMT'));

		$oldNotes = $this->noteMapper->findOldDeletedNotes($cutoff);

		foreach ($oldNotes as $note) {
			// `NoteService::destroy()` is the same path the trash view
			// uses on "Delete permanently": it removes shares, the row
			// itself, the attachments, and prunes orphan colors.
			$this->noteService->destroy($note->getUserId(), $note->getId());
		}
	}

}
