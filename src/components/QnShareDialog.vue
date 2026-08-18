<!--
  - @copyright 2026 Matias De lellis <mati86dl@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - The share dialog of a note.
  -
  - It replaces a multiselect of user names whose result was handed back to the
  - editor and only written when the note itself was saved. Two things were
  - wrong with that beyond the looks: a share was lost by closing the note
  - without saving, and the list the editor held could revoke, on save, a share
  - somebody had made in the meantime.
  -
  - So this one talks to the share endpoints directly and every action here has
  - already happened by the time it is on screen. Which is also what makes the
  - permissions possible: "can edit" is a property of one share, not something
  - that fits in a list of user ids.
  -->
<template>
	<Modal class="qn-share-modal"
		:title="title"
		:can-close="true"
		size="small"
		@close="close">
		<div class="qn-share">
			<p class="qn-share__message">
				{{ message }}
			</p>

			<Multiselect v-if="canShare"
				v-model="picked"
				class="qn-share__select"
				:options="candidates"
				:loading="loading"
				:multiple="false"
				:internal-search="false"
				:clear-on-select="true"
				:close-on-select="true"
				:preserve-search="false"
				:max-height="240"
				:placeholder="placeholder"
				:user-select="true"
				label="label"
				track-by="key"
				@search-change="onSearch"
				@select="add">
				<template #noResult>
					<span>{{ t('quicknotes', 'No user or group found') }}</span>
				</template>
				<template #noOptions>
					<span>{{ t('quicknotes', 'Start typing to find someone') }}</span>
				</template>
			</Multiselect>

			<ul v-if="shares.length" class="qn-share__list">
				<li v-for="share in shares" :key="share.id" class="qn-share__entry">
					<Avatar class="qn-share__avatar"
						:user="share.shareWith"
						:display-name="share.displayName"
						:is-no-user="isGroup(share)"
						:disable-menu="true"
						:size="32" />
					<span class="qn-share__name">
						<span class="qn-share__label">{{ share.displayName }}</span>
						<span class="qn-share__hint">{{ describe(share) }}</span>
					</span>
					<Actions :disabled="busy.indexOf(share.id) !== -1"
						:aria-label="t('quicknotes', 'Sharing options')">
						<ActionRadio :name="'qn-perm-' + share.id"
							value="view"
							:checked="!share.canEdit"
							@change="setEditable(share, false)">
							{{ t('quicknotes', 'Can view') }}
						</ActionRadio>
						<ActionRadio :name="'qn-perm-' + share.id"
							value="edit"
							:checked="share.canEdit"
							@change="setEditable(share, true)">
							{{ t('quicknotes', 'Can edit') }}
						</ActionRadio>
						<ActionSeparator />
						<ActionCheckbox :checked="share.canReshare"
							@change="setResharable(share, !share.canReshare)">
							{{ t('quicknotes', 'Can reshare') }}
						</ActionCheckbox>
						<ActionSeparator />
						<ActionButton @click="remove(share)">
							{{ t('quicknotes', 'Unshare') }}
						</ActionButton>
					</Actions>
				</li>
			</ul>
			<p v-else class="qn-share__empty">
				{{ t('quicknotes', 'This note is not shared with anyone yet.') }}
			</p>

			<div class="qn-share__buttons">
				<Button type="primary" @click="close">
					{{ t('quicknotes', 'Done') }}
				</Button>
			</div>
		</div>
	</Modal>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import {
	Actions,
	ActionButton,
	ActionCheckbox,
	ActionRadio,
	ActionSeparator,
	Avatar,
	Button,
	Modal,
	Multiselect,
} from '@nextcloud/vue'

import {
	PERMISSION_READ,
	PERMISSION_SHARE,
	PERMISSION_UPDATE,
	TYPE_GROUP,
	createShare,
	deleteShare,
	errorMessage,
	getShares,
	searchSharees,
	updateShare,
} from '../SharesService.js'

