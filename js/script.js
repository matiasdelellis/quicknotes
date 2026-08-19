/*
 * @copyright 2016-2022 Matias De lellis <mati86dl@gmail.com>
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

// Escape text for use inside an HTML string (attribute and text content).
// Note titles are user content, so inserting them raw into the content
// would break the DOM — or worse, smuggle markup into other people's view
// of a shared note.
var escapeHtml = function (str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

/*
 * MediumEditor toolbar button that links the current note to another one
 * (a "wikilink"). It opens the QnDialogs note picker and inserts an
 * <a class="qn-wikilink" data-note-id="…"> anchor into the content.
 *
 * The link is stored as plain HTML like any other content, and keyed by
 * the numeric id of the target note — ids survive renames, titles don't.
 * Clicks on it are intercepted by View._openNoteLink() in this same file,
 * so following one jumps straight to the other note.
 */
var QnWikiLinkExtension = MediumEditor.Extension.extend({
    name: 'qn-wikilink',

    getButton: function () {
        var aria = this.ariaLabel || this.name;
        var button = this.document.createElement('button');
        button.className = 'medium-editor-action medium-editor-action-qn-wikilink';
        button.setAttribute('data-action', this.name);
        button.setAttribute('aria-label', aria);
        button.setAttribute('title', aria);
        button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

        var self = this;
        this.on(button, 'click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            self.onClick(event);
        });
        return button;
    },

    onClick: function () {
        var self = this;
        var notes = this.getNotes();
        if (notes.length === 0) {
            QnDialogs.error(t('quicknotes', 'No notes to link'));
            return;
        }

        // The dialog is a Nextcloud modal; opening it steals focus and the
        // selection, so remember where the caret was to drop the link there.
        this.base.saveSelection();

        QnDialogs.linkNote(notes, function (note) {
            if (note === null) {
                return;
            }
            self.base.restoreSelection();

            // The href is a real app deep-link (?n=<id>), so the link also
            // works with a middle click or "open in new tab".
            var href = OC.generateUrl('/apps/quicknotes') + '?n=' + note.id;
            var label = escapeHtml(note.label);
            // data-disable-preview keeps the medium-editor anchor preview bubble
            // off these links: it would only display the internal deep-link URL.
            var anchor = '<a href="' + href + '" class="qn-wikilink" data-note-id="' +
                note.id + '" data-disable-preview title="' + label + '">' + label + '</a>';
            MediumEditor.util.insertHTMLCommand(self.document, anchor);

            self.onInserted();
        });
    }
});

/*
 * Text as it is compared by the filter: lower case and without accents,
 * so "cafe" finds "Café" and the other way round. Notes are written in whatever
 * language the user thinks in, and requiring the right accent to find your own
 * note is the kind of strictness nobody asked for.
 */
var normalizeForFilter = function (text) {
    return String(text || '')
        .toLowerCase()
        .normalize('NFD')
        // The combining marks block, i.e. what NFD just split the accents into.
        .replace(/[\u0300-\u036f]/g, '');
};

/*
 * Opening an attachment in OCA.Viewer.
 *
 * The point of this is the files of *other people*: an attachment is served by
 * this app, from the storage of whoever attached it, to anybody who can see the
 * note — nothing is shared in Files. The viewer can be handed that, as long as
 * it is given a `fileInfo` and not a path:
 *
 *   - `source` wins over the dav path it would otherwise derive, and is what
 *     the image and media components load from;
 *   - `list` has to be provided, or it goes looking for the folder of the file
 *     over WebDAV to build the gallery — a folder that is not the viewer's;
 *   - `permissions` is deliberately *not* provided: it is what the Edit and
 *     Delete buttons of the viewer key off, and both of those would act on
 *     the file over WebDAV as the person looking, which is precisely what they
 *     cannot do. Left out, the buttons are not rendered at all.
 *   - `enableSidebar: false` for the same reason: the Files sidebar wants a
 *     real dav node.
 *
 * Which leaves the mime types whose handler is the viewer's own — images, video
 * and audio. The ones other apps register (Text, richdocuments, the pdf viewer)
 * resolve the file themselves, as the current user, so they cannot open
 * somebody else's attachment and are left to the plain link.
 */
var VIEWABLE_ATTACHMENT_MIMES = ['image/', 'video/', 'audio/'];

var isViewableAttachment = function (mime) {
    if (!mime) return false;
    return VIEWABLE_ATTACHMENT_MIMES.some(function (prefix) {
        return mime.indexOf(prefix) === 0;
    });
};

// The viewer identifies a file within its list by `filename`, so it carries the
// file id to stay unique; what it *shows* is the basename.
var attachmentFileInfo = function ($attach) {
    var fileId = $attach.attr('attach-file-id');
    var basename = $attach.attr('data-attach-name') || String(fileId);
    return {
        fileid: parseInt(fileId, 10),
        filename: '/' + fileId + '/' + basename,
        basename: basename,
        displayname: basename,
        mime: $attach.attr('data-attach-mime'),
        source: $attach.attr('data-attach-source'),
        hasPreview: false
    };
};

// this will be the view that is used to update the html
var View = function (notes) {
    var self = this;
    this._notes = notes;

    this._editor = undefined;
    this._isotope = undefined;
    this._colorPick = undefined;

    this._noteChanged = false;

    // The text the grid is filtered by, '' when it is not. It survives a
    // re-render (the navigation is redrawn from scratch, input and all), which
    // is why it lives here and not only in the DOM.
    this._query = '';
    this._filterTimer = undefined;

    // Which bucket is currently shown in the grid: 'all' (active notes),
    // 'archived' or 'trash'. Drives which list is rendered and which
    // navigation entry is marked active.
    this._currentView = 'all';

    // Cached jQuery selectors. Re-rendered by renderContent() before any
    // user interaction, so a single binding per instance is safe.
    this._$doc = $(document);
    this._$modal = $('#modal-note-div');
    this._$modalContent = $('.modal-content');
    this._$notesGrid = $('.notes-grid');
};

