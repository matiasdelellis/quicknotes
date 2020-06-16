/*
 * @copyright 2016-2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @author 2016 Matias De lellis <mati86dl@gmail.com>
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

(function (OC, window, $, undefined) {
'use strict';

$(document).ready(function () {

// this notes object holds all our notes
var Notes = function (baseUrl) {
    this._baseUrl = baseUrl;
    this._notes = [];
    this._loaded = false;

    this._usersSharing = [];
    this._loadUsersSharing();
};

Notes.prototype = {
    // Load notes from backend.
    load: function () {
        var self = this;
        var deferred = $.Deferred();
        $.get(this._baseUrl).done(function (notes) {
            self._notes = notes.reverse();
            self._loaded = true;
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    // Check that all the notes were loaded.
    isLoaded: function () {
        return this._loaded;
    },
    // Get the amount of notes.
    length: function () {
        return this._notes.length;
    },
    // Get all notes.
    getAll: function () {
        return this._notes;
    },
    // Get the colors used in the notes
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
    getUsersSharing: function () {
        return this._usersSharing;
    },
    // Get the tags used in the notes
    getTags: function () {
        var tags = [];
        $.each(this._notes, function(index, note) {
            $.each(note.tags, function(index, tag) {
                if (tags.findIndex(item => item.id == tag.id) === -1)
                    tags.push(tag);
            });
        });
        return tags;
    },
    // CRUD Create: Need an note template to have the translated title.
    create: function (noteTemplate) {
        var self = this;
        var deferred = $.Deferred();
        $.ajax({
            url: this._baseUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(noteTemplate)
        }).done(function (note) {
            self._notes.unshift(note);
            deferred.resolve(note);
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    // CRUD Read: Load a note to edit.
    read: function (id) {
        return this._notes.find((note) => note.id === id);
    },
    // CRUD Update
    update: function (note) {
        var self = this;
        var deferred = $.Deferred();
        $.ajax({
            url: this._baseUrl + '/' + note.id,
            method: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify(note)
        }).done(function (dbnote) {
            var index = self._notes.findIndex((aNote) => aNote.id === dbnote.id);
            self._notes.splice(index, 1, dbnote);
            deferred.resolve(dbnote);
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    // CRUD Delete
    remove: function (note) {
        var self = this;
        var deferred = $.Deferred();
        $.ajax({
            url: this._baseUrl + '/' + note.id,
            method: 'DELETE'
        }).done(function () {
            var index = self._notes.findIndex((aNote) => aNote.id === note.id);
            self._notes.splice(index, 1);
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    // Get the users to share in the notes
    _loadUsersSharing: function () {
        var self = this;
        $.get(OC.linkToOCS('apps/files_sharing/api/v1/', 1) + 'sharees', {
            format: 'json',
            itemType: 'principals'
        }).done(function (shares) {
            var users = [];
            console.log(shares);
            $.each(shares.ocs.data.exact.users, function(index, user) {
                users.push(user.value.shareWith);
            });
            self._usersSharing = users;
        }).fail(function () {
            console.error("Could not get users to share.");
        });
    }
};


// this will be the view that is used to update the html
var View = function (notes) {
    this._notes = notes;

    this._editor = undefined;
    this._changed = false;
};

View.prototype = {
    showAll: function () {
        $('.notes-grid').isotope({ filter: '*'});
    },
    updateSort: function() {
        $('.notes-grid').isotope('updateSortData').isotope();
    },
    editNote: function (id) {
        // Get selected note and sync content
        var note = this._notes.read(id);

        this._editableId(note.id);
        this._editableTitle(note.title);
        this._editableContent(note.content);
        this._editablePinned(note.ispinned);
        this._editableColor(note.color);
        this._editableShares(note.shared_with, note.shared_by);
        this._editableTags(note.tags);
        this._editableAttachts(note.attachts);

        // Create medium div editor.
        this._isEditable(!note.is_shared);

        // Show modal editor
        this._showEditor(id);

    },
    saveNote: function () {
        var fakeNote = {
            id: this._editableId(),
            title: this._editableTitle(),
            content: this._editableContent(),
            attachts: this._editableAttachts(),
            color: this._editableColor(),
            pinned: this._editablePinned(),
            tags: this._editableTags()
        };
        var self = this;
        this._notes.update(fakeNote).done(function (note) {
            // Create an new note and replace in grid.
            var noteHtml = $(Handlebars.templates['note-item'](note)).children();
            $('.notes-grid [data-id=' + note.id + ']').replaceWith(noteHtml);

            self._resizeAttachtsGrid();
            lozad('.attach-preview').observe();

            // Hide modal editor and reset it.
            self._hideEditor(note.id);
            self._destroyEditor();

            // Update navigation show the note again and update grid.
            self.renderNavigation();
            self.updateSort();
        }).fail(function () {
            alert('DOh!. Could not update note!.');
        });
    },
    cancelEdit: function () {
        // Hide modal editor and reset it.
        this._hideEditor(this._editableId());
        this._destroyEditor();
    },
    renderContent: function () {
        // Remove all event handlers to prevent double events.
        $("#div-content").off();
        $("#note-grid-dev").off();

        // Draw notes.
        var html = Handlebars.templates['notes']({
            loaded: this._notes.isLoaded(),
            notes: this._notes.getAll(),
            tagTxt: t('quicknotes', 'Tags'),
            cancelTxt: t('quicknotes', 'Cancel'),
            saveTxt: t('quicknotes', 'Save'),
            loadingMsg: t('quicknotes', 'Looking for your notes'),
            loadingIcon: OC.imagePath('core', 'loading.gif'),
            emptyMsg: t('quicknotes', 'Nothing here. Take your first quick notes'),
            emptyIcon: OC.imagePath('quicknotes', 'app'),
        });

        $('#div-content').html(html);

        // TODO: Move within handlebars template
        this._resizeAttachtsGrid();
        lozad('.attach-preview').observe();

        // Init masonty grid to notes.
        $('.notes-grid').isotope({
            itemSelector: '.note-grid-item',
            layoutMode: 'masonry',
            masonry: {
                isFitWidth: true,
                fitWidth: true,
                gutter: 14,
            },
            sortBy: 'pinnedNote',
            getSortData: {
                pinnedNote: function(itemElem) {
                    var $item = $(itemElem);
                    return $item.find('.icon-pinned').hasClass('fixed-header-icon') ? -1 : $item.index();
                }
            }
        });

        // Show delete and pin icons when hover over the notes.
        $("#notes-grid-div").on("mouseenter", ".quicknote", function() {
            $(this).find(".icon-header-note").addClass( "show-header-icon");
        });
        $("#notes-grid-div").on("mouseleave", ".quicknote", function() {
            $(this).find(".icon-header-note").removeClass("show-header-icon");
        });

        // Open notes when clicking them.
        $("#notes-grid-div").on("click", ".quicknote", function (event) {
            event.stopPropagation();
            var id = parseInt($(this).data('id'), 10);
            self.editNote(id);
        });

        // Doesn't show modal dialog when opening link
        $("#notes-grid-div").on("click", ".note-grid-item a", function (event) {
            event.stopPropagation();
        });

        // Filter notes by tag.
        $('#notes-grid-div').on('click', '.slim-tag', function (event) {
            event.stopPropagation();
            var tagId = parseInt($(this).attr('tag-id'), 10);
            $('.notes-grid').isotope({ filter: function() {
                var match = false;
                $(this).find(".slim-tag").siblings().addBack().each(function() {
                    var id = parseInt($(this).attr('tag-id'), 10);
                    if (tagId === id)
                        match = true;
                });
                return match;
            }});
            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
                $(oct).removeClass('icon-checkmark');
            });
        });

        // Remove note when click icon
        var self = this;
        $('#notes-grid-div').on("click", ".icon-delete-note", function (event) {
            event.stopPropagation();

            var gridnote = $(this).parent().parent().parent();
            var id = parseInt(gridnote.data('id'), 10);

            var note = self._notes.read(id);
            OC.dialogs.confirm(
                t('quicknotes', 'Are you sure you want to delete the note?'),
                t('quicknotes', 'Delete note'),
                function(result) {
                    if (result) {
                        self._notes.remove(note).done(function () {
                            if (self._notes.length() > 0) {
                                $(".notes-grid").isotope('remove', gridnote.parent())
                                                .isotope('layout');
                                self.showAll();
                                self.renderNavigation();
                            } else {
                                self.render();
                            }
                        }).fail(function () {
                            alert('Could not delete note, not found');
                        });
                    }
                },
                true
            );
        });

        // Pin note when click icon
        $('#notes-grid-div').on("click", ".icon-pin", function (event) {
            event.stopPropagation();

            var icon =  $(this);
            var gridNote = icon.parent().parent().parent();
            var id = parseInt(gridNote.data('id'), 10);

            var note = self._notes.read(id);
            note.pinned = true;

            self._notes.update(note).done(function () {
                icon.removeClass("hide-header-icon");
                icon.addClass("fixed-header-icon");
                icon.removeClass("icon-pin");
                icon.addClass("icon-pinned");
                icon.attr('title', t('quicknotes', 'Unpin note'));
                $('.notes-grid').isotope('updateSortData').isotope();
            }).fail(function () {
                alert('Could not pin note');
            });
        });

        // Unpin note when click icon
        $('#notes-grid-div').on("click", ".icon-pinned", function (event) {
            event.stopPropagation();

            var icon =  $(this);
            var gridNote = icon.parent().parent().parent();
            var id = parseInt(gridNote.data('id'), 10);

            var note = self._notes.read(id);
            note.pinned = false;

            self._notes.update(note).done(function () {
                icon.removeClass("fixed-header-icon");
                icon.addClass("hide-header-icon");
                icon.removeClass("icon-pinned");
                icon.addClass("icon-pin");
                icon.attr('title', t('quicknotes', 'Pin note'));
                $('.notes-grid').isotope('updateSortData').isotope();
            }).fail(function () {
                alert('Could not unpin note');
            });
        });

        /*
         * Modal actions.
         */
        // Cancel when click outside the modal.
        $('#div-content').on('click', '.modal-note-background', function (event) {
            event.stopPropagation();
            if (!self._changed) {
                self.cancelEdit();
                return;
            }
            OC.dialogs.confirm(
                t('facerecognition', 'Do you want to discard the changes?'),
                t('facerecognition', 'Unsaved changes'),
                function(result) {
                    if (result) {
                        self.cancelEdit();
                    }
                },
                true
            );
        });

        // But handles the click of modal within itself.
        $('#div-content').on('click', '.modal-content', function (event) {
            event.stopPropagation();
        });

        // Handle hotkeys
        $(document).off("keyup");  // FIXME: This prevent exponential calls of save note.
        $(document).on("keyup", function(event) {
            if (event.keyCode == 27) {
                event.stopPropagation();
                if (!self._changed) {
                    self.cancelEdit();
                    return;
                }
                OC.dialogs.confirm(
                    t('facerecognition', 'Do you want to discard the changes?'),
                    t('facerecognition', 'Unsaved changes'),
                    function(result) {
                        if (result) {
                            self.cancelEdit();
                        }
                    },
                    true
                );
            }
            else if (event.keyCode == 13 && event.altKey) {
                event.preventDefault();
                event.stopPropagation();
                self.saveNote();
            }
        });

        // Pin note in modal
        $('#modal-note-div').on("click", ".attach-remove", function (event) {
            event.stopPropagation();
            $(this).parent().remove();
            self._resizeAttachtsModal();
        });

        // Pin note in modal
        $('#modal-note-div').on("click", ".icon-pin", function (event) {
            event.stopPropagation();
            self._editablePinned(true);
        });

        // Unpin note in modal
        $('#modal-note-div').on("click", ".icon-pinned", function (event) {
            event.stopPropagation();
            self._editablePinned(false);
        });

        // Handle colors.
        $('#modal-note-div .circle-toolbar').click(function (event) {
            event.stopPropagation();
            var color = self._colorToHex($(this).css("background-color"));
            self._editableColor(color);
        });

        // Handle tags on modal
        $('#modal-note-div').on('click', '.slim-tag', function (event) {
            event.stopPropagation();
            $('#modal-note-div #tag-button').trigger( "click");
        });

        // handle tags button.
        $('#modal-note-div #share-button').click(function (event) {
            event.stopPropagation();
            QnDialogs.shares(
                self._notes.getUsersSharing(),
                self._editableShares(),
                function(result, newShares) {
                    if (result === true) {
                        self._editableShares(newShares, []);
                    }
                },
                true,
                t('quicknotes', 'Shares'),
                false
            );
        });

        // handle attach button.
        $('#modal-note-div #attach-button').click(function (event) {
            event.stopPropagation();
            OC.dialogs.filepicker(t('quicknotes', 'Select file to attach'), function(datapath, returntype) {
                OC.Files.getClient().getFileInfo(datapath).then((status, fileInfo) => {
                    var attachts = self._editableAttachts();
                    attachts.push({
                        file_id: fileInfo.id,
                        preview_url: OC.generateUrl('core') + '/preview.png?file=' + encodeURI(datapath) + '&x=512&y=512',
                        redirect_url: OC.generateUrl('/apps/files/?dir={dir}&scrollto={scrollto}', {dir: fileInfo.path, scrollto: fileInfo.name})
                    });
                    self._editableAttachts(attachts);
                }).fail(() => {
                    console.log("ERRORRR");
                });
            }, false, '*', true, OC.dialogs.FILEPICKER_TYPE_CHOOSE)
        });

        // handle tags button.
        $('#modal-note-div #tag-button').click(function (event) {
            event.stopPropagation();
            var noteTags = self._editableTags();
            QnDialogs.tags(
                self._notes.getTags(),
                noteTags,
                function(result, newTags) {
                    if (result === true) {
                        self._editableTags(newTags);
                    }
                },
                true,
                t('quicknotes', 'Tags'),
                false
            );
        });

        // handle cancel editing notes.
        $('#modal-note-div #close-button').click(function (event) {
            event.stopPropagation();
            self.cancelEdit();
        });

        // handle cancel editing notes.
        $('#modal-note-div #cancel-button').click(function (event) {
            event.stopPropagation();
            self.cancelEdit();
        });

        // Handle save note
        $('#modal-note-div #save-button').click(function (event) {
            event.stopPropagation();
            self.saveNote();
        });
    },
    renderNavigation: function () {
        var html = Handlebars.templates['navigation']({
            colors: this._notes.getColors(),
            notes: this._notes.getAll(),
            tags: this._notes.getTags(),
            newNoteTxt: t('quicknotes', 'New note'),
            allNotesTxt: t('quicknotes', 'All notes'),
            colorsTxt: t('quicknotes', 'Colors'),
            notesTxt: t('quicknotes', 'Notes'),
            tagsTxt: t('quicknotes', 'Tags'),
        });

        $('#app-navigation ul').html(html);

        // show all notes
        $('#all-notes').click(function () {
            $('.notes-grid').isotope({ filter: '*'});

            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
               $(oct).removeClass('icon-checkmark');
            });
            $('#app-navigation .any-color').addClass('icon-checkmark');
        });

        $('#shared-with-you').click(function () {
            $('.notes-grid').isotope({ filter: function() {
                return $(this).children().hasClass('shared');
            } });
        });

        $('#shared-by-you').click(function () {
            $('.notes-grid').isotope({ filter: function() {
                return $(this).children().hasClass('shareowner');
            } });
        });

        // create a new note
        var self = this;
        $('#new-note').click(function () {
            var fakenote = {
                title: t('quicknotes', 'New note'),
                content: '',
                color: '#F7EB96'
            };
            self._notes.create(fakenote).done(function(note) {
                if (self._notes.length() > 1) {
                    var $notehtml = $(Handlebars.templates['note-item'](note));
                    $(".notes-grid").prepend($notehtml)
                                    .isotope('prepended', $notehtml)
                                    .isotope('layout');
                    self.showAll();
                    self.updateSort();
                    self.renderNavigation();
                } else {
                    self.render();
                }
            }).fail(function () {
                alert('Could not create note');
            });
        });

        /* Colors Navigation */

        $('#colors-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#colors-folder > ul').click(function (event) {
            event.stopPropagation();
        });

        $('#app-navigation .circle-toolbar').click(function (event) {
            event.stopPropagation();
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

        /* Notes Navigation */

        $('#notes-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#app-navigation .nav-note > a').click(function (event) {
            event.stopPropagation();
            var id = parseInt($(this).parent().data('id'), 10);
            $('.notes-grid').isotope({ filter: function() {
                var itemId = parseInt($(this).children().data('id'), 10);
                return id == itemId;
            }});
            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
                $(oct).removeClass('icon-checkmark');
            });
        });

        /* Tags Navigation */

        $('#tags-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#app-navigation .nav-tag > a').click(function (event) {
            event.stopPropagation();
            var tagId = parseInt($(this).parent().attr('tag-id'), 10);
            $('.notes-grid').isotope({ filter: function() {
                var match = false;
                $(this).find(".slim-tag").siblings().addBack().each(function() {
                    var id = parseInt($(this).attr('tag-id'), 10);
                    if (tagId === id)
                        match = true;
                });
                return match;
            }});
            var oldColorTool = $('#app-navigation .circle-toolbar.icon-checkmark');
            $.each(oldColorTool, function(i, oct) {
                $(oct).removeClass('icon-checkmark');
            });
        });
    },

    /**
     * Some 'private' functions as helpers.
     */
    _colorToHex: function(color) {
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
    _isEditable: function(editable) {
        if (editable === undefined)
            return $('#title-editable').prop('contenteditable');
        else {
            if (editable) {
                $('#title-editable').prop('contenteditable', true);
                $('#modal-note-div .icon-header-note').show();
                $('#modal-note-div .note-options').show();
                $('#modal-note-div .note-disable-options').hide();
                this._initEditor();
            } else {
                $('#modal-note-div .note-options').hide();
                $('#modal-note-div .icon-header-note').hide();
                $('#title-editable').removeAttr("contentEditable");
                $('#modal-note-div .note-disable-options').show();
            }
        }
    },
    _editableId: function(id) {
        if (id === undefined)
            return $("#modal-note-div .quicknote").data('id');
        else
            $("#modal-note-div .quicknote").data('id', id);
    },
    _editableTitle: function(title) {
        if (title === undefined)
            return $('#modal-note-div #title-editable').html().trim();
        else
            $('#modal-note-div #title-editable').html(title);
    },
    _editableContent: function(content) {
        if (content === undefined)
            return $('#modal-note-div #content-editable').html().trim();
        else
            $('#modal-note-div #content-editable').html(content);
    },
    _editablePinned: function(pinned) {
        if (pinned === undefined)
            return $('#modal-note-div').find(".icon-pinned").length > 0;
        else {
            var icon = $('#modal-note-div .icon-header-note');
            if (pinned) {
                icon.removeClass("icon-pin");
                icon.addClass("icon-pinned");
                icon.attr('title', t('quicknotes', 'Unpin note'));
            } else {
                icon.removeClass("icon-pinned");
                icon.addClass("icon-pin");
                icon.attr('title', t('quicknotes', 'Pin note'));
            }
        }
    },
    _editableColor: function(color) {
        if (color === undefined)
            return this._colorToHex($("#modal-note-div .quicknote").css("background-color"));
        else {
            var self = this;
            var colors = $("#modal-note-div .quicknote")[0].getElementsByClassName("circle-toolbar");
            $.each(colors, function(i, c) {
                $(c).removeClass('icon-checkmark');
            });
            $.each(colors, function(i, c) {
                if (color === self._colorToHex(c.style.backgroundColor)) {
                    c.className += " icon-checkmark";
                }
            });
            $("#modal-note-div .quicknote").css("background-color", color);
        }
    },
    _editableShares: function(shared_with, shared_by) {
        if (shared_with === undefined) {
            return $("#modal-note-div .slim-share").toArray().map(function (value) {
                return {
                    id: value.getAttribute('share-id'),
                    name: value.textContent.trim()
                };
            });
        } else {
            var html = Handlebars.templates['shares']({ shared_by: shared_by, shared_with: shared_with});
            $("#modal-note-div .note-shares").replaceWith(html);
        }
    },
    _editableTags: function(tags) {
        if (tags === undefined) {
            return $("#modal-note-div .slim-tag").toArray().map(function (value) {
                return {
                    id: value.getAttribute('tag-id'),
                    name: value.textContent.trim()
                };
            });
        } else {
            var html = Handlebars.templates['tags']({ tags: tags});
            $("#modal-note-div .note-tags").replaceWith(html);
        }
    },
    _editableAttachts: function(attachts) {
        if (attachts === undefined) {
            return $("#modal-note-div .note-attach").toArray().map(function (value) {
                return {
                    file_id: value.getAttribute('attach-file-id'),
                    preview_url: value.getAttribute('data-background-image'),
                    redirect_url: value.parentElement.getAttribute('href')
                };
            });
        } else {
            var html = Handlebars.templates['attachts']({ attachts: attachts});
            $("#modal-note-div .note-attachts").replaceWith(html);

            lozad('.attach-preview').observe();
            this._resizeAttachtsModal();
        }
    },
    _resizeAttachtsModal: function() {
        var sAttachts = $('#modal-note-div .note-attach-grid');
        if (sAttachts.length === 0) {
            $('#modal-note-div .note-attachts').css('height','');
            return;
        }
        sAttachts.parent().css('height', (500/sAttachts.length) + 'px');
        sAttachts.first().children().first().children().css('border-top-left-radius', '8px');
        sAttachts.each(function(index) {
            $(this).css('width', (100/sAttachts.length) + '%');
            $(this).css('left', (100/sAttachts.length)*index + '%');
        });
        sAttachts.last().children().first().children().css('border-top-right-radius', '8px');
    },
    _resizeAttachtsGrid: function() {
        var attachtsgrids = $('#notes-grid-div .note-attachts');
        attachtsgrids.each(function() {
            var sAttachts = $(this).children('.note-attach-grid');
            sAttachts.parent().css('height', (250/sAttachts.length) + 'px');
            sAttachts.first().children().css('border-top-left-radius', '8px');
            sAttachts.each(function(index) {
            $(this).css('width', (100/sAttachts.length) + '%');
                $(this).css('left', (100/sAttachts.length)*index + '%');
            });
            sAttachts.last().children().css('border-top-right-radius', '8px');
        });
    },
    _initEditor: function() {
        var modalcontent = $('#modal-note-div #content-editable');
        var editor = new MediumEditor(modalcontent, {
            toolbar: {
                buttons: [
                    { name: 'bold', aria: t('quicknotes', 'Bold') },
                    { name: 'italic', aria: t('quicknotes', 'Italic') },
                    { name: 'underline', aria: t('quicknotes', 'Underline') },
                    { name: 'strikethrough', aria: t('quicknotes', 'Strikethrough') },
                    { name: 'unorderedlist', aria: t('quicknotes', 'Bulleted list') },
                    { name: 'orderedlist', aria: t('quicknotes', 'Numbered list') },
                    { name: 'quote', aria: t('quicknotes', 'Blockquote') },
                    { name: 'removeFormat', aria: t('quicknotes', 'Clean format') }
               ]
            },
            autoLink: true,
            targetBlank: true,
            paste: {
                forcePlainText: false,
                cleanPastedHTML: false
            },
            extensions: {
                'autolist': new AutoList()
            }
        });

        var self = this;
        editor.subscribe('editableInput', function(event, editorElement) {
            self._changed = true;
        });

        this._editor = editor;
    },
    _destroyEditor: function() {
        if (this._editor != undefined) {
            this._editor.destroy();
            this._editor = undefined;
        }
        this._changed = false;

        this._editableId(-1);
        this._editableTitle('');
        this._editableContent('');
        this._editablePinned(false);
        this._editableTags([]);

        $.each($("#modal-note-div .circle-toolbar"), function(i, colortool) {
            $(colortool).removeClass('icon-checkmark');
        });
    },
    _showEditor: function(id) {
        var note = $('.notes-grid [data-id=' + id + ']').parent();
        var modal = $(".modal-content");

        /* Positioning the modal to the original size */
        $(".modal-content").css({
            "position" : "absolute",
            "left"     : note.offset().left,
            "top"      : note.offset().top,
            "width"    : note.width(),
            "height:"  : "auto"
        });

        $('#modal-note-div').removeClass("hide-modal-note");
        $('#modal-note-div').addClass("show-modal-note");

        note.css({"opacity": "0.1"});
        modal.css({"opacity": "0.1"});

        /* Animate to center */

        var windowWidth = $(window).width();
        var modalWidth = note.width()*2;

        var modalTop = 150;
        if (windowWidth < modalWidth) {
            modalWidth = windowWidth;
            modalTop = 50;
        }
        var noteLeft = note.offset().left;
        var noteTop = note.offset().top;
        var modalLeft = (windowWidth / 2 - modalWidth / 2);

        var distance = Math.sqrt(Math.pow(noteLeft - modalLeft , 2) + Math.pow(noteTop - modalTop, 2));
        var duration = distance / 3;

        modal.animate (
            {
               left: modalLeft,
               width: modalWidth,
               top: modalTop,
               opacity: 1.0
            },
            duration,
            function () {
                modal.css({"opacity": ""});
                $('#modal-note-div #content-editable').focus();
            }
        );
    },
    _hideEditor: function(id) {
        var note = $('.notes-grid [data-id=' + id + ']').parent();
        var modal = $(".modal-content");

        var noteLeft = note.offset().left;
        var noteTop = note.offset().top;
        var modalLeft = modal.offset().left;
        var modalTop = modal.offset().top;

        var distance = Math.sqrt(Math.pow(noteLeft - modalLeft , 2) + Math.pow(noteTop - modalTop, 2));
        var duration = distance / 3;

        modal.animate (
            {
               left: noteLeft,
               width: note.width(),
               top: noteTop,
               opacity: 0.0
            },
            duration,
            function () {
                note.css({"opacity": ""});
                $('#modal-note-div').removeClass("show-modal-note");
                $('#modal-note-div').addClass("hide-modal-note");
                modal.css({"opacity": ""});
            }
        );
    },
    render: function () {
        this.renderNavigation();
        this.renderContent();
    }
};


/**
 * Filter notes.
 */
function search (query) {
    if (query) {
        query = query.toLowerCase();
        $('.notes-grid').isotope({
            filter: function() {
                var title = $(this).find(".note-title").html().toLowerCase();
                if (title.search(query) >= 0)
                    return true;

                var content = $(this).find(".note-content").html().toLowerCase();
                if (content.search(query) >= 0)
                    return true;

                return false;
            }
        });
    } else {
        $('.notes-grid').isotope({ filter: '*'});
    }
};

new OCA.Search(search, function() {
    search('');
});


/*
 * Create modules
 */
var notes = new Notes(OC.generateUrl('/apps/quicknotes/notes'));
var view = new View(notes);

/*
 * Render initial loading view
 */
view.renderContent();

/*
 * Loading notes and render final view.
 */
notes.load().done(function () {
    view.renderNavigation();
    view.renderContent();
}).fail(function () {
    alert('Could not load notes');
});


});

})(OC, window, jQuery);
