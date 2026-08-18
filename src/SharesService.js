/*
 * @copyright 2026 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2026 Matias De lellis <mati86dl@gmail.com>
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

/**
 * The share endpoints of a note.
 *
 * Every call here changes something the moment it is made: sharing a note is
 * not part of saving it. That is the whole reason these live outside
 * `js/notes-api.js`, which speaks the "one big note payload" language of the
 * editor.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** Share types, mirroring NoteShare::TYPE_* (and IShare::TYPE_*). */
export const TYPE_USER = 0
export const TYPE_GROUP = 1

/** Permissions, mirroring NoteShare::PERMISSION_* (and OCP\Constants). */
export const PERMISSION_READ = 1
export const PERMISSION_UPDATE = 2
export const PERMISSION_SHARE = 16

/**
 * Build an app url.
 *
 * @param {string} path path inside the app
 * @return {string} absolute url
 */
function url(path) {
	return generateUrl(`apps/quicknotes${path}`)
}

/**
 * The message the server sent with an error, if it bothered to send one.
 *
 * @param {object} error the axios error
 * @param {string} fallback message to use when there is nothing better
 * @return {string} something worth showing the user
 */
export function errorMessage(error, fallback) {
	return error?.response?.data?.message || fallback
}

/**
 * Every share of a note.
 *
 * @param {number} noteId id of the note
 * @return {Promise<Array>} the shares
 */
export function getShares(noteId) {
	return axios.get(url(`/notes/${noteId}/shares`)).then(response => response.data)
}

/**
 * Share a note with one user or one group.
 *
 * @param {number} noteId id of the note
 * @param {number} shareType TYPE_USER or TYPE_GROUP
 * @param {string} shareWith uid or gid
 * @param {number} permissions bitmask of PERMISSION_*
 * @return {Promise<object>} the share that was created
 */
export function createShare(noteId, shareType, shareWith, permissions) {
	return axios.post(url(`/notes/${noteId}/shares`), {
		shareType,
		shareWith,
		permissions,
	}).then(response => response.data)
}

/**
 * Change what a share grants.
 *
 * @param {number} shareId id of the share
 * @param {number} permissions bitmask of PERMISSION_*
 * @return {Promise<object>} the share as it now stands
 */
export function updateShare(shareId, permissions) {
	return axios.put(url(`/shares/${shareId}`), { permissions })
		.then(response => response.data)
}

/**
 * Take a share back.
 *
 * @param {number} shareId id of the share
 * @return {Promise} resolved once it is gone
 */
export function deleteShare(shareId) {
	return axios.delete(url(`/shares/${shareId}`))
}

/**
 * Who this note could still be shared with.
 *
 * @param {number} noteId id of the note
 * @param {string} search what the user typed
 * @return {Promise<Array>} sharees as {shareType, shareWith, label, subline}
 */
export function searchSharees(noteId, search) {
	return axios.get(url(`/notes/${noteId}/sharees`), { params: { search } })
		.then(response => response.data)
}