View.prototype = {
    showAll: function () {
        this._isotope.arrange({ filter: '*'});
        this._afterFilter();
        setFilterUrl();
    },
    updateSort: function() {
        this._isotope.updateSortData();
        this._isotope.layout();
        this._isotope.arrange({sortBy: ['pinned', getSortBy()]})
    },
    editNote: function (id) {
        // Defensive re-cache: ensure the modal selector still matches the
        // current DOM, even if editNote is called from a context that
        // didn't run through renderContent().
        this._$modal = $('#modal-note-div');

        // Get selected note and sync content
        var note = this._notes.read(id);

        this._editableId(note.id);
        this._editableTitle(note.title);
        this._editableContent(note.content);
        this._editablePinned(note.isPinned);
        this._editableColor(note.color);
        this._editableShares(note.sharedWith);
        this._editableTags(note.tags);
        // `|| null` matters: _editableReminder() reads an undefined argument
        // as "this is a getter call", so a note that somehow arrives without
        // the field would leave the badge of the previously edited note in
        // place instead of clearing it.
        this._editableReminder(note.reminderAt || null, note.reminderNotifiedAt || null);
        this._editableAttachts(note.attachments, note.canEdit);

        // The state of the note as it was read. It goes back to the server on
        // save as If-Match, so an edit that lands on top of somebody else's is
        // refused instead of silently winning.
        this._editedEtag = note.etag;

        // Create medium div editor. A note shared with the user is editable
        // when the share says so; what belongs to the owner alone stays out of
        // reach either way, and _applyPermissions() takes care of that.
        this._isEditable(note.canEdit);
        this._applyPermissions(note);

        // Show modal editor
        this._showEditor(id);

        // Every open starts clean. _noteChanged is only ever reset in
        // _destroyEditor(), which the direct wikilink path skips, and opening
        // re-initialises the editor (destroy + rebuild) which can make
        // MediumEditor fire editableInput and flag a note as dirty for changes
        // that were never made — leaving Escape asking to discard them.
        this._noteChanged = false;

    },
    saveNote: function () {
        var fakeNote = {
            id: this._editableId(),
            title: this._editableTitle(),
            content: this._editableContent(),
            attachments: this._editableAttachts(),
            color: this._editableColor(),
            isPinned: this._editablePinned(),
            tags: this._editableTags(),
            etag: this._editedEtag
        };

        // The reminder is not in here: it is personal, it has its own
        // endpoint, and it is written the moment the user picks it — the same
        // way the shares are. Nothing about it waits for the note to be saved,
        // which is what makes it work on a note shared read only, where there
        // is nothing to save at all.
        var self = this;
        return this._notes.update(fakeNote).done(function (note) {
            // Create an new note and replace in grid.
            var noteHtml = $(Handlebars.templates['note-item'](note)).children();
            self._$notesGrid.find("[data-id='" + note.id + "']").replaceWith(noteHtml);

            self._layoutAttachts($('#notes-grid-div .note-attachts'));
            lozad('.attach-preview').observe();

            // Hide modal editor and reset it.
            self._hideEditor(note.id);
            self._destroyEditor();

            // Update navigation show the note again and update grid.
            self.renderNavigation();
            self.updateSort();
        }).fail(function (xhr) {
            if (xhr && xhr.status === 412) {
                self._handleSaveConflict(xhr);
                return;
            }
            if (xhr && xhr.status === 403) {
                QnDialogs.error(t('quicknotes', 'You are not allowed to edit this note'));
                return;
            }
            QnDialogs.error(t('quicknotes', 'DOh!. Could not update note!.'));
        });
    },
    /**
     * Somebody else saved the note while it was open here.
     *
     * The server refused the save (412) and sent back the note as it now
     * stands, so there is a real choice to offer: keep what was typed here and
     * overwrite theirs, or drop it and take theirs. Doing neither — saving
     * anyway, or failing silently — is how one of the two edits disappears
     * without anybody noticing, which is exactly what the etag exists to
     * prevent.
     *
     * @param {object} xhr the failed request, carrying {message, note}
     */
    _handleSaveConflict: function (xhr) {
        var self = this;
        var current = (xhr.responseJSON || {}).note;

        OC.dialogs.confirm(
            t('quicknotes', 'Somebody else changed this note while you were editing it. Do you want to overwrite their version with yours? Otherwise your changes are discarded and the note is reloaded.'),
            t('quicknotes', 'The note changed elsewhere'),
            function (result) {
                if (result) {
                    // Save again against the note as it stands now. This is an
                    // overwrite, and the user just asked for it in as many
                    // words.
                    self._editedEtag = current ? current.etag : undefined;
                    self.saveNote();
                    return;
                }

                if (!current) {
                    // Nothing to fall back to; a full reload is the only
                    // honest way to show what is really stored.
                    self.closeEdit();
                    self._refresh(true);
                    return;
                }

                self._notes.replace(current);
                var noteHtml = $(Handlebars.templates['note-item'](current)).children();
                self._$notesGrid.find("[data-id='" + current.id + "']").replaceWith(noteHtml);
                self.closeEdit();
                self.updateSort();
            },
            true
        );
    },
    closeEdit: function () {
        // Hide modal editor and reset it.
        this._hideEditor(this._editableId());
        this._destroyEditor();
    },
    cancelEdit: function () {
        var self = this;
        if (!self._noteChanged) {
            self.closeEdit();
            return;
        }
        OC.dialogs.confirm(
            t('quicknotes', 'Do you want to discard the changes?'),
            t('quicknotes', 'Unsaved changes'),
            function(result) {
                if (result) {
                    self.closeEdit();
                }
            },
            true
        );
    },
    renderContent: function () {
        // Remove all event handlers to prevent double events.
        $("#div-content").off();
        $("#note-grid-dev").off();

        // Pick the bucket to render based on the active view.
        var currentNotes;
        var emptyMsg;
        var emptyIcon;
        switch (this._currentView) {
            case 'archived':
                currentNotes = this._notes.getArchived();
                emptyMsg = t('quicknotes', 'No archived notes');
                emptyIcon = 'icon-archived';
                break;
            case 'trash':
                currentNotes = this._notes.getDeleted();
                emptyMsg = t('quicknotes', 'Trash is empty');
                emptyIcon = 'icon-qn-trash';
                break;
            case 'all':
            default:
                currentNotes = this._notes.getAll();
                emptyMsg = t('quicknotes', 'Nothing here. Take your first quick notes');
                emptyIcon = 'icon-quicknotes';
                break;
        }

        // Draw notes.
        var html = Handlebars.templates['notes']({
            loaded: this._notes.isLoaded(),
            notes: currentNotes,
            allView: this._currentView === 'all',
            trashView: this._currentView === 'trash',
            archivedView: this._currentView === 'archived',
            tagTxt: t('quicknotes', 'Tags'),
            cancelTxt: t('quicknotes', 'Cancel'),
            saveTxt: t('quicknotes', 'Save'),
            emptyTrashTxt: t('quicknotes', 'Empty trash'),
            loadingMsg: t('quicknotes', 'Looking for your notes'),
            loadingIcon: OC.imagePath('core', 'loading.gif'),
            emptyMsg: emptyMsg,
            emptyIcon: emptyIcon,
            noMatchesMsg: t('quicknotes', 'No notes match the filter'),
        });

        $('#div-content').html(html);

        // Re-cache jQuery selectors that point into the re-rendered DOM.
        this._$modal = $('#modal-note-div');
        this._$modalContent = $('.modal-content');
        this._$notesGrid = $('.notes-grid');

        // TODO: Move within handlebars template
        this._layoutAttachts($('#notes-grid-div .note-attachts'));
        lozad('.attach-preview').observe();

        // Save instance of View
        var self = this;

        // Init masonty grid to notes.
        if (this._notes.isLoaded() && currentNotes.length > 0 && this._$notesGrid[0]) {
            this._isotope = new Isotope(this._$notesGrid[0], {
                layoutMode: 'masonry',
                masonry: {
                    isFitWidth: true,
                    fitWidth: true,
                    gutter: 14,
                },
                itemSelector: '.note-grid-item',
                getSortData: {
                    pinned: function(itemElem) {
                        return itemElem.firstElementChild.getAttribute('data-pinned');
                    },
                    title: function(itemElem) {
                        var $item = $(itemElem);
                        return $item.find('.note-title').text().trim();
                    },
                    created: function(itemElem) {
                        return itemElem.firstElementChild.getAttribute('data-id');
                    },
                    updated: function(itemElem) {
                        return itemElem.firstElementChild.getAttribute('data-timestamp');
                    }
                },
                sortAscending: {
                    pinned: false,
                    title: true,
                    created: true,
                    updated: false
                },
                sortBy: ['pinned', getSortBy()]
            });

            this._colorPick = new QnColorPick(".modal-content", function (color) {
                self._$modal.find(".quicknote").css("background-color", color);
                self._noteChanged = true;
            });
        }

        // A re-render throws the isotope arrangement away, so an active text
        // filter has to be put back — otherwise saving a note or coming back to
        // the tab silently shows everything again.
        if (this._query.length > 0 && this._isotope) {
            this._filterText(this._query);
        } else {
            this._afterFilter();
        }

        // Show delete and pin icons when hover over the notes.
        $("#notes-grid-div").on("mouseenter", ".quicknote", function() {
            $(this).find(".icon-header-note").addClass( "show-header-icon");
        });
        $("#notes-grid-div").on("mouseleave", ".quicknote", function() {
            $(this).find(".icon-header-note").removeClass("show-header-icon");
        });

        // Open notes when clicking them. Ignore clicks on header
        // icons (pin, delete, ...) — they have their own handlers
        // registered as delegated handlers on the same parent, which
        // would otherwise also fire and open the edit modal.
        $("#notes-grid-div").on("click", ".quicknote", function (event) {
            if ($(event.target).closest('.icon-header-note').length) {
                return;
            }
            event.stopPropagation();
            // Notes in the trash can only be restored or purged; the
            // edit modal must not open for them.
            if (self._currentView === 'trash') return;
            var id = parseInt($(this).attr('data-id'), 10);
            self.editNote(id);
        });

        // Doesn't show modal dialog when opening link. A wikilink (an
        // <a data-note-id="…">) jumps to the linked note instead.
        $("#notes-grid-div").on("click", ".note-grid-item a", function (event) {
            event.stopPropagation();

            var noteId = $(this).attr('data-note-id');
            if (noteId === undefined) {
                return;
            }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();
            self._openNoteLink(parseInt(noteId, 10));
        });

        // Filter notes by tag.
        $('#notes-grid-div').on('click', '.slim-tag', function (event) {
            event.stopPropagation();
            if (self._currentView !== 'all') {
                return;
            }
            var tagId = parseInt($(this).attr('tag-id'), 10);
            self._cleanNavigation();
            self._filterTag(tagId);
            setFilterUrl('t', tagId);
        });

        // Archive or unarchive the note. The fixed-header-icon variant
        // is rendered on already-archived notes, so it triggers an
        // unarchive instead of a fresh archive. Inside the trash view
        // the icon is shown only as a state indicator — no action.
        $('#notes-grid-div').on("click", ".icon-archived", function (event) {
            event.stopPropagation();

            if (self._currentView === 'trash') return;

            var icon = $(this);
            var gridnote = icon.parent().parent();
            var id = parseInt(gridnote.attr('data-id'), 10);
            var note = self._notes.read(id);
            if (!note) return;

            // Archiving is personal: it says where the note sits in *this*
            // user's grid, so it works on a note somebody else shared — which
            // is the only way out of the grid for one shared with a group,
            // since that share is not theirs to leave.

            if (icon.hasClass('fixed-header-icon')) {
                self._unarchiveNote(note, gridnote);
            } else if (self._currentView !== 'archived') {
                self._archiveNote(note, gridnote);
            }
        });

        // Unarchive the note from the archived view. The template only
        // renders this icon in the archived view, but the guard keeps
        // the handler safe to wire up unconditionally.
        $('#notes-grid-div').on("click", ".icon-unarchive", function (event) {
            event.stopPropagation();

            if (self._currentView !== 'archived') return;

            var icon = $(this);
            var gridnote = icon.parent().parent();
            var id = parseInt(gridnote.attr('data-id'), 10);
            var note = self._notes.read(id);
            if (!note) return;

            self._unarchiveNote(note, gridnote);
        });

        // Move the note to trash, restore it, or purge it depending
        // on the current view and whether the icon is the fixed
        // "in trash" indicator.
        $('#notes-grid-div').on("click", ".icon-delete-note", function (event) {
            event.stopPropagation();

            var icon = $(this);
            var gridnote = icon.parent().parent();
            var id = parseInt(gridnote.attr('data-id'), 10);
            var note = self._notes.read(id);
            if (!note) return;

            // For a note of somebody else's, the same icon means leaving it —
            // and it is only rendered when there is a share of one's own to
            // leave. A note reaching you through a group is archived instead.
            if (!note.isOwner) {
                if (!note.canLeave) return;
                self._forgetSharedNote(note, gridnote);
                return;
            }

            if (self._currentView === 'trash') {
                self._purgeNote(note, gridnote);
                return;
            }

            self._trashNote(note, gridnote);
        });

        // Empty the trash in one go. The bar it lives on is only rendered in
        // that view, and only when there is something in there.
        $('#empty-trash').click(function (event) {
            event.stopPropagation();
            if (self._currentView !== 'trash') return;
            self._emptyTrash();
        });

        // Restore a note from the trash. The template only renders
        // this icon in the trash view; the guard matches that scope.
        $('#notes-grid-div').on("click", ".icon-restore", function (event) {
            event.stopPropagation();

            if (self._currentView !== 'trash') return;

            var icon = $(this);
            var gridnote = icon.parent().parent();
            var id = parseInt(gridnote.attr('data-id'), 10);
            var note = self._notes.read(id);
            if (!note) return;

            if (!note.isOwner) return;

            self._restoreNote(note, gridnote);
        });

        // Pin note when click icon
        $('#notes-grid-div').on("click", ".icon-pin", function (event) {
            event.stopPropagation();

            // Pin is not allowed inside the trash; the icon is
            // rendered as a state indicator only.
            if (self._currentView === 'trash') return;

            var icon = $(this);
            var gridNote = icon.parent().parent();
            var id = parseInt(gridNote.attr('data-id'), 10);

            var note = self._notes.read(id);
            note.isPinned = true;

            self._notes.update(note).done(function () {
                icon.removeClass("hide-header-icon");
                icon.addClass("fixed-header-icon");
                icon.removeClass("icon-pin");
                icon.addClass("icon-pinned");
                icon.attr('title', t('quicknotes', 'Unpin note'));
                gridNote.attr('data-pinned', 1);
                self._isotope.updateSortData();
                self._isotope.arrange();
            }).fail(function () {
                QnDialogs.error(t('quicknotes', 'Could not pin note'));
            });
        });

        // Unpin note when click icon
        $('#notes-grid-div').on("click", ".icon-pinned", function (event) {
            event.stopPropagation();

            if (self._currentView === 'trash') return;

            // Archived notes can only be edited from the modal, so the
            // pin icon is rendered as a state indicator only and must
            // not toggle the pin state from the grid.
            if (self._currentView === 'archived') return;

            var icon = $(this);
            var gridNote = icon.parent().parent();
            var id = parseInt(gridNote.attr('data-id'), 10);

            var note = self._notes.read(id);
            note.isPinned = false;
            self._notes.update(note).done(function () {
                icon.removeClass("fixed-header-icon");
                icon.addClass("hide-header-icon");
                icon.removeClass("icon-pinned");
                icon.addClass("icon-pin");
                icon.attr('title', t('quicknotes', 'Pin note'));
                gridNote.attr('data-pinned', 0);
                self._isotope.updateSortData();
                self._isotope.arrange();
            }).fail(function () {
                QnDialogs.error(t('quicknotes', 'Could not unpin note'));
            });
        });

        /*
         * Modal actions.
         */

        /**
         * Save references of event target on mouse down to avoid manage click on
         * next event handler when selecting outside text outside the content.
         */
        var _clickTarget = undefined;
        $('#div-content').on('mousedown', function (event) {
            _clickTarget = event.target;
        });

        // Cancel when explicit click outside the modal.
        $('#div-content').on('click', '.modal-note-background', function (event) {
            if (_clickTarget != event.target)
                return;
            event.stopPropagation();
            if (self._colorPick.isVisible()) {
                self._colorPick.close();
                return;
            }
            self.cancelEdit();
        });

        // But handles the click of modal within itself.
        $('#div-content').on('click', '.modal-content', function (event) {
            event.stopPropagation();
        });

        // A wikilink inside the editor jumps to the linked note. It has to be
        // intercepted before the browser follows the href — the point is to
        // switch notes inside the app, not to load the page again.
        self._$modal.on("click", "a[data-note-id]", function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            self._openNoteLink(parseInt($(this).attr('data-note-id'), 10));
        });

        $('#title-editable').on("keydown", function(event) {
            if (event.keyCode == 13) {
                event.preventDefault();
                event.stopPropagation();
                $('#content-editable').focus();
            }
        });

        // Handle hotkeys
        $(document).off("keyup");  // FIXME: This prevent exponential calls of save note.
        $(document).on("keyup", function(event) {
            if (event.keyCode == 27) {
                event.stopPropagation();
                self.cancelEdit();
            }
            else if ((event.keyCode == 13 && event.ctrlKey) ||
                     (event.keyCode == 13 && event.altKey)) {
                event.preventDefault();
                event.stopPropagation();
                self.saveNote();
            }
        });

        // Pin note in modal
        self._$modal.on("click", ".attach-remove", function (event) {
            event.stopPropagation();
            $(this).parent().remove();
            self._layoutAttachts(self._$modal.find('.note-attachts'));
            self._noteChanged = true;
        });

        // Pin note in modal
        self._$modal.on("click", ".icon-pin", function (event) {
            event.stopPropagation();
            self._editablePinned(true);
            self._noteChanged = true;
        });

        // Unpin note in modal
        self._$modal.on("click", ".icon-pinned", function (event) {
            event.stopPropagation();
            self._editablePinned(false);
            self._noteChanged = true;
        });

        // Handle tags on modal
        self._$modal.on("click", ".slim-tag", function (event) {
            event.stopPropagation();
            self._$modal.find('#tag-button').trigger( "click");
        });

        // Handle shares on modal
        self._$modal.on("click", ".slim-share", function (event) {
            event.stopPropagation();
            self._$modal.find('#share-button').trigger( "click");
        });

        // handle share button.
        //
        // Sharing is not part of saving the note any more: the dialog writes
        // every change as it is made, and hands the resulting list back only
        // so the badges can be redrawn. Which is why `_noteChanged` is not
        // touched here — there is nothing pending to save.
        self._$modal.on("click", "#share-button", function (event) {
            event.stopPropagation();

            var id = parseInt(self._editableId(), 10);
            var note = self._notes.read(id);
            if (!note) return;
            if (!note.isOwner && !note.canReshare) return;

            QnDialogs.shares(id, note.sharedWith || [], true, function (shares) {
                if (shares === null) return;
                note.sharedWith = shares;
                note.sharedByMe = note.isOwner && shares.length > 0;
                self._editableShares(shares);
                self._refreshGridNote(note);
            });
        });

        // handle color button.
        self._$modal.on("click", "#color-button", function (event) {
            event.stopPropagation();
            self._colorPick.toggle();
        });

        // Open an attachment in the viewer, when it is something the viewer
        // can show and the app is installed at all. Everything else — and a
        // modified click, which means "open in a new tab" — is left to the
        // link the thumbnail sits in.
        self._$modal.on("click", ".note-attach", function (event) {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            var $attach = $(this);
            var fileInfo = attachmentFileInfo($attach);
            if (!fileInfo.source || !isViewableAttachment(fileInfo.mime)) {
                return;
            }
            if (!window.OCA || !OCA.Viewer) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            // The whole set of viewable attachments of the note, so the arrows
            // of the viewer walk through them instead of dead-ending.
            var list = self._$modal.find(".note-attach").toArray()
                .map(function (el) { return attachmentFileInfo($(el)); })
                .filter(function (info) {
                    return info.source && isViewableAttachment(info.mime);
                });

            OCA.Viewer.open({
                fileInfo: fileInfo,
                list: list,
                enableSidebar: false
            });
        });

        // handle attach button.
        self._$modal.on("click", "#attach-button", function (event) {
            event.stopPropagation();
            OC.dialogs.filepicker(t('quicknotes', 'Select file to attach'), function(datapath, returntype) {
                self._notes.getAttachmentInfo(datapath).then(function (attachment) {
                    var attachts = self._editableAttachts();
                    attachts.push({
                        file_id: attachment.file_id,
                        basename: attachment.basename,
                        mime: attachment.mime,
                        has_preview: attachment.has_preview,
                        preview_url: attachment.preview_url,
                        redirect_url: attachment.redirect_url,
                        // Until the note is saved there is no attachment to
                        // serve yet, so the thumbnail and the link are the
                        // ones of the file itself — which the person who just
                        // picked it can always reach. The save replaces both
                        // with the urls of the app.
                        link_url: attachment.redirect_url,
                        // Freshly picked, so it is this user's: no `user_id`
                        // yet, and theirs to remove before it is even saved.
                        is_mine: true
                        // No `download_url` either: there is no attachment to
                        // serve until the note is saved, so the thumbnail links
                        // into the picker's own Files and the viewer sits this
                        // one out. The save fills both in.
                    });
                    self._editableAttachts(attachts, true);
                    self._noteChanged = true;
                }).fail(function () {
                    QnDialogs.error(t('quicknotes', 'Could not attach the file.'));
                });
            }, false, null, true, OC.dialogs.FILEPICKER_TYPE_CHOOSE)
        });

        // handle tags button.
        self._$modal.on("click", "#tag-button", function (event) {
            event.stopPropagation();
            var noteTags = self._editableTags();
            QnDialogs.tags(
                self._notes.getTags(),
                noteTags,
                function(result, newTags) {
                    if (result === true) {
                        self._editableTags(newTags);
                        self._noteChanged = true;
                    }
                }
            );
        });

        // handle reminder button. It is on both toolbars — the reminder is the
        // one thing a note shared read only still lets you do — so the handler
        // keys off the class and not the id.
        self._$modal.on("click", ".reminder-button", function (event) {
            event.stopPropagation();

            var id = parseInt(self._editableId(), 10);
            var note = self._notes.read(id);
            if (!note) return;

            QnDialogs.reminder(
                self._editableReminder(),
                function(result, reminderAt) {
                    if (result !== true) return;
                    self._setReminder(note, reminderAt);
                }
            );
        });

        // Handle the reminder badge on the modal.
        self._$modal.on("click", ".slim-reminder", function (event) {
            event.stopPropagation();
            self._$modal.find('.reminder-button').first().trigger("click");
        });

        // handle close editing notes.
        self._$modal.on("click", "#close-button", function (event) {
            event.stopPropagation();
            if (!self._isEditable()) {
                self.closeEdit();
                return;
            }
            if (getExplicitSaveSetting())
                self.closeEdit();
            else
                self.saveNote();
        });

        // handle cancel editing notes.
        self._$modal.on("click", "#cancel-button", function (event) {
            event.stopPropagation();
            self.cancelEdit();
        });

        // Handle save note
        self._$modal.on("click", "#save-button", function (event) {
            event.stopPropagation();
            self.saveNote();
        });
    },
    renderNavigation: function () {
        var html = Handlebars.templates['navigation']({
            colors: this._notes.getColors(),
            tags: this._notes.getTags(),
            newNoteTxt: t('quicknotes', 'New note'),
            // "Filter", not "search": it narrows what is on screen. The
            // search of the server is the unified one of Nextcloud.
            filterTxt: t('quicknotes', 'Filter notes'),
            clearFilterTxt: t('quicknotes', 'Clear the filter'),
            // The navigation is redrawn from scratch, so the field has to be
            // told what it was showing.
            filterQuery: this._query,
            allNotesTxt: t('quicknotes', 'All notes'),
            remindersTxt: t('quicknotes', 'Reminders'),
            archivedTxt: t('quicknotes', 'Archived'),
            trashTxt: t('quicknotes', 'Trash'),
            colorsTxt: t('quicknotes', 'Colors'),
            tagsTxt: t('quicknotes', 'Tags'),
        });

        $('#app-navigation ul').html(html);

        /* Mark the entry matching the current view as active. */
        switch (this._currentView) {
            case 'archived': $('#archived-notes').addClass('active'); break;
            case 'trash':    $('#trash-notes').addClass('active');    break;
            case 'all':
            default:         $('#all-notes').addClass('active');      break;
        }

        var self = this;

        /* Filter by text */

        // Collapsed by default, as an entry like the others; the field takes its
        // place while it is in use, and only stays once there is something in it
        // — an empty field left open would claim to be filtering when it is not.
        this._showTextFilter(this._query.length > 0);

        $('#filter-notes > a').click(function (event) {
            event.preventDefault();
            self._showTextFilter(true);
        });

        $('#note-filter').on('input', function () {
            self._setTextFilter($(this).val(), false);
        });

        // Escape puts the entry back and drops the filter: with the cursor in
        // here, that is what the user means by it.
        $('#note-filter').on('keydown', function (event) {
            if (event.keyCode === 27) {
                event.stopPropagation();
                self._closeTextFilter();
            }
        });

        // Clicking away from an empty field collapses it again; one with
        // something typed stays, because it is showing what the grid is doing.
        $('#note-filter').on('blur', function () {
            if ($(this).val().length === 0) {
                self._showTextFilter(false);
            }
        });

        $('#note-filter-clear').click(function (event) {
            event.preventDefault();
            self._closeTextFilter();
        });

        /* Create a new note */

        $('#new-note').click(function () {
            var fakenote = {
                title: t('quicknotes', 'New note'),
                content: ''
            };
            self._notes.create(fakenote).done(function(note) {
                self._currentView = 'all';
                if (self._notes.length() > 1) {
                    var $notehtml = $(Handlebars.templates['note-item'](note));
                    self._$notesGrid.prepend($notehtml);
                    self._isotope.prepended($notehtml);
                    self._isotope.layout();
                    self.showAll();
                    self.updateSort();
                    self.renderNavigation();
                } else {
                    self.render();
                }
                // Open the freshly created note for editing.
                self.editNote(note.id);
            }).fail(function () {
                QnDialogs.error(t('quicknotes', 'Could not create note'));
            });
        });

        /* Show all notes */

        $('#all-notes').click(function (event) {
            event.preventDefault();
            self._currentView = 'all';
            self._cleanNavigation();
            $(this).addClass("active");
            self.renderContent();
            self.updateSort();
            setFilterUrl();
        });

        /* Show only the notes that carry a reminder.
         *
         * A filter over the active notes, not a bucket of its own like Archive
         * and Trash: it leans on isotope the same way the shared, colour and
         * tag entries do, so it composes with the grid that is already there
         * instead of needing another list in notes-api.js. */

        $('#reminder-notes').click(function (event) {
            event.preventDefault();
            if (self._currentView !== 'all') {
                self._currentView = 'all';
                self.renderContent();
            }
            self._cleanNavigation();
            $(this).addClass("active");
            self._filterReminders();
            setFilterUrl('r', '1');
        });

        /* Show archived notes */

        $('#archived-notes').click(function (event) {
            event.preventDefault();
            self._currentView = 'archived';
            self._cleanNavigation();
            $(this).addClass("active");
            self.renderContent();
            setFilterUrl();
        });

        /* Show trash */

        $('#trash-notes').click(function (event) {
            event.preventDefault();
            self._currentView = 'trash';
            self._cleanNavigation();
            $(this).addClass("active");
            self.renderContent();
            setFilterUrl();
        });

        /* Shares Navigation */

        $('#shared-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#shared-with-you').click(function (event) {
            event.preventDefault();
            event.stopPropagation();
            self._cleanNavigation();
            $(this).addClass("active");
            self._isotope.arrange({
                filter: function(index, elem) {
                    return elem.querySelector('.shared') != null;
                }
            });
            setFilterUrl();
        });

        $('#shared-by-you').click(function (event) {
            event.preventDefault();
            event.stopPropagation();
            self._cleanNavigation();
            $(this).addClass("active");
            self._isotope.arrange({
                filter: function(index, elem) {
                    return elem.querySelector('.shareowner') != null;
                }
            });
            setFilterUrl();
        });

        /* Colors Navigation */

        $('#colors-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#colors-folder > ul').click(function (event) {
            event.stopPropagation();
        });

        $('#colors-folder .circle-toolbar').click(function (event) {
            event.stopPropagation();
            if (self._currentView !== 'all') {
                self._currentView = 'all';
                self.renderContent();
            }
            self._cleanNavigation();
            $(this).addClass('icon-filter-checkmark');

            if (!$(this).hasClass("any-color-filter")) {
                var color = $(this).css("background-color");
                self._filterColor(color);
                setFilterUrl('c', color);
                $(this).parent().addClass("active");
            }
            else {
                self.showAll();
            }
        });

        /* Tags Navigation */

        $('#tags-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#app-navigation .nav-tag > a').click(function (event) {
            event.preventDefault();
            event.stopPropagation();
            var tagId = parseInt($(this).parent().attr('tag-id'), 10);
            if (self._currentView !== 'all') {
                self._currentView = 'all';
                self.renderContent();
            }
            self._cleanNavigation();
            $(this).addClass("active");
            self._filterTag(tagId);
            setFilterUrl('t', tagId);
        });
    },
    renderSettings: function () {
        /* Render view */
        var html = Handlebars.templates['settings']({});
        $('#app-settings-content').html(html);

        var self = this;

        $.get(OC.generateUrl('apps/quicknotes/getuservalue'), {'type': 'default_color'})
        .done(function (response) {
                var color = response.value;;
                var colors = $("#setting-defaul-color")[0].getElementsByClassName("circle-toolbar");
                $.each(colors, function(i, c) {
                    if (color === self._colorToHex(c.style.backgroundColor)) {
                        c.className += " icon-filter-checkmark";
                    }
                });
        });

        // Unlike the explicit-save one below, this setting lives on the server:
        // the calendar provider reads it on the CalDAV path, where there is no
        // browser to keep it in localStorage.
        $.get(OC.generateUrl('apps/quicknotes/getuservalue'), {'type': 'calendar_enabled'})
        .done(function (response) {
            $('#app-settings-content #show-reminders-calendar').prop('checked', response.value === true);
        });

        let sortBy = getSortBy();
        $("#sort-select option[value='" + sortBy + "']").attr("selected", true);

        $('#app-settings-content #explicit-save-notes').prop('checked', getExplicitSaveSetting());

        /* Settings */

        $("#app-settings-content").off();


        $('#app-settings-content').on('click', '#explicit-save-notes', function (event) {
              setExplicitSaveSetting($(this).is(':checked'));
        });

        $('#app-settings-content').on('click', '#show-reminders-calendar', function (event) {
            var checkbox = $(this);
            var enabled = checkbox.is(':checked');
            $.ajax({
                url: OC.generateUrl('apps/quicknotes/setuservalue'),
                type: 'POST',
                data: {
                    'type': 'calendar_enabled',
                    'value': enabled
                },
                error: function () {
                    // Put the box back where it was, so it never claims a
                    // state the server did not take.
                    checkbox.prop('checked', !enabled);
                    QnDialogs.error(t('quicknotes', 'Could not save the calendar setting'));
                }
            });
        });

        $('#app-settings-content').on( "change", "#sort-select", function() {
            let sortBy = $("#sort-select option:selected")[0].value
            self._isotope.arrange({sortBy: ['pinned', sortBy]});
            setSortBy(sortBy);
        });

        $('#app-settings-content').on('click', '.circle-toolbar', function (event) {
            event.stopPropagation();

            var currentColor = $(this);
            var color = self._colorToHex(currentColor.css("background-color"));

            $.ajax({
                url: OC.generateUrl('apps/quicknotes/setuservalue'),
                type: 'POST',
                data: {
                    'type': 'default_color',
                    'value': color
                },
                success: function (response) {
                    $('#setting-defaul-color .circle-toolbar').removeClass('icon-filter-checkmark');
                    currentColor.addClass('icon-filter-checkmark');
                }
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
            return ($('#title-editable').prop('contenteditable') === 'true');
        else {
            if (editable) {
                this._$modal.find(".icon-header-note").show();
                $('#title-editable').prop('contenteditable', true);
                this._$modal.find(".note-editable-options").show();
                this._$modal.find(".note-noneditable-options").hide();
                if (getExplicitSaveSetting()) {
                    this._$modal.find("#cancel-button").show();
                    this._$modal.find("#save-button").show();
                    this._$modal.find("#close-button").hide();
                } else {
                    this._$modal.find("#cancel-button").hide();
                    this._$modal.find("#save-button").hide();
                    this._$modal.find("#close-button").show();
                }
                this._initEditor();
            } else {
                this._$modal.find(".icon-header-note").hide();
                $('#title-editable').removeAttr("contentEditable");
                $('#content-editable').removeAttr("contentEditable");
                this._$modal.find(".note-editable-options").hide();
                this._$modal.find(".note-noneditable-options").show();
                this._$modal.find("#close-button").show();
            }
        }
    },
    /**
     * Hide the parts of the editor this user has no business using.
     *
     * `_isEditable()` is the coarse switch — can this note be typed into at
     * all — and this is the fine one. A note shared with write access is not
     * the same as a note of one's own: the colour and the attachments are
     * properties of the note in the owner's account, the reminder notifies the
     * owner, and passing the note on to a third person is a permission of its
     * own. What is personal (the pin, the tags) only needs write access,
     * because it rides along with the save of the note.
     *
     * @param {object} note the note being opened
     */
    _applyPermissions: function (note) {
        var $modal = this._$modal;
        var show = function (selector, visible) {
            $modal.find(selector).toggle(!!visible);
        };

        show('#color-button', note.isOwner);
        // Attaching only needs write access: the app serves every attachment
        // from the storage of whoever attached it, so a collaborator's file is
        // as visible to the others as the owner's.
        show('#attach-button', note.canEdit);
        show('#share-button', note.isOwner || note.canReshare);
        show('#tag-button', note.canEdit);
        // Not owner-only and not even edit-only: the reminder is the caller's
        // own, and read access is all it takes.
        show('.reminder-button', true);
        show('.icon-pin, .icon-pinned', note.canEdit);
    },
    /**
     * Arm, move or cancel the reminder of this user on a note.
     *
     * Applied immediately, not on save: a reminder is personal — since 0.9.2
     * it is a row of the user's own, not a column of the note — so it neither
     * needs write access to the note nor has any reason to wait for one. The
     * badge and the grid are redrawn from what the server answers.
     *
     * @param {object} note the note being edited
     * @param {string|null} reminderAt UTC 'Y-m-d H:i:s', null to cancel
     */
    _setReminder: function (note, reminderAt) {
        var self = this;
        this._notes.setReminder(note, reminderAt).done(function (dbnote) {
            self._editableReminder(dbnote.reminderAt || null, dbnote.reminderNotifiedAt || null);
            self._refreshGridNote(dbnote);
            self.updateSort();
        }).fail(function () {
            QnDialogs.error(t('quicknotes', 'Could not save the reminder'));
        });
    },
    /**
     * Redraw one note of the grid from the copy held in memory.
     *
     * @param {object} note the note to redraw
     */
    _refreshGridNote: function (note) {
        var noteHtml = $(Handlebars.templates['note-item'](note)).children();
        this._$notesGrid.find("[data-id='" + note.id + "']").replaceWith(noteHtml);
    },
    /**
     * Pick up what changed on the server.
     *
     * A note that is shared can be edited by somebody else while this page is
     * open, and nothing would say so: the grid is rendered once, at load. So
     * the list is fetched again whenever the tab is brought back to the front,
     * and the view is only rebuilt when something actually came back
     * different — rebuilding it drops the current filter and the isotope
     * layout, which is not worth doing for nothing.
     *
     * @param {boolean} force reload even with the editor open, and re-render
     *        whether anything changed or not
     */
    _refresh: function (force) {
        var self = this;

        if (this._refreshing) return;

        // The first load is still on its way; it will render on its own.
        if (!this._notes.isLoaded()) return;

        // Rebuilding the grid would pull the note out from under an open
        // editor, unsaved changes and all.
        if (!force && this._$modal.hasClass('show-modal-note')) return;

        var now = Date.now();
        if (!force && this._lastRefresh && (now - this._lastRefresh) < 15000) return;
        this._lastRefresh = now;

        var before = this._notes.signature();
        this._refreshing = true;

        this._notes.load().done(function () {
            if (force || self._notes.signature() !== before) {
                self.renderNavigation();
                self.renderContent();
            }
        }).always(function () {
            self._refreshing = false;
        });
    },
    _editableId: function(id) {
        if (id === undefined)
            return this._$modal.find(".quicknote").attr('data-id');
        else
            this._$modal.find(".quicknote").attr('data-id', id);
    },
    _editableTitle: function(title) {
        if (title === undefined) {
            title = this._$modal.find("#title-editable")[0].textContent ||
                    this._$modal.find("#title-editable")[0].innerText || "";
            return title.trim();
        } else
            this._$modal.find("#title-editable").html(title);
    },
    _editableContent: function(content) {
        if (content === undefined)
            return this._$modal.find("#content-editable").html().trim();
        else
            this._$modal.find("#content-editable").html(content);
    },
    _editablePinned: function(pinned) {
        if (pinned === undefined)
            return this._$modal.find(".icon-pinned").length > 0;
        else {
            var icon = this._$modal.find(".icon-header-note");
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
            return this._colorToHex(this._$modal.find(".quicknote").css("background-color"));
        else {
            this._$modal.find(".quicknote").css("background-color", color);
            this._colorPick.select(color);
        }
    },
    /**
     * Draw the share badges of the note being edited.
     *
     * Write only, unlike its tag counterpart: the shares of a note are not
     * read back out of the DOM on save any more, because saving no longer has
     * anything to do with them.
     *
     * @param {Array} shared_with the shares of the note
     */
    _editableShares: function(shared_with) {
        var html = Handlebars.templates['shares']({sharedWith: shared_with || []});
        this._$modal.find(".note-shares").replaceWith(html);
    },
    _editableTags: function(tags) {
        if (tags === undefined) {
            return this._$modal.find(".slim-tag").toArray().map(function (value) {
                return {
                    id: value.getAttribute('tag-id'),
                    name: value.textContent.trim()
                };
            });
        } else {
            var html = Handlebars.templates['tags']({ tags: tags});
            this._$modal.find(".note-tags").replaceWith(html);
        }
    },
    /**
     * Read or write the reminder shown in the editor, as the UTC
     * 'Y-m-d H:i:s' string the backend stores (null when there is none).
     *
     * `notified` only matters when writing: it greys the badge out for a
     * reminder whose notification already went out.
     */
    _editableReminder: function(reminderAt, notified) {
        if (reminderAt === undefined) {
            var $badge = this._$modal.find(".slim-reminder");
            return $badge.length ? ($badge.attr('reminder-at') || null) : null;
        } else {
            var html = Handlebars.templates['reminder']({
                reminderAt: reminderAt,
                reminderNotifiedAt: notified
            });
            this._$modal.find(".note-reminder").replaceWith(html);
        }
    },
    _editableAttachts: function(attachts, can_delete) {
        if (attachts === undefined) {
            return this._$modal.find(".note-attach").toArray().map(function (value) {
                return {
                    file_id: value.getAttribute('attach-file-id'),
                    // Who attached it: the save only touches the rows of
                    // whoever is saving, and without this the whole list —
                    // other people's attachments included — would come back
                    // filed as theirs.
                    user_id: value.getAttribute('data-attach-user') || undefined,
                    preview_url: value.getAttribute('data-background-image'),
                    link_url: value.parentElement.getAttribute('href')
                };
            });
        } else {
            var html = Handlebars.templates['attachts']({ attachments: attachts, can_delete: can_delete});
            this._$modal.find(".note-attachts").replaceWith(html);

            lozad('.attach-preview').observe();
            this._layoutAttachts(this._$modal.find('.note-attachts'));
        }
    },
    /**
     * Keep the attachment count on the container in step with the DOM.
     *
     * The shape of the mosaic is entirely CSS, keyed off `data-count` — see the
     * comment above `.note-attachts` in css/style.css. The templates set it
     * when they render; this is for afterwards, when the editor gains or loses
     * one without re-rendering the note.
     *
     * It replaces the two functions that used to compute a width and a left
     * offset per attachment (`100 / n` percent each, on a container `500 / n`
     * pixels tall), which is what made five attachments unreadable.
     *
     * @param {object} $container the .note-attachts to refresh
     */
    _layoutAttachts: function($container) {
        $container.each(function () {
            var $el = $(this);
            var count = $el.children('.note-attach-grid').length;
            $el.attr('data-count', count);
            // Only the grid of notes hides the extras and counts them; the
            // editor shows every one, since that is where they are removed.
            if (count > 6 && $el.closest('#notes-grid-div').length) {
                $el.attr('data-more', count - 6);
            } else {
                $el.removeAttr('data-more');
            }
        });
    },
    _initEditor: function() {
        var self = this;
        var modalcontent = this._$modal.find("#content-editable");
        if (modalcontent.length === 0) {
            console.error('[quicknotes] _initEditor: #content-editable not found in DOM');
            return;
        }
        if (this._editor) {
            this._editor.destroy();
            this._editor = undefined;
        }
        var qnWikiLink = new QnWikiLinkExtension({
            ariaLabel: t('quicknotes', 'Link to a note'),
            getNotes: function () {
                var currentId = parseInt(self._editableId(), 10);
                return self._notes.getAll().concat(self._notes.getArchived())
                    .filter(function (note) { return note.id !== currentId; })
                    .map(function (note) { return { id: note.id, label: note.title }; });
            },
            onInserted: function () {
                self._noteChanged = true;
            }
        });
        var editor = new MediumEditor(modalcontent, {
            toolbar: {
                buttons: [
                    { name: 'bold', aria: t('quicknotes', 'Bold') },
                    { name: 'italic', aria: t('quicknotes', 'Italic') },
                    { name: 'underline', aria: t('quicknotes', 'Underline') },
                    { name: 'strikethrough', aria: t('quicknotes', 'Strikethrough') },
                    { name: 'anchor', aria: t('quicknotes', 'Link') },
                    { name: 'qn-wikilink' },
                    { name: 'unorderedlist', aria: t('quicknotes', 'Bulleted list') },
                    { name: 'orderedlist', aria: t('quicknotes', 'Numbered list') },
                    { name: 'quote', aria: t('quicknotes', 'Blockquote') },
                    { name: 'removeFormat', aria: t('quicknotes', 'Clean format') }
               ]
            },
            placeholder: {
                text: t('quicknotes', 'Create a note…'),
                hideOnClick: false
            },
            autoLink: true,
            targetBlank: true,
            paste: {
                forcePlainText: true
            },
            extensions: {
                'autolist': new AutoList(),
                'qn-wikilink': qnWikiLink
            },
            imageDragging: false
        });

        editor.subscribe('editableInput', function(event, editorElement) {
            self._noteChanged = true;
        });
        $('#title-editable').on('input', function (event) {
            self._noteChanged = true;
        });
        this._editor = editor;
    },
    _destroyEditor: function() {
        // The picker hangs inside the modal, and nothing else takes it down:
        // left open, it stayed over the next note that was opened.
        if (this._colorPick != undefined && this._colorPick.isVisible()) {
            this._colorPick.close();
        }

        if (this._editor != undefined) {
            this._editor.destroy();
            this._editor = undefined;
        }
        this._noteChanged = false;

        this._editableId(-1);
        this._editableTitle('');
        this._editableContent('');
        this._editablePinned(false);
        this._editableTags([]);
    },
    _showEditor: function(id) {
        var self = this;
        var note = this._$notesGrid.find("[data-id='" + id + "']").parent();
        var modal = this._$modalContent;

        // Only one note is ever translucent at a time — the one the editor
        // floats over, dimmed through its .note-grid-item (the note's parent,
        // see note.css({"opacity": "0.1"}) below). Switching notes directly (a
        // wikilink) leaves the previous one faded if it never went through
        // _hideEditor(), so reset every grid item before dimming the target.
        this._$notesGrid.find(".note-grid-item").css("opacity", "");

        /* Positioning the modal to the original size */
        modal.css({
            "position" : "absolute",
            "left"     : note.offset().left,
            "top"      : note.offset().top,
            "width"    : note.width(),
            "height"   : "auto"
        });

        this._$modal.removeClass("hide-modal-note");
        this._$modal.addClass("show-modal-note");

        note.css({"opacity": "0.1"});
        modal.css({"opacity": "0.1"});

        /* Move caret to end of content */
        var range = document.createRange();
        range.selectNodeContents($('#content-editable')[0]);
        range.collapse(false);

        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);

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
                self._$modal.find("#content-editable").focus();
            }
        );
    },
    _hideEditor: function(id) {
        var self = this;
        var note = this._$notesGrid.find("[data-id='" + id + "']").parent();
        var modal = this._$modalContent;

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
                self._$modal.removeClass("show-modal-note");
                self._$modal.addClass("hide-modal-note");
                modal.css({"opacity": ""});
            }
        );
    },
    _filterNote: function (noteId) {
        this._isotope.arrange({
            filter: function(index, elem) {
                return noteId == elem.firstElementChild.getAttribute('data-id');
            }
        });
    },
    /**
     * Follow a wikilink to another note.
     *
     * Any unsaved edits go out first, so jumping does not silently lose
     * work, then the target is opened for editing.
     *
     * @param {number} id id of the linked note
     */
    _openNoteLink: function (id) {
        var self = this;
        var note = this._notes.read(id);
        if (!note || note.deletedAt) {
            QnDialogs.error(t('quicknotes', 'Note not found'));
            return;
        }
        if (this._isEditable() && this._noteChanged) {
            this.saveNote().done(function () {
                self._openNote(id);
            });
            return;
        }
        this._openNote(id);
    },
    /**
     * Open a note for editing, making sure it is actually on screen first.
     *
     * The editor modal positions itself over the note's grid cell, so a note
     * hidden by a filter or living in another view has to be surfaced before
     * editNote() can place the modal on it.
     */
    _openNote: function (id) {
        var self = this;
        var note = this._notes.read(id);
        if (!note) return;

        var $target = this._$notesGrid.find("[data-id='" + id + "']");
        if ($target.length === 0 || $target.filter(':visible').length === 0) {
            this._destroyEditor();
            if (note.archivedAt) {
                this._currentView = 'archived';
            } else {
                this._currentView = 'all';
            }
            this._cleanNavigation();
            if (this._currentView === 'archived') {
                $('#archived-notes').addClass('active');
            } else {
                $('#all-notes').addClass('active');
            }
            this.renderContent();
            this.updateSort();
            setFilterUrl();
        }
        this.editNote(id);
    },
    /**
     * Narrow the grid to the notes whose text matches.
     *
     * A filter over what is on screen, like the colour and tag ones — not a
     * search of the server. Every note is already in memory (`Notes.load()`
     * fetches them all), so this costs nothing and answers as you type; the
     * unified search of Nextcloud is the other thing, and `NoteSearchProvider`
     * is what serves it.
     *
     * Matching is per word and AND: "pagar luz" finds the note that says both,
     * in any order and in any of the three places a note carries text — its
     * title, its body and the names of its tags. Accents and case are ignored
     * (see normalizeForFilter).
     *
     * It reads the text off the DOM, the way `_filterTag()` reads the badges,
     * which also means it is scoped to the bucket on screen for free: the grid
     * only ever holds the current view.
     *
     * @param {string} query what the user typed
     */
    _filterText: function (query) {
        var terms = normalizeForFilter(query).split(/\s+/).filter(function (t) {
            return t.length > 0;
        });

        if (terms.length === 0) {
            this.showAll();
            return;
        }

        this._isotope.arrange({
            filter: function (index, elem) {
                var haystack = normalizeForFilter([
                    (elem.querySelector('.note-title') || {}).textContent,
                    (elem.querySelector('.note-content') || {}).textContent,
                    Array.prototype.map.call(
                        elem.querySelectorAll('.slim-tag'),
                        function (tag) { return tag.textContent; }
                    ).join(' ')
                ].join(' '));

                return terms.every(function (term) {
                    return haystack.indexOf(term) !== -1;
                });
            }
        });

        this._afterFilter();
    },
    /**
     * Say so when a filter leaves the grid empty.
     *
     * Isotope hides the items it filters out, so without this the user is left
     * looking at a blank area with no way of telling a filter that matched
     * nothing from a bucket that is empty — the template's own empty state only
     * covers the second case. Every filter ends here, not just the text one.
     */
    _afterFilter: function () {
        if (!this._isotope) {
            return;
        }

        var matched = (this._isotope.filteredItems || []).length;
        $('#no-matches').toggle(matched === 0);

        // The grid itself is deliberately left alone. Hiding the container
        // looks like the obvious thing and breaks isotope: it hides an item by
        // fading it and only writes `display: none` when that transition ends,
        // which never happens inside a hidden subtree — so the last item
        // standing came back as a ghost the next time a query matched
        // something. An isotope with everything filtered out lays itself out at
        // zero height anyway, so there is nothing to hide.
    },
    /**
     * Apply the text filter after a debounce, and remember it.
     *
     * Also keeps the url in step, so a filtered grid can be linked and survives
     * a reload — the same `?t=` / `?c=` / `?r=` convention the other filters
     * follow, here as `?q=`.
     *
     * @param {string} query what the user typed
     * @param {boolean} [immediate] skip the debounce (a cleared field, a reload)
     */
    /**
     * Swap between the navigation entry and the field.
     *
     * @param {boolean} open true to show the field
     * @param {boolean} [focus] false to leave the focus where it is; a filter
     *        that comes from the url should not steal the caret
     */
    _showTextFilter: function (open, focus) {
        $('#note-filter-fixed').toggleClass('open', open);
        $('#filter-notes').toggle(!open);
        $('#note-filter-clear').toggle(open && this._query.length > 0);

        if (open && focus !== false) {
            $('#note-filter').focus();
        }
    },
    /**
     * Drop the filter and put the entry back. What Escape and the × do.
     */
    _closeTextFilter: function () {
        $('#note-filter').val('');
        this._setTextFilter('', true);
        this._showTextFilter(false);
    },
    _setTextFilter: function (query, immediate) {
        var self = this;

        this._query = query;

        // A filter with something in it is always on screen: it is the only
        // thing telling the user why the grid is short. Never the other way
        // round — collapsing on an empty field would pull it out from under the
        // caret while they are deleting what they typed.
        if (query.length > 0) {
            this._showTextFilter(true, false);
        }

        $('#note-filter-clear').toggle(query.length > 0);


        clearTimeout(this._filterTimer);

        var apply = function () {
            // The other filters are exclusive with each other, and this one is
            // no different: typing means "show me these", not "these among the
            // ones the tag I clicked before left".
            self._cleanNavigation();
            self._filterText(self._query);
            setFilterUrl(self._query.length ? 'q' : undefined,
                         self._query.length ? self._query : undefined);
        };

        if (immediate) {
            apply();
        } else {
            this._filterTimer = setTimeout(apply, 150);
        }
    },
    _filterTag: function (tagId) {
        this._isotope.arrange({
            filter: function(index, elem) {
                var match = false;
                var tags = elem.querySelectorAll('.slim-tag');
                tags.forEach (function(tagItem) {
                    if (tagId == tagItem.getAttribute('tag-id'))
                        match = true;
                });
                return match;
            }
        });
        this._afterFilter();
    },
    /**
     * Keep only the notes that carry a reminder.
     *
     * The badge is rendered by note-item.handlebars precisely when the note has
     * a `reminderAt`, so its presence in the DOM is the answer — the same trick
     * the shared filters use with `.shared` / `.shareowner`.
     */
    _filterReminders: function () {
        this._isotope.arrange({
            filter: function(index, elem) {
                return elem.querySelector('.slim-reminder') != null;
            }
        });
        this._afterFilter();
    },
    _filterColor: function (color) {
        this._isotope.arrange({
            filter: function(index, elem) {
                return color == elem.firstElementChild.style["background-color"];
            }
        });
        this._afterFilter();
    },
    _selectColor: function (color) {
        var circles = $("#colors-folder")[0].getElementsByClassName("circle-toolbar");
        $.each(circles, function(i, c) {
            if (color == c.style.backgroundColor) {
                c.className += " icon-filter-checkmark";
            }
        });
    },
    _cleanNavigation: function () {
        var navItems = $('#app-navigation .active');
        $.each(navItems, function(i, item) {
            $(item).removeClass('active');
        });
        var oldColorTool = $('#app-navigation .circle-toolbar.icon-filter-checkmark');
        $.each(oldColorTool, function(i, oct) {
            $(oct).removeClass('icon-filter-checkmark');
        });
    },
    // Soft-delete the note and re-render the view.
    _trashNote: function (note, gridnote) {
        var self = this;
        this._notes.trash(note).done(function () {
            if (self._currentView === 'all' && self._notes.getAll().length > 0) {
                self._isotope.remove(gridnote.parent());
                self._isotope.layout();
                self.renderNavigation();
            } else {
                self.render();
            }
        }).fail(function () {
            QnDialogs.error(t('quicknotes', 'Could not move note to trash'));
        });
    },
    // Archive the note and re-render the view.
    _archiveNote: function (note, gridnote) {
        var self = this;
        this._notes.archive(note).done(function () {
            if (self._currentView === 'all' && self._notes.getAll().length > 0) {
                self._isotope.remove(gridnote.parent());
                self._isotope.layout();
                self.renderNavigation();
            } else {
                self.render();
            }
        }).fail(function () {
            QnDialogs.error(t('quicknotes', 'Could not archive note'));
        });
    },
    // Unarchive the note and re-render the view.
    _unarchiveNote: function (note, gridnote) {
        var self = this;
        this._notes.unarchive(note).done(function () {
            if (self._currentView === 'archived' && self._notes.getArchived().length > 0) {
                self._isotope.remove(gridnote.parent());
                self._isotope.layout();
                self.renderNavigation();
            } else {
                self.render();
            }
        }).fail(function () {
            QnDialogs.error(t('quicknotes', 'Could not unarchive note'));
        });
    },
    // Restore the note from trash and re-render the view.
    _restoreNote: function (note, gridnote) {
        var self = this;
        this._notes.restore(note).done(function () {
            if (self._currentView === 'trash' && self._notes.getDeleted().length > 0) {
                self._isotope.remove(gridnote.parent());
                self._isotope.layout();
                self.renderNavigation();
            } else {
                self.render();
            }
        }).fail(function () {
            QnDialogs.error(t('quicknotes', 'Could not restore note'));
        });
    },
    // Hard-delete a soft-deleted note (only used from the trash view).
    _purgeNote: function (note, gridnote) {
        var self = this;
        OC.dialogs.confirm(
            t('quicknotes', 'Permanently delete this note? This cannot be undone.'),
            t('quicknotes', 'Delete permanently'),
            function (result) {
                if (!result) return;
                self._notes.remove(note).done(function () {
                    if (self._notes.getDeleted().length > 0) {
                        self._isotope.remove(gridnote.parent());
                        self._isotope.layout();
                        self.renderNavigation();
                    } else {
                        self.render();
                    }
                }).fail(function () {
                    QnDialogs.error(t('quicknotes', 'Could not delete note, not found'));
                });
            },
            true
        );
    },
    // Destroy everything in the trash at once. _purgeNote() is the same
    // thing for a single note; the background job does it a week later for
    // whoever never comes back here.
    _emptyTrash: function () {
        var self = this;
        OC.dialogs.confirm(
            t('quicknotes', 'Permanently delete every note in the trash? This cannot be undone.'),
            t('quicknotes', 'Empty trash'),
            function (result) {
                if (!result) return;
                self._notes.emptyTrash().done(function () {
                    self.render();
                }).fail(function () {
                    QnDialogs.error(t('quicknotes', 'Could not empty the trash'));
                });
            },
            true
        );
    },
    // Forget a note shared with the current user.
    _forgetSharedNote: function (note, gridnote) {
        var self = this;
        OC.dialogs.confirm(
            t('quicknotes', 'Leave this shared note?'),
            t('quicknotes', 'Leave shared note'),
            function (result) {
                if (!result) return;
                self._notes.forgetShare(note).done(function () {
                    if (self._notes.getAll().length > 0) {
                        self._isotope.remove(gridnote.parent());
                        self._isotope.layout();
                        self.renderNavigation();
                    } else {
                        self.render();
                    }
                }).fail(function (xhr) {
                    // A note that reaches the user through a group is not
                    // theirs to leave: dropping that share would take the note
                    // from everybody else in the group too.
                    if (xhr && xhr.status === 404) {
                        QnDialogs.error(t('quicknotes', 'This note is shared with a group you belong to, so only its owner can stop sharing it'));
                        return;
                    }
                    QnDialogs.error(t('quicknotes', 'Could not leave the shared note'));
                });
            },
            true
        );
    },
    render: function () {
        this.renderNavigation();
        this.renderContent();
        this.renderSettings();
    }
};

