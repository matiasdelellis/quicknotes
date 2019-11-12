/*
 * @copyright 2019 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2019 Matias De lellis <mati86dl@gmail.com>
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
 * this class to ease the usage of jquery dialogs
 */
const QnDialogs = {

	tags: function (currentTags, selectedTags, callback) {
		return $.when(this._getMessageTemplate()).then(function ($tmpl) {
			var dialogName = 'qn-dialog-content';
			var dialogId = '#' + dialogName;
			var $dlg = $tmpl.octemplate({
				dialog_name: dialogName,
				title: t('quicknotes', 'Tag note'),
				message: t('quicknotes', 'Enter tags to organize your note'),
				type: 'none'
			});

			var input = $('<input/>');
			input.attr('type', 'text');
			input.attr('id', dialogName + '-input');
			input.attr('multiple', 'multiple');

			$dlg.append(input);
			$('body').append($dlg);

			input.select2({
				placeholder: t('quicknotes', 'Enter tag name'),
				tokenSeparators: ',',
				multiple: false,
				allowClear: true,
				toggleSelect: true,
				tags : function () {
					var data = [];
					currentTags.forEach(function (item, index) {
						data.push({id: item.id, text: item.name});
					});
					return data;
				}
			});

			input.val(selectedTags.map(function (value) { return value.id; }));
			input.trigger("change");

			// wrap callback in _.once():
			// only call callback once and not twice (button handler and close
			// event) but call it for the close event, if ESC or the x is hit
			if (callback !== undefined) {
				callback = _.once(callback);
			}

			var buttonlist = [{
				text: t('quicknotes', 'Cancel'),
				click: function () {
					input.select2('close');
					if (callback !== undefined) {
						callback(false, input.select2("data"));
					}
					$(dialogId).ocdialog('close');
				}
			}, {
				text: t('quicknotes', 'Done'),
				click: function () {
					input.select2('close');
					if (callback !== undefined) {
						callback(true, input.select2("data"));
					}
					$(dialogId).ocdialog('close');
				},
				defaultButton: true
			}
			];

			$(dialogId).ocdialog({
				closeOnEscape: true,
				modal: true,
				buttons: buttonlist,
				close: function () {
					// callback is already fired if Yes/No is clicked directly
					if (callback !== undefined) {
						callback(false, input.val());
					}
				}
			});
			input.focus();
		});
	},
	_getMessageTemplate: function () {
		var defer = $.Deferred();
		if (!this.$messageTemplate) {
			var self = this;
			$.get(OC.filePath('core', 'templates', 'message.html'), function (tmpl) {
				self.$messageTemplate = $(tmpl);
				defer.resolve(self.$messageTemplate);
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				defer.reject(jqXHR.status, errorThrown);
			});
		} else {
			defer.resolve(this.$messageTemplate);
		}
		return defer.promise();
	}

}