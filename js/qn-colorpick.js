/*
 * @copyright 2022 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2022 Matias De lellis <mati86dl@gmail.com>
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

'use strict';

function QnColorPick(parentSelector, onSelectColor) {

    this._parentSelector = parentSelector;
    this._onSelectColor = onSelectColor;

    this._color = undefined;

    this._template = '' +
        '<div id="colorPickWrapper" class="colorPickWrapper">' +
            '<div id="colorPick">' +
                '<div class="colorPickButton" hexvalue="#F7EB96" style="background-color:#F7EB96"></div>' +
                '<div class="colorPickButton" hexvalue="#88B7E3" style="background-color:#88B7E3"></div>' +
                '<div class="colorPickButton" hexvalue="#C1ECB0" style="background-color:#C1ECB0"></div>' +
                '<div class="colorPickButton" hexvalue="#BFA6E9" style="background-color:#BFA6E9"></div>' +
                '<div class="colorPickButton" hexvalue="#DAF188" style="background-color:#DAF188"></div>' +
                '<div class="colorPickButton" hexvalue="#FF96AC" style="background-color:#FF96AC"></div>' +
                '<div class="colorPickButton" hexvalue="#FCF66F" style="background-color:#FCF66F"></div>' +
                '<div class="colorPickButton" hexvalue="#F2F1EF" style="background-color:#F2F1EF"></div>' +
                '<div class="colorPickButton" hexvalue="#C1D756" style="background-color:#C1D756"></div>' +
                '<div class="colorPickButton" hexvalue="#CECECE" style="background-color:#CECECE"></div>' +
            '</div>' +
       '</div>';

    this.select = function (hexcolor) {
        this._color = hexcolor;
    };

    this.close = function () {
        var picker = document.getElementById("colorPickWrapper");
        picker.remove();
    };

    this.show = function (hexcolor) {
        var self = this;

        var parent = document.querySelector(this._parentSelector);
        parent.innerHTML += this._template;

        var colors = document.querySelectorAll(".colorPickButton");
        colors.forEach (function(color) {
            if (hexcolor == color.getAttribute("hexvalue")) {
                color.classList.add("icon-checkmark");
            }
            color.addEventListener("click", function () {
                self._color = color.getAttribute("hexvalue");
                self._onSelectColor(self._color);
                self.close ();
            });
        });
    };

    this.isVisible = function () {
        var picker = document.getElementById("colorPickWrapper");
        return picker != null;
    };

    this.toggle = function () {
        if (this.isVisible()) {
            this.close();
        } else {
            this.show(this._color);
        }
    };
}