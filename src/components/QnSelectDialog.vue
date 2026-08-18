<!--
  - @copyright 2026 Matias De lellis <mati86dl@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - Generic "pick some entries and confirm" dialog. It replaces the select2
  - based dialogs the app used to build on top of the jQuery plugins shipped
  - by the server, which were removed in Nextcloud 34.
  -->
<template>
	<Modal class="qn-dialog-modal"
		:title="title"
		:can-close="true"
		size="small"
		@close="cancel">
		<div class="qn-dialog" :class="{ 'qn-dialog--picking': picking }">
			<p v-if="message" class="qn-dialog__message">
				{{ message }}
			</p>
			<Multiselect v-model="selected"
				class="qn-dialog__select"
				:options="options"
				:multiple="multiple"
				:taggable="taggable"
				:loading="loading"
				:max-height="240"
				open-direction="above"
				:close-on-select="!multiple"
				:clear-on-select="!multiple"
				:placeholder="placeholder"
				:user-select="userSelect"
				label="label"
				track-by="id"
				@tag="addEntry"
				@search-change="onSearch"
				@open="picking = true"
				@close="picking = false">
				<template #noResult>
					<span>{{ noResultText }}</span>
				</template>
			</Multiselect>
			<div class="qn-dialog__buttons">
				<Button v-if="removeLabel" @click="remove">
					{{ removeLabel }}
				</Button>
				<Button @click="cancel">
					{{ t('quicknotes', 'Cancel') }}
				</Button>
				<Button type="primary" @click="submit">
					{{ t('quicknotes', 'Done') }}
				</Button>
			</div>
		</div>
	</Modal>
</template>

<script>
import { Button, Modal, Multiselect } from '@nextcloud/vue'

export default {
	name: 'QnSelectDialog',

	components: {
		Button,
		Modal,
		Multiselect,
	},

	props: {
		title: {
			type: String,
			default: '',
		},
		message: {
			type: String,
			default: '',
		},
		placeholder: {
			type: String,
			default: '',
		},
		noResultText: {
			type: String,
			default: '',
		},
		/** Entries offered in the dropdown, as {id, label} objects. */
		initialOptions: {
			type: Array,
			default: () => [],
		},
		/** Entries selected when the dialog opens, as {id, label} objects. */
		initialSelected: {
			type: Array,
			default: () => [],
		},
		/** Whether the user can create entries that are not in the list. */
		taggable: {
			type: Boolean,
			default: false,
		},
		/** Pick several entries (tags, users) or exactly one (a note to link). */
		multiple: {
			type: Boolean,
			default: true,
		},
		/** Render the entries as users, with their avatar. */
		userSelect: {
			type: Boolean,
			default: false,
		},
		/**
		 * Label of a "remove" action shown next to Cancel/Done. When set, the
		 * dialog is editing an existing link and offers to drop it.
		 */
		removeLabel: {
			type: String,
			default: '',
		},
		/**
		 * Optional search callback, `fn(term)` returning a promise of
		 * {id, label} objects. Results are added to the offered entries;
		 * the list itself is still filtered in the browser.
		 */
		searchFn: {
			type: Function,
			default: null,
		},
	},

	data() {
		return {
			options: this.initialOptions.slice(),
			// A single-select (a note to link) holds one entry or null; the
			// multi-select keeps an array, matching vue-multiselect's model.
			selected: this.multiple
				? this.initialSelected.slice()
				: (this.initialSelected[0] || null),
			loading: false,
			searchTimer: null,
			// True while the dropdown of the select is open.
			picking: false,
		}
	},

	mounted() {
		// The selection may reference entries that are not part of the
		// offered list (a tag of the note that no longer exists anywhere
		// else, a user that the sharees API did not return), so make sure
		// every selected entry is a valid option too.
		this.mergeOptions(this.selected)
	},

	beforeDestroy() {
		clearTimeout(this.searchTimer)
	},

	methods: {
		/** Add entries to the dropdown, skipping the ones already known. */
		mergeOptions(entries) {
			if (!entries) {
				return
			}
			const known = new Set(this.options.map(option => String(option.id)))
			entries.forEach(entry => {
				if (!known.has(String(entry.id))) {
					known.add(String(entry.id))
					this.options.push(entry)
				}
			})
		},

		/** Create and select an entry from the typed text (tags only). */
		addEntry(label) {
			const entry = { id: label, label }
			this.mergeOptions([entry])
			this.selected = this.selected.concat([entry])
		},

		onSearch(term) {
			if (this.searchFn === null) {
				return
			}

			clearTimeout(this.searchTimer)

			if (term === '') {
				this.loading = false
				return
			}

			this.searchTimer = setTimeout(() => {
				this.loading = true
				this.searchFn(term)
					.then(entries => this.mergeOptions(entries))
					.catch(() => {})
					.then(() => {
						this.loading = false
					})
			}, 300)
		},

		submit() {
			// Single-select models one entry (or null); callers always get an
			// array so the callback shape stays the same either way.
			const result = this.multiple
				? this.selected
				: (this.selected === null || this.selected === undefined
					? []
					: [this.selected])
			this.$emit('submit', result)
		},

		cancel() {
			this.$emit('cancel')
		},
	},
}
</script>

<!--
  - Not scoped: the modal renders the box around this component, and it clips
  - its content (`overflow: auto`). The dropdown of the select opens upwards,
  - outside that box, so it would come out cut in half. The dialogs are short
  - enough that they never need the scrolling this gives up.
  -->
<style lang="scss">
/* `!important` because the rule of the modal itself carries the scoping
   attribute of the component, and so wins on specificity. */
.qn-dialog-modal .modal-wrapper .modal-container {
	overflow: visible !important;
}
</style>

<style lang="scss" scoped>
.qn-dialog {
	display: flex;
	flex-direction: column;
	padding: 20px;

	// The dropdown opens upwards (open-direction on the Multiselect), over the
	// message: below the input are the buttons, and a dropdown covering them
	// would leave the dialog with no way out. This also keeps the dialog as
	// small as its content — nothing is reserved, nothing reflows while the
	// user picks.
	&--picking ::v-deep .multiselect__content-wrapper {
		box-shadow: 0 -2px 8px var(--color-box-shadow);
	}

	&__message {
		margin-bottom: 12px;
	}

	&__select {
		width: 100%;
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