var getExplicitSaveSetting = function () {
    var explicitSave = localStorage.getItem('explicit-save');
    if (explicitSave === null) return true;
    return (explicitSave === 'true');
}

var setExplicitSaveSetting = function (explicit) {
    localStorage.setItem('explicit-save', explicit ? 'true' : 'false');
}

var getSortBy = function () {
    var sortBy = localStorage.getItem('quicknotes-sort-by');
    if (sortBy === null) return 'title';
    return sortBy;
}

var setSortBy = function (sortBy) {
    localStorage.setItem('quicknotes-sort-by', sortBy);
}

/**
 * Get the filter as URL parameter
 */
var getFilterUrl = function (filterParam) {
    var filter = undefined;
    var parser = document.createElement('a');
    parser.href = window.location.href;
    var query = parser.search.substring(1);
    var vars = query.split('&');
    for (var i = 0; i < vars.length; i++) {
        var pair = vars[i].split('=');
        if (pair[0] === filterParam) {
            filter = decodeURIComponent(pair[1]);
            break;
        }
    }
    return filter;
};

/**
 *  Change the URL location with query as parameter
 */
var setFilterUrl = function (filterParam, filter) {
    var cleanUrl = window.location.href.split("?")[0];
    var title = t('quicknotes', 'Quick notes');
    if (filter) {
        cleanUrl += '?'+ filterParam + '=' + encodeURIComponent(filter);
    }
    window.history.replaceState({}, title, cleanUrl);
    document.title = title;
};

