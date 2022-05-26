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
			showError(t('quicknotes', 'There was an error fetching your notes for the dashboard'))
			throw err
		})
}

export const postNewNote = (title, content) => {
	return axios
		.post(url('/notes'), {
			title: title,
			content: content,
		})
		.then(response => {
			return response.data
		})
		.catch(err => {
			console.error(err)
			showError(t('quicknotes', 'There was an error saving the note'))
			throw err
		})
}
