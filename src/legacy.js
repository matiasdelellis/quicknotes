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
 * Support bundle for the non-Vue part of the app.
 *
 * Nextcloud 34 stopped shipping jQuery (along with jQuery UI, select2 and the
 * `ocdialog` / `octemplate` plugins) to the browser, so the app brings its own
 * copy for `js/script.js` and friends, plus a reimplementation of the dialogs
 * that used to rely on those plugins.
 *
 * It has to be the *first* script of the app on the page: everything under
 * `js/` expects `$` to be a global. `\OCP\Util::addScript()` keeps the order
 * in which the scripts of an app are added, and the tags it renders are
 * deferred, so they run in that same order.
 */

import { getRequestToken } from '@nextcloud/auth'
import $ from 'jquery'

import QnDialogs from './dialogs.js'

// The server used to install this on its own jQuery (see
// core/src/jquery/requesttoken.js up to Nextcloud 33). Without it every
// request of the app comes back as "412 Precondition failed", because it
// carries no CSRF token.
$(document).on('ajaxSend', function (elm, xhr, settings) {
	if (settings.crossDomain === false) {
		xhr.setRequestHeader('requesttoken', getRequestToken())
		xhr.setRequestHeader('OCS-APIREQUEST', 'true')
	}
})

// Servers older than 34 expose their own (deprecated) jQuery as a read-only
// property, and the plugins the app used are registered on it, so leave it be.
if (window.jQuery === undefined) {
	window.jQuery = $
	window.$ = $
}

window.QnDialogs = QnDialogs