/**
 * Add Helpers to handlebars
 */

// Until Nextcloud 33 the server registered this one on the Handlebars instance
// it shipped (core/src/OC/l10n.js). The app now brings its own Handlebars, so
// it has to register it too.
Handlebars.registerHelper('t', function(app, text) {
    return t(app, text);
});

Handlebars.registerHelper('tSW', function(user) {
    return t('quicknotes', 'Shared with {user}', {user: user});
});

Handlebars.registerHelper('tSB', function(user) {
    return t('quicknotes', 'Shared by {user}', {user: user});
});

// The tooltip of a share badge. A share now says who it reaches — a user or a
// whole group — and what it lets them do, which is more than the {{tSW}} of a
// plain user name could express.
Handlebars.registerHelper('tShare', function(share) {
    var name = share.displayName;
    if (share.shareType === 1) {
        return share.canEdit
            ? t('quicknotes', 'Shared with the group {group}, can edit', {group: name})
            : t('quicknotes', 'Shared with the group {group}', {group: name});
    }
    return share.canEdit
        ? t('quicknotes', 'Shared with {user}, can edit', {user: name})
        : t('quicknotes', 'Shared with {user}', {user: name});
});

// The number of attachments a card is not showing, as the attribute itself:
// an empty `data-more=""` would still match the selector that draws the badge,
// so it has to be absent rather than empty.
Handlebars.registerHelper('attachtsExtra', function(attachments) {
    var extra = ((attachments || []).length) - 6;
    return extra > 0 ? new Handlebars.SafeString('data-more="' + extra + '"') : '';
});

