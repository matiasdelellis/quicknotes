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

/**
 * The user may see this note, but not do this to it.
 *
 * Deliberately distinct from "not found": a note nobody shared with you does
 * not exist as far as you are concerned (404), while a note you were given to
 * read but not to write is a 403 — telling you so gives nothing away that the
 * share did not already give you.
 */
class ForbiddenException extends \RuntimeException {
}
