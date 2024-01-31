<template>
	<DashboardWidget :items="items"
		:loading="loading"
	>
		<template #default="{ item }">
			<DashboardWidgetItem
				:target-url="getItemTargetUrl(item)"
				:main-text="item.title"
				:sub-text="item.content"
			>
				<template #avatar>
					<div
						class="note-item"
						:class="{ 'dashboard-pinned-icon': item.pinned, 'note-item-no-pinned': !hasPinned }"
					/>
				</template>
			</DashboardWidgetItem>
		</template>
		<template #empty-content>
			<EmptyContent icon="icon-quicknotes">
				<template #desc>
					<p class="notes-empty-content-label">
						{{ t('quicknotes', 'Nothing here. Take your first quick notes') }}
					</p>
					<p>
						<a :href="createNoteUrl" class="button">{{ t('quicknotes', 'Create a note…') }}</a>
					</p>
				</template>
			</EmptyContent>
		</template>
	</DashboardWidget>
</template>

<script>
import { DashboardWidget, DashboardWidgetItem } from '@nextcloud/vue-dashboard'
import { EmptyContent } from '@nextcloud/vue'
import { generateUrl } from '@nextcloud/router'

import { getDashboardData } from '../NotesService.js'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
		DashboardWidgetItem,
		EmptyContent,
	},

	data() {
		return {
			loading: true,
			items: [],
		}
	},

	computed: {
		hasPinned() {
			return this.items.length > 0 && this.items[0].pinned
		},

		createNoteUrl() {
			return generateUrl('/apps/quicknotes')
		},

		getItemTargetUrl() {
			return (note) => {
				return generateUrl('/apps/quicknotes/?n=' + note.id)
			}
		},
	},

	created() {
		this.loadDashboardData()
	},

	methods: {
		loadDashboardData() {
			getDashboardData().then(data => {
				this.items = data.notes
				this.loading = false
			})
		},
	},

}
</script>
<style scoped>

.note-item {
	width: 44px;
	height: 44px;
	line-height: 44px;
	flex-shrink: 0;
	background-size: 50%;
	background-repeat: no-repeat;
	background-position: center;
}

.note-item-no-pinned {
	display: none;
}

.notes-empty-content-label {
	margin-bottom: 20px;
}
</style>