Handlebars.registerHelper('tNN', function(number) {
    return t('quicknotes', 'Note {number}', {number: number});
});

// Reminders are stored as UTC strings. Turning one into something readable
// needs the locale and the timezone of the browser, and all of that lives in
// the Vue bundle (src/dialogs.js), next to the dialog that produces them.
Handlebars.registerHelper('reminderLabel', function(reminderAt) {
    return QnDialogs.formatReminder(reminderAt);
});

/*
 * Off canvas navigation on narrow screens
 *
 * The server used to open and close it through snap.js, which set the
 * `snapjs-left` class on the body. Nextcloud 34 does not ship snap.js
 * anymore, so the toggle of templates/main.php is wired up here. The class
 * it sets is the one css/not-vue.css watches.
 */

var setNavigationOpen = function (open) {
    $('body').toggleClass('qn-nav-open', open);
    $('#app-navigation-toggle').attr('aria-expanded', open ? 'true' : 'false');
};

$('#app-navigation-toggle').on('click', function (event) {
    event.stopPropagation();
    setNavigationOpen(!$('body').hasClass('qn-nav-open'));
});

/*
 * Settings panel at the foot of the navigation
 *
 * The server used to open it itself: `data-apps-slide-toggle` on the button
 * was picked up by a jQuery plugin of the core that slid the target open.
 * Nextcloud 34 dropped it along with jQuery, so the button did nothing at all
 * and the panel — which renderSettings() had already filled — stayed at the
 * `display: none` the server's own CSS gives it.
 *
 * That CSS is still there and still keys off `#app-settings.opened`, so
 * setting the class is all that is needed.
 *
 * It does not close on an outside click, unlike the plugin: the panel holds a
 * colour picker and two checkboxes, and closing while the user is aiming at
 * one of them was worse than leaving it to the button.
 */
