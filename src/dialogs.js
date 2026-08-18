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
 * The dialogs of the note editor.
 *
 * The tags and shares ones used to be built with select2 and the `ocdialog`
 * jQuery plugin, both of which the server shipped until Nextcloud 33 and
 * dropped in 34. This is the same API — `QnDialogs.tags()` /
 * `QnDialogs.shares()` — reimplemented with the Nextcloud Vue components, so
 * `js/script.js` did not have to change. `QnDialogs.reminder()` is new, and
 * follows the same shape.
 *
 * Every date crossing this boundary is a UTC 'Y-m-d H:i:s' string, the format
 * the reminder endpoint stores. Converting to and from the timezone of the
 * browser happens here and nowhere else, so `js/script.js` never has to think
 * about it.
 */

import { showError } from '@nextcloud/dialogs'
import { getCanonicalLocale } from '@nextcloud/l10n'
import Vue from 'vue'

import QnReminderDialog from './components/QnReminderDialog.vue'
import QnSelectDialog from './components/QnSelectDialog.vue'
import QnShareDialog from './components/QnShareDialog.vue'

Vue.mixin({ methods: { t, n } })

/**
 * Mount a dialog, and tear it down again once it is closed.
 *
 * @param {object} component the dialog component to mount
 * @param {object} propsData properties for that component
 * @param {Function} onSubmit called with the result on "Done"
 * @param {Function} onCancel called when the dialog is dismissed
 * @param {Function} [onRemove] called when a link is to be removed
 */
function openDialog(component, propsData, onSubmit, onCancel, onRemove) {
	const container = document.createElement('div')
	document.body.appendChild(container)

	const dialog = new (Vue.extend(component))({ propsData })

	// All handlers are wired to the same teardown, and only one of them can
	// ever run: the first one takes the dialog off the page.
	const close = (handler) => {
		return (selected) => {
			// $mount() replaced the container with the root element of the
			// component, so that is what has to go.
			const el = dialog.$el
			dialog.$destroy()
			el.remove()
			handler(selected)
		}
	}

	dialog.$on('submit', close(onSubmit))
	dialog.$on('cancel', close(onCancel))
	if (onRemove) {
		dialog.$on('remove', close(onRemove))
	}

	dialog.$mount(container)

	return dialog
}

/** Mount a QnSelectDialog. */
function open(propsData, onSubmit, onCancel, onRemove) {
	return openDialog(QnSelectDialog, propsData, onSubmit, onCancel, onRemove)
}

/**
 * A Date to the UTC string the backend stores. toISOString() is already UTC,
 * so this only reshapes it: 'YYYY-MM-DDTHH:MM:SS.sssZ' to 'YYYY-MM-DD HH:MM:SS'.
 *
 * @param {Date} date the instant to store
 * @return {string} UTC 'Y-m-d H:i:s'
 */
function dateToUtcString(date) {
	return date.toISOString().slice(0, 19).replace('T', ' ')
}

/**
 * The stored UTC string back into a Date, i.e. back into the timezone of the
 * browser.
 *
 * @param {string|null} value UTC 'Y-m-d H:i:s'
 * @return {Date|null} null if there is no reminder
 */
function utcStringToDate(value) {
	if (!value) {
		return null
	}
	// The explicit 'Z' is what stops the browser from reading it as local time.
	const date = new Date(value.replace(' ', 'T') + 'Z')
	return isNaN(date.getTime()) ? null : date
}

/** Tags of a note, as stored by the app, to dropdown entries. */
function tagToEntry(tag) {
	// A tag that only exists in the editor (one the user just typed) has no
	// numeric id yet, and the backend identifies it by name.
	const id = (tag.id === undefined || tag.id === null) ? tag.name : tag.id
	return { id, label: tag.name }
}

/** Dropdown entries back to the shape `js/script.js` expects for tags. */
function entryToTag(entry) {
	return { id: entry.id, name: entry.label }
}

