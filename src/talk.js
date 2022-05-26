import Vue from 'vue'

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { postNewNote } from './NotesService.js'

Vue.prototype.t = t
Vue.prototype.n = n
Vue.prototype.OC = OC

window.addEventListener('DOMContentLoaded', () => {
	if (!window.OCA?.Talk?.registerMessageAction) {
		return
	}

	window.OCA.Talk.registerMessageAction({
		label: t('quicknotes', 'Save as a note'),
		icon: 'icon-quicknotes',
		async callback({message: { message, id: messageId, messageParameters, actorDisplayName }, metadata: { name: conversationName, token: conversationToken }}) {
			const parsedMessage = message.replace(/{[a-z0-9-_]+}/gi, function(parameter) {
				const parameterName = parameter.substr(1, parameter.length - 2)
				if (messageParameters[parameterName]) {
					if (messageParameters[parameterName].type === 'file' && messageParameters[parameterName].path) {
						return messageParameters[parameterName].path
					}
					if (messageParameters[parameterName].type === 'user' || messageParameters[parameterName].type === 'call') {
						return '@' + messageParameters[parameterName].name
					}
					if (messageParameters[parameterName].name) {
						return messageParameters[parameterName].name
					}
				}
				// Do not replace so insert with curly braces again
				return parameter
			})

			const title = t('quicknotes', 'Message from {author} in the call {conversationName}', {
				author: actorDisplayName,
				conversationName,
			})

			const callLink = window.location.origin + generateUrl('/call/{conversationToken}#message_{messageId}', { conversationToken, messageId })

			let content = '<p>' + parsedMessage + '</p>'
			content += '<p><br></p>'
			content += '<p><a href="' + callLink + '" rel="noopener noreferrer" target="_blank">' + callLink + '</a></p>'

			postNewNote(title, content).then(data => {
				const noteUrl = generateUrl('apps/quicknotes/?n={noteId}', {noteId: data.id})
				showSuccess(t('quicknotes', 'Message saved as a new note. <a target="_blank" rel="noreferrer noopener" href="{link}" >See that. ↗</a>', {
					link: noteUrl,
				}), {
					isHTML: true,
				})
			})
		},
	})
})