export default {
	name: 'QnShareDialog',

	components: {
		Actions,
		ActionButton,
		ActionCheckbox,
		ActionRadio,
		ActionSeparator,
		Avatar,
		Button,
		Modal,
		Multiselect,
	},

	props: {
		/** The note being shared. Shares are written the moment they change,
		 * so this dialog is only ever opened on a note that exists. */
		noteId: {
			type: Number,
			required: true,
		},
		/** Shares as the editor already knows them, so the list is on screen
		 * before the request that refreshes it comes back. */
		initialShares: {
			type: Array,
			default: () => [],
		},
		/** Whether the user may share this note at all. */
		canShare: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			title: t('quicknotes', 'Share note'),
			message: t('quicknotes', 'People you share this note with see it in their own Quick notes, attachments included. The files themselves stay in your Files and are not shared there.'),
			placeholder: t('quicknotes', 'Name or group…'),
			shares: this.initialShares.slice(),
			candidates: [],
			picked: null,
			loading: false,
			searchTimer: null,
			// Ids of the shares with a request in flight, so the menu of that
			// row cannot be used to fire a second one on top of it.
			busy: [],
		}
	},

	mounted() {
		// The editor's copy of the list may be a few minutes old, and the note
		// may have been shared from another tab in the meantime.
		getShares(this.noteId)
			.then(shares => {
				this.shares = shares
			})
			.catch(() => {})
	},

	beforeDestroy() {
		clearTimeout(this.searchTimer)
	},

	methods: {
		isGroup(share) {
			return share.shareType === TYPE_GROUP
		},

		/** What this share lets its recipient do, in one line. */
		describe(share) {
			if (share.canEdit && share.canReshare) {
				return t('quicknotes', 'Can edit and reshare')
			}
			if (share.canEdit) {
				return t('quicknotes', 'Can edit')
			}
			if (share.canReshare) {
				return t('quicknotes', 'Can view and reshare')
			}
			return t('quicknotes', 'Can view')
		},

		onSearch(term) {
			clearTimeout(this.searchTimer)

			if (term === '') {
				this.candidates = []
				this.loading = false
				return
			}

			this.searchTimer = setTimeout(() => {
				this.loading = true
				searchSharees(this.noteId, term)
					.then(sharees => {
						this.candidates = sharees.map(sharee => {
							const isGroup = sharee.shareType === TYPE_GROUP
							return {
								key: sharee.shareType + ':' + sharee.shareWith,
								label: isGroup
									? t('quicknotes', '{group} (group)', { group: sharee.label })
									: sharee.label,
								shareType: sharee.shareType,
								shareWith: sharee.shareWith,
								// What the user-select rendering of the
								// Multiselect reads to draw the avatar.
								user: sharee.shareWith,
								displayName: sharee.label,
								isNoUser: isGroup,
							}
						})
					})
					.catch(() => {
						this.candidates = []
					})
					.then(() => {
						this.loading = false
					})
			}, 300)
		},

		/**
		 * Share the note with whoever was picked.
		 *
		 * New shares are read only, the way every share of this app was until
		 * now. Handing out write access is a decision, so it is made in the
		 * menu of the entry rather than by picking a name.
		 *
		 * @param {object} candidate the entry picked in the dropdown
		 */
		add(candidate) {
			if (!candidate) {
				return
			}

			// The picker is a means to an end here: the entry belongs to the
			// list below the moment the server confirms it.
			this.$nextTick(() => {
				this.picked = null
			})
			this.candidates = []

			createShare(this.noteId, candidate.shareType, candidate.shareWith, PERMISSION_READ)
				.then(share => {
					this.shares = this.shares.concat([share])
				})
				.catch(error => {
					showError(errorMessage(error, t('quicknotes', 'Could not share the note')))
				})
		},

		setEditable(share, editable) {
			if (share.canEdit === editable) {
				return
			}
			const permissions = editable
				? share.permissions | PERMISSION_UPDATE
				: share.permissions & ~PERMISSION_UPDATE
			this.applyPermissions(share, permissions)
		},

		setResharable(share, resharable) {
			const permissions = resharable
				? share.permissions | PERMISSION_SHARE
				: share.permissions & ~PERMISSION_SHARE
			this.applyPermissions(share, permissions)
		},

		applyPermissions(share, permissions) {
			this.busy = this.busy.concat([share.id])

			updateShare(share.id, permissions | PERMISSION_READ)
				.then(updated => {
					this.shares = this.shares.map(entry => entry.id === updated.id ? updated : entry)
				})
				.catch(error => {
					showError(errorMessage(error, t('quicknotes', 'Could not change the share')))
				})
				.then(() => {
					this.busy = this.busy.filter(id => id !== share.id)
				})
		},

		remove(share) {
			this.busy = this.busy.concat([share.id])

			deleteShare(share.id)
				.then(() => {
					this.shares = this.shares.filter(entry => entry.id !== share.id)
				})
				.catch(error => {
					showError(errorMessage(error, t('quicknotes', 'Could not remove the share')))
				})
				.then(() => {
					this.busy = this.busy.filter(id => id !== share.id)
				})
		},

		/**
		 * Nothing is pending here, so closing only hands the list back for the
		 * badges of the note to be redrawn.
		 */
		close() {
			this.$emit('submit', this.shares)
		},
	},
}
</script>

<!--
  - Not scoped, for the same reason as QnSelectDialog: the modal clips its
  - content and the dropdown of the select opens over the edge of it.
  -->
<style lang="scss">
.qn-share-modal .modal-wrapper .modal-container {
	overflow: visible !important;
}
</style>

<style lang="scss" scoped>
.qn-share {
	display: flex;
	flex-direction: column;
	padding: 20px;

	&__message {
		margin-bottom: 12px;
		color: var(--color-text-maxcontrast);
	}

	&__select {
		width: 100%;
	}

	&__list {
		margin-top: 8px;
		// Enough for half a dozen entries; a note shared with a whole
		// department scrolls instead of pushing the buttons off screen.
		max-height: 280px;
		overflow-y: auto;
	}

	&__entry {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 4px 0;
	}

	&__avatar {
		flex: 0 0 32px;
	}

	&__name {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		min-width: 0;
	}

	&__label {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__hint {
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}

	&__empty {
		margin: 12px 0;
		color: var(--color-text-maxcontrast);
		text-align: center;
	}

	&__buttons {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
		margin-top: auto;
		padding-top: 20px;
	}
}
</style>
