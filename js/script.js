/**
 * ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016
 */

(function (OC, window, $, undefined) {
'use strict';

$(document).ready(function () {

var translations = {
    newNote: $('#new-note-string').text()
};

// this notes object holds all our notes
var Notes = function (baseUrl) {
    this._baseUrl = baseUrl;
    this._notes = [];
    this._activeNote = undefined;
};

Notes.prototype = {
    load: function (id) {
        var self = this;
        this._notes.forEach(function (note) {
            if (note.id === id) {
                note.active = true;
                self._activeNote = note;
            } else {
                note.active = false;
            }
        });
    },
    getActive: function () {
        return this._activeNote;
    },
    unsetActive: function () {
        this._activeNote = undefined;
        this._notes.forEach(function (note) {
            note.active = false;
        });
    },
    removeActive: function () {
        var index;
        var deferred = $.Deferred();
        var id = this._activeNote.id;
        this._notes.forEach(function (note, counter) {
            if (note.id === id) {
                index = counter;
            }
        });

        if (index !== undefined) {
            // delete cached active note if necessary
            if (this._activeNote === this._notes[index]) {
                delete this._activeNote;
            }

            this._notes.splice(index, 1);

            $.ajax({
                url: this._baseUrl + '/' + id,
                method: 'DELETE'
            }).done(function () {
                deferred.resolve();
            }).fail(function () {
                deferred.reject();
            });
        } else {
            deferred.reject();
        }
        return deferred.promise();
    },
    create: function (note) {
        var deferred = $.Deferred();
        var self = this;
        $.ajax({
            url: this._baseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(note)
        }).done(function (note) {
            self._notes.unshift(note);
            self._activeNote = note;
            self.load(note.id);
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    getAll: function () {
        return this._notes;
    },
    getColors: function () {
        var colors = [];
        var Ccolors = [];
        $.each(this._notes, function(index, value) {
            if ($.inArray(value.color, colors) == -1) {
                colors.push(value.color);
            }
        });
        $.each(colors, function(index, value) {
            Ccolors.push({color: value});
        });
        return Ccolors;
    },
    loadAll: function () {
        var deferred = $.Deferred();
        var self = this;
        $.get(this._baseUrl).done(function (notes) {
            self._activeNote = undefined;
            self._notes = notes.reverse();
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    updateActive: function (title, content, color) {
        var note = this.getActive();
        note.title = title;
        note.content = content;
        note.color = color;

        return $.ajax({
            url: this._baseUrl + '/' + note.id,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(note)
        });
    },
    updateId: function (id, title, content, color) {
        this.load(id);
        return this.updateActive(title, content, color);
    }
};

// this will be the view that is used to update the html
var View = function (notes) {
    this._notes = notes;
};

View.prototype = {

    showAll: function () {
        //self._notes.unsetActive();
        $('.notes-grid').isotope({ filter: '*'});
    },
    editNote: function (id) {
        var modal = $('#modal-note-div');
        var modaltitle = $('#modal-note-div #title-editable');
        var modalcontent = $('#modal-note-div #content-editable');
        var modalnote = $("#modal-note-div .quicknote");

        var note = $('.notes-grid [data-id=' + id + ']').parent();

        var title = note.find("#title-editable").html();
        var content = note.find("#content-editable").html();
        var color = note.children().css("background-color");
        var colors = modal[0].getElementsByClassName("circle-toolbar");
        $.each(colors, function(i, c) {
            if(color == c.style.backgroundColor) {
                c.className += " icon-checkmark";
            }
        });

        var modalid = modalnote.data('id');

        if (id == modalid)
            return;

        modalnote.data('id', id);
        modaltitle.html(title);
        modalcontent.html(content);
        modalnote.css("background-color", color);

        /* Positioning the modal to the original size */
        $(".modal-content").css({
            "position" : "absolute",
            "left"     : note.offset().left,
            "top"      : note.offset().top,
            "width"    : note.width(),
            "min-height": note.height(),
            "height:"  : "auto"
        });
        modal.removeClass("hide-modal-note");
        modal.addClass("show-modal-note");
        modalcontent.focus();
    },
    cancelEdit: function () {
        var modal = $('#modal-note-div');
        var modaltitle = $('#modal-note-div #title-editable');
        var modalcontent = $('#modal-note-dive #content-editable');
        var modalnote = $("#modal-note-div .quicknote");
        //remove checkmark icons from temp selected color
        var modalcolortools = $("#modal-note-div .circle-toolbar");
        $.each(modalcolortools, function(i, colortool) {
            $(colortool).removeClass('icon-checkmark');
        });

        this._notes.unsetActive();

        modal.removeClass("show-modal-note");
        modal.addClass("hide-modal-note");

        modalnote.data('id', -1);
        modaltitle.html("");
        modalcontent.html("");
    },
    colorToHex: function(color) {
        if (color.substr(0, 1) === '#') {
            return color.toUpperCase();;
        }
        var digits = /(.*?)rgb\((\d+), (\d+), (\d+)\)/.exec(color);

        var red = parseInt(digits[2]);
        var green = parseInt(digits[3]);
        var blue = parseInt(digits[4]);

        var rgb = blue | (green << 8) | (red << 16);

        return digits[1] + '#' + rgb.toString(16).toUpperCase();
    },
    renderContent: function () {
        var source = $('#content-tpl').html();
        var template = Handlebars.compile(source);
        var html = template({notes: this._notes.getAll()});

        $('#div-content').html(html);

        // Init masonty grid to notes.
        $('.notes-grid').isotope({
            itemSelector: '.note-grid-item',
            layoutMode: 'masonry',
            masonry: {
                isFitWidth: true,
                fitWidth: true,
                gutter: 10,
            }
        });

        // Handle click event to open note.
        var modal = $('#modal-note-div');
        var modaltitle = $('#modal-note-div #title-editable');
        var modalcontent = $('#modal-note-div #content-editable');
        var modalnote = $("#modal-note-div .quicknote");

        // Show delete icon on hover.
        $("#app-content").on("mouseenter", ".quicknote", function() {
            $(this).find(".icon-delete").addClass( "show-delete-icon");
        });
        $("#app-content").on("mouseleave", ".quicknote", function() {
            $(this).find(".icon-delete").removeClass("show-delete-icon");
        });

        // Open notes when clicking them.
        $("#app-content").on("click", ".quicknote", function (event) {
            event.stopPropagation(); // Not work so need fix on next binding..

            var modalnote = $("#modal-note-editable .quicknote");
            var modalid = modalnote.data('id');
            if (modalid > 0) return;

            var id = parseInt($(this).data('id'), 10);

            self.editNote(id);
        });

        // Cancel when click outside the modal.
        $(".modal-note-background").click(function (event) {
            /* stopPropagation() not work with .on() binings. */
            if (!$(event.target).is(".modal-note-background")) {
                 event.stopPropagation();
                 return;
            }
            self.cancelEdit();
        });

        // Cancel with escape key
        $(document).keyup(function(event) {
            if (event.keyCode == 27) {
                self.cancelEdit();
            }
        });

        // Remove note icon
        var self = this;
        $('#app-content').on("click", ".icon-delete-note", function (event) {
            var note = $(this).parent();
            var id = parseInt(note.data('id'), 10);

            event.stopPropagation();

            self._notes.load(id);
            self._notes.removeActive().done(function () {
                $(".notes-grid").isotope('remove', note.parent())
                                .isotope('layout');
                self.renderNavigation();
            }).fail(function () {
                alert('Could not delete note, not found');
            });
        });

        /*
         * Modal actions.
         */

        // Handle colors.
        $('#modal-note-div .circle-toolbar').click(function (event) {
            event.stopPropagation();

            var oldColorTool = $('#modal-note-div .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
               $(oct).removeClass('icon-checkmark');
            });
            $(this).addClass('icon-checkmark');
            var color = $(this).css("background-color");
            modalnote.css("background-color", color);
        });

        // handle cancel editing notes.
        $('#modal-note-div #cancel-button').click(function (event) {
           self.cancelEdit();
        });

        // Handle save note
        $('#modal-note-div #save-button').click(function (event) {
            event.stopPropagation();

            var id = modalnote.data('id');
            var title = modaltitle.html();
            var content = modalcontent.html();
            var color = self.colorToHex(modalnote.css("background-color"));

            self._notes.updateId(id, title, content, color).done(function () {
                self._notes.unsetActive();

                modal.removeClass("show-modal-note");
                modal.addClass("hide-modal-note");

                modalnote.data('id', -1);
                modaltitle.html("");
                modalcontent.html("");

                self.render();
            }).fail(function () {
                alert('DOh!. Could not update note!.');
            });
        });
    },
    renderNavigation: function () {
        var source = $('#navigation-tpl').html();
        var template = Handlebars.compile(source);
        var html = template({colors: this._notes.getColors(), notes: this._notes.getAll()});

        $('#app-navigation ul').html(html);

        // show all notes
        $('#all-notes').click(function () {
            self._notes.unsetActive();
            $('.notes-grid').isotope({ filter: '*'});

            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
               $(oct).removeClass('icon-checkmark');
            });
            $('#app-navigation .any-color').addClass('icon-checkmark');
        });

        // create a new note
        var self = this;
        $('#new-note').click(function () {
            var note = {
                title: translations.newNote,
                content: '',
                color: '#F7EB96'
            };

            self._notes.create(note).done(function() {
                note = self._notes.getActive();
                var $notehtml = $("<div class=\"note-grid-item\">" +
                                  "<div class=\"quicknote noselect\" style=\"background-color:" + note.color + "\" data-id=\"" + note.id + "\">" +
                                  "<div id='title-editable' class='note-title'>" + note.title + "</div>" +
                                  "<button class=\"icon-delete hide-delete-icon icon-delete-note\" title=\"Delete\"></button>" +
                                  "<div id='content-editable' class='note-content'>" + note.content + "</div>" +
                                  "</div></div>");
                $(".notes-grid").prepend( $notehtml )
                                .isotope({ filter: '*'})
                                .isotope( 'prepended', $notehtml);
                self._notes.unsetActive();
                self.renderNavigation();
            }).fail(function () {
                alert('Could not create note');
            });
        });

        // show app menu
        $('#app-navigation .app-navigation-entry-utils-menu-button').click(function () {
            var entry = $(this).closest('.note');
            entry.find('.app-navigation-entry-menu').toggleClass('open');
        });

        // delete a note
        $('#app-navigation .note .delete').click(function () {
            var entry = $(this).closest('.note');
            entry.find('.app-navigation-entry-menu').removeClass('open');

            self._notes.removeActive().done(function () {
                self.render();
            }).fail(function () {
                alert('Could not delete note, not found');
            });
        });

        // show a note
        $('#app-navigation .note > a').click(function () {
            var id = parseInt($(this).parent().data('id'), 10);
            $('.notes-grid').isotope({ filter: function() {
                var itemId = parseInt($(this).children().data('id'), 10);
                return id == itemId;
            } });
        });

        // Handle colors.
        $('#app-navigation .circle-toolbar').click(function (event) {
            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
                 $(oct).removeClass('icon-checkmark');
            });
            $(this).addClass('icon-checkmark');

            if (!$(this).hasClass("any-color")) {
                var color = $(this).css("background-color");
                $('.notes-grid').isotope({ filter: function() {
                    var itemColor = $(this).children().css("background-color");
                    return color == itemColor;
                }});
            }
            else {
                self.showAll();
            }
        });

    },
    render: function () {
        this.renderNavigation();
        this.renderContent();
    }
};

var notes = new Notes(OC.generateUrl('/apps/quicknotes/notes'));
var view = new View(notes);
notes.loadAll().done(function () {
    view.render();
}).fail(function () {
    alert('Could not load notes');
});


});

})(OC, window, jQuery);
