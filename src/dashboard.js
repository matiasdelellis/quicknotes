import Vue from 'vue'
import Dashboard from './components/Dashboard.vue'

Vue.mixin({ methods: { t, n } })

document.addEventListener('DOMContentLoaded', () => {
	OCA.Dashboard.register('quicknotes', (el) => {
		const View = Vue.extend(Dashboard)
		new View().$mount(el)
	})
})