const QnDialogs = {

	/**
	 * Report an error to the user.
	 *
	 * This used to be `OC.dialogs.alert()`, which is broken in Nextcloud 34:
	 * it asks its own message() for a button set that no longer exists, logs
	 * "Invalid call to OC.dialogs" and shows nothing at all.
	 *
	 * @param {string} text message to show
	 */
	error(text) {
		showError(text)
	},

	/**
	 * Pick the tags of a note.
	 *
	 * @param {Array} currentTags every tag known to the user, as {id, name}
	 * @param {Array} selectedTags tags of the note, as {id, name}
	 * @param {Function} callback called as `callback(confirmed, tags)`
	 */
	tags(currentTags, selectedTags, callback) {
		return open({
			title: t('quicknotes', 'Tag the note'),
			message: t('quicknotes', 'Enter tags to organize your note'),
			placeholder: t('quicknotes', 'Enter tag name'),
			noResultText: t('quicknotes', 'No tags found'),
			initialOptions: currentTags.map(tagToEntry),
			initialSelected: selectedTags.map(tagToEntry),
			taggable: true,
		},
		(selected) => callback(true, selected.map(entryToTag)),
		() => callback(false, []))
	},

	/**
	 * Manage who a note is shared with, and what they may do with it.
	 *
	 * Unlike the other dialogs here, this one does not hand a result back for
	 * the caller to save: it writes every change through the share endpoints
	 * as the user makes it. The callback is only told what the shares ended up
	 * being, so the badges of the note can be redrawn.
	 *
	 * @param {number} noteId id of the note being shared
	 * @param {Array} currentShares shares the editor already knows about
	 * @param {boolean} canShare whether this user may share the note at all
	 * @param {Function} callback called as `callback(shares)` when it closes
	 */
	shares(noteId, currentShares, canShare, callback) {
		return openDialog(QnShareDialog, {
			noteId,
			initialShares: currentShares || [],
			canShare,
		},
		(shares) => callback(shares),
		() => callback(null))
	},

	/**
	 * Pick when a note should remind the user.
	 *
	 * @param {string|null} currentReminder the reminder of the note, as the
	 *        UTC 'Y-m-d H:i:s' string the backend stores, or null if it has none
	 * @param {Function} callback called as `callback(confirmed, reminderAt)`,
	 *        where reminderAt is a UTC 'Y-m-d H:i:s' string, or null to cancel
	 *        the reminder
	 */
	reminder(currentReminder, callback) {
		const current = utcStringToDate(currentReminder)

		// A note being written now is rarely about the next few minutes, so an
		// existing reminder aside, default to tomorrow morning.
		let initialDate = current
		if (initialDate === null || initialDate.getTime() < Date.now()) {
			initialDate = new Date()
			initialDate.setDate(initialDate.getDate() + 1)
			initialDate.setHours(9, 0, 0, 0)
		}

		return openDialog(QnReminderDialog, {
			title: t('quicknotes', 'Remind me about this note'),
			message: t('quicknotes', 'Pick the date and time you want to be notified.'),
			// Delivery rides on the background jobs of the server, so promising
			// the minute the user picked would be a lie.
			hint: t('quicknotes', 'The notification is sent by the server in the background, so it may arrive a few minutes later.'),
			initialDate,
			hasReminder: current !== null,
		},
		(date) => callback(true, date === null ? null : dateToUtcString(date)),
		() => callback(false, null))
	},

	/**
	 * Pick a single note for a wikilink from the current one.
	 *
	 * With `options.currentId` set the dialog edits an existing link instead of
	 * inserting a new one: the target note comes preselected and a "Remove
	 * link" action is offered. The callback then receives the picked note as
	 * {id, label}, `false` to remove the link, or `null` if the user cancelled.
	 *
	 * @param {Array} currentNotes every linkable note, as {id, label}
	 * @param {Function} callback called as `callback(note)` with the picked
	 *        note as {id, label}, `false` for "remove link", or null to cancel
	 * @param {object} [options]
	 * @param {string|number} [options.currentId] id of the note the link
	 *        currently points at, to preselect it
	 * @param {string} [options.currentLabel] label of that note, used when it
	 *        no longer exists so the dialog still shows what the link points at
	 */
	linkNote(currentNotes, callback, options = {}) {
		const editing = options.currentId !== undefined && options.currentId !== null
		let initialSelected = []
		if (editing) {
			const match = currentNotes.filter(n => String(n.id) === String(options.currentId))
			initialSelected = match.length
				? [match[0]]
				// The target note may have been deleted; keep it on screen
				// anyway so the dialog still shows what the link points at.
				: [{ id: options.currentId, label: options.currentLabel }]
		}
		return open({
			title: editing
				? t('quicknotes', 'Edit the link to a note')
				: t('quicknotes', 'Link to a note'),
			message: editing
				? t('quicknotes', 'Select the note to link, or remove the link')
				: t('quicknotes', 'Select the note to link'),
			placeholder: t('quicknotes', 'Select a note'),
			noResultText: t('quicknotes', 'No notes found'),
			initialOptions: currentNotes,
			initialSelected,
			multiple: false,
			removeLabel: editing ? t('quicknotes', 'Remove link') : '',
		},
		(selected) => callback(selected.length ? selected[0] : null),
		() => callback(null),
		() => callback(false))
	},

	/**
	 * A stored reminder as something to show the user, in their locale and
	 * their timezone.
	 *
	 * @param {string|null} reminderAt UTC 'Y-m-d H:i:s'
	 * @return {string} empty if there is no reminder
	 */
	formatReminder(reminderAt) {
		const date = utcStringToDate(reminderAt)
		if (date === null) {
			return ''
		}

		return date.toLocaleString(getCanonicalLocale(), {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		})
	},

}

export default QnDialogs
