<?php declare(strict_types=1);
/*
 * @copyright 2026 Matias De lellis <mati86dl@gmail.com>
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

namespace OCA\QuickNotes\Exception;

use OCA\QuickNotes\Db\Note;

/**
 * The note changed since the client last read it.
 *
 * Thrown when an update carries an `If-Match` that no longer matches the note,
 * which is what stops two people editing the same shared note from silently
 * overwriting each other. It carries the note as it stands now, so the client
 * can show what it would have overwritten instead of just failing.
 */
class ConflictException extends \RuntimeException {

	/** @var Note */
	private $note;

	public function __construct(Note $note, string $message = 'The note was modified elsewhere') {
		parent::__construct($message);
		$this->note = $note;
	}

	public function getNote(): Note {
		return $this->note;
	}

}
