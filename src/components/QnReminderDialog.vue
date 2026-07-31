<!--
  - @copyright 2026 Matias De lellis <mati86dl@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - "When should this note remind me?" dialog. Companion of QnSelectDialog,
  - built on the Nextcloud Vue DatetimePicker so the app does not need a date
  - picker of its own — Nextcloud 34 removed jQuery UI along with jQuery.
  -->
<template>
	<Modal class="qn-reminder-modal"
		:title="title"
		:can-close="true"
		size="small"
		@close="cancel">
		<div class="qn-reminder">
			<p v-if="message" class="qn-reminder__message">
				{{ message }}
			</p>
			<DatetimePicker v-model="date"
				class="qn-reminder__picker"
				type="datetime"
				:minute-step="5"
				:clearable="false"
				:append-to-body="true"
				:disabled-date="isPastDay"
				:disabled-time="isPastTime" />
			<p v-if="hint" class="qn-reminder__hint">
				{{ hint }}
			</p>
			<div class="qn-reminder__buttons">
				<Button v-if="hasReminder" @click="remove">
					{{ t('quicknotes', 'Remove') }}
				</Button>
				<Button @click="cancel">
					{{ t('quicknotes', 'Cancel') }}
				</Button>
				<Button type="primary" :disabled="!date" @click="submit">
					{{ t('quicknotes', 'Done') }}
				</Button>
			</div>
		</div>
	</Modal>
</template>

<script>
import { Button, DatetimePicker, Modal } from '@nextcloud/vue'

export default {
	name: 'QnReminderDialog',

	components: {
		Button,
		DatetimePicker,
		Modal,
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
		/** Small print below the picker, e.g. the accuracy caveat. */
		hint: {
			type: String,
			default: '',
		},
		/** Date the picker opens on. Never null: the caller picks a default. */
		initialDate: {
			type: Date,
			required: true,
		},
		/** Whether the note already has a reminder, i.e. whether it can be removed. */
		hasReminder: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			date: this.initialDate,
		}
	},

	methods: {
		/** Whole days before today cannot be picked. */
		isPastDay(date) {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			return date < today
		},

		/**
		 * Neither can times that have already gone by — which only ever
		 * excludes anything on the current day.
		 */
		isPastTime(date) {
			return date.getTime() < Date.now()
		},

		submit() {
			this.$emit('submit', this.date)
		},

		/** Confirm with no date at all, which the caller reads as "cancel it". */
		remove() {
			this.$emit('submit', null)
		},

		cancel() {
			this.$emit('cancel')
		},
	},
}
</script>

<!--
  - Not scoped: the calendar popup is rendered outside this component.
  -
  - `append-to-body` moves it to the body so the modal, which clips its content
  - (`overflow: auto`), cannot cut it in half — the same problem QnSelectDialog
  - solves the other way round. But out there it has to compete with the modal,
  - whose stacking goes up to 100000, and @nextcloud/vue already pins the popup
  - down with
  -
  -     .mx-datepicker-main { &.mx-datepicker-popup { z-index: 2000; } }
  -
  - Two classes, so a plain `.mx-datepicker-popup` loses on specificity no
  - matter where it lands in the sheet, and the popup renders *behind* the
  - dialog. Hence both the doubled selector and the `!important`: the styles
  - are injected at runtime by style-loader (no CSS file is emitted), so the
  - order between the two is not something this file gets to decide.
  -->
<style lang="scss">
.mx-datepicker-main.mx-datepicker-popup {
	z-index: 100001 !important;
}
</style>

<style lang="scss" scoped>
.qn-reminder {
	display: flex;
	flex-direction: column;
	padding: 20px;

	&__message {
		margin-bottom: 12px;
	}

	&__picker {
		width: 100%;
	}

	&__hint {
		margin-top: 8px;
		font-size: 90%;
		opacity: 0.7;
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