$('.settings-button').on('click', function (event) {
    event.stopPropagation();
    var opened = $('#app-settings').toggleClass('opened').hasClass('opened');
    $(this).attr('aria-expanded', opened ? 'true' : 'false');
});

// Picking an entry, or reaching for the notes, closes it again. Collapsing a
// section or opening the settings does not, since the user is still busy in
// the navigation.
$(document).on('click', '#app-navigation', function (event) {
    if ($(event.target).closest('.collapse, #app-settings').length) {
        return;
    }
    setNavigationOpen(false);
});

$(document).on('click', '#app-content', function () {
    if ($('body').hasClass('qn-nav-open')) {
        setNavigationOpen(false);
    }
});

/*
 * Create modules
 */
var notes = new window.QuickNotesNotes(OC.generateUrl('/apps/quicknotes/notes'));
var view = new View(notes);

/*
 * Render initial loading view
 */
view.renderContent();

/*
 * Loading notes and render final view.
 */
/*
 * A shared note can change while this page sits open, so the list is picked up
 * again whenever the tab comes back to the front. `_refresh()` decides whether
 * anything actually needs redrawing, and stays out of the way while a note is
 * being edited.
 */
$(window).on('focus', function () {
    view._refresh(false);
});

document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        view._refresh(false);
    }
});

notes.load().done(function () {
    view.render();

    var noteId = getFilterUrl('n');
    if (noteId !== undefined)
        view._filterNote(noteId);

    var tagId = getFilterUrl('t');
    if (tagId !== undefined)
        view._filterTag(tagId);

    var query = getFilterUrl('q');
    if (query !== undefined && query.length > 0) {
        $('#note-filter').val(query);
        view._setTextFilter(query, true);
    }

    var color = getFilterUrl('c');
    if (color !== undefined) {
        view._selectColor(color);
        view._filterColor(color);
    }

    if (getFilterUrl('r') !== undefined) {
        $('#reminder-notes').addClass('active');
        view._filterReminders();
    }
}).fail(function () {
    QnDialogs.error(t('quicknotes', 'Could not load notes'));
});


});

})(OC, window, jQuery);
