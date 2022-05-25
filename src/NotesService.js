import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'

function url(url) {
	url = `apps/quicknotes${url}`
	return generateUrl(url)
}

export const getDashboardData = () => {
	return axios
		.get(url('/notes/dashboard'))
		.then(response => {
			return response.data
		})
		.catch(err => {
			console.error(err)
			showError(t('notes', 'Fetching notes for dashboard has failed.'))
			throw err
		})
}
