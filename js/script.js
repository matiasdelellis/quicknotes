/**
 * NextCloud/ownCloud - quicknotes
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 * @copyright Matias De lellis 2016-2019
 */

(function (OC, window, $, undefined) {
'use strict';

$(document).ready(function () {

// this notes object holds all our notes
var Notes = function (baseUrl) {
    this._baseUrl = baseUrl;
    this._notes = [];
    this._activeNote = undefined;
    this._loaded = false;
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
    length: function () {
        return this._notes.length;
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
            self._loaded = true;
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    isLoaded: function () {
        return this._loaded;
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

// this notes object holds all our tags
var Tags = function (baseUrl) {
    this._baseUrl = baseUrl;
    this._tags = [];
    this._loaded = false;
};

Tags.prototype = {
    loadAll: function () {
        var deferred = $.Deferred();
        var self = this;
        $.get(this._baseUrl).done(function (tags) {
            self._tags = tags.reverse();
            self._loaded = true;
            deferred.resolve();
        }).fail(function () {
            deferred.reject();
        });
        return deferred.promise();
    },
    isLoaded: function () {
        return this._loaded;
    },
    getAll: function () {
        return this._tags;
    }
};

// this will be the view that is used to update the html
var View = function (notes, tags) {
    this._notes = notes;
    this._tags = tags;
};

View.prototype = {

    showAll: function () {
        //self._notes.unsetActive();
        $('.notes-grid').isotope({ filter: '*'});
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
    editNote: function (id) {
        var modal = $('#modal-note-div');
        var modaltitle = $('#modal-note-div #title-editable');
        var modalcontent = $('#modal-note-div #content-editable');
        var modalnote = $("#modal-note-div .quicknote");

        var note = $('.notes-grid [data-id=' + id + ']').parent();

        var title = note.find(".note-title").html();
        var content = note.find(".note-content").html();
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

        var autolist = new AutoList();
        var editor = new MediumEditor(modalcontent, {
            toolbar: {
                buttons: [
                    'bold',
                    'italic',
                    'underline',
                    'strikethrough',
                    'unorderedlist','orderedlist',
                    'quote',
                    'removeFormat'
               ]
            },
            autoLink: true,
            paste: {
                forcePlainText: false,
                cleanPastedHTML: false
            },
            extensions: {
                'autolist': autolist
            }
        });

        /*
        var shareSelect = $('.note-share-select');
        shareSelect.select2({
            placeholder: "Share with users or groups",
            allowClear: true,
        });

        var formData = {
            noteId: id
        }
        $.post(OC.generateUrl('/apps/quicknotes/api/0.1/getusergroups'), formData, function(data) {
            $.each(data.users, function(i, user) {
                var newOption = new Option(user, user , false, false);
                shareSelect.append(newOption);
            });
            shareSelect.trigger('change');

            var sUsers = []
            $.each(data.posUsers, function(i, user) {
                var newOption = new Option(user, user , false, false);
                shareSelect.append(newOption);
                sUsers.push(user);
            });
            shareSelect.val(sUsers);
            shareSelect.trigger('change');
        });
        */

        /* Positioning the modal to the original size */

        $(".modal-content").css({
            "position" : "absolute",
            "left"     : note.offset().left,
            "top"      : note.offset().top,
            "width"    : note.width(),
            "min-height": note.height(),
            "height:"  : "auto"
        });

        /* Animate to center */

        modal.removeClass("hide-modal-note");
        modal.addClass("show-modal-note");

        note.css({"opacity": "0.1"});

        var windowWidth = $(window).width();
        var modalWidth = note.width()*2;
        var modalTop = 150;
        if (windowWidth < modalWidth) {
            modalWidth = windowWidth;
            modalTop = 50;
        }
        $(".modal-content").animate (
            {
               left: (windowWidth / 2 - modalWidth / 2),
               width: modalWidth,
               top: modalTop,
            },
            250,
            function () {
                modalcontent.focus();
            }
        );

    },
    saveNote: function () {
        var id = $("#modal-note-div .quicknote").data('id');

        if (id === -1)
            return;

        var title = $('#modal-note-div #title-editable').html().trim();
        var content = $('#modal-note-div #content-editable').html().trim();
        var color = this.colorToHex($("#modal-note-div .quicknote").css("background-color"));

        /*
        var shareSelect = $('.note-share-select');
        var shares = shareSelect.select2('data');
        for (var i = 0; i < shares.length; i++) {
            var user = shares[i].id;
            var formData = {
                userId : user,
                noteId : id
            };
            $.post(OC.generateUrl('/apps/quicknotes/api/0.1/users/addshare'), formData, function(data){});
        }
        */

        var self = this;
        this._notes.updateId(id, title, content, color).done(function () {
            var modal = $('#modal-note-div');
            var modalnote = $("#modal-note-div .quicknote");
            var modaltitle = $('#modal-note-div #title-editable');
            var modalcontent = $('#modal-note-div #content-editable');

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
    },
    cancelEdit: function () {
        var modal = $('#modal-note-div');
        var modaltitle = $('#modal-note-div #title-editable');
        var modalcontent = $('#modal-note-dive #content-editable');
        var modalcolortools = $("#modal-note-div .circle-toolbar");
        var modalnote = $("#modal-note-div .quicknote");

        var id = $("#modal-note-div .quicknote").data('id');

        /*
        var shareSelect = $('.note-share-select');
        shareSelect.val(null).trigger('change');
        shareSelect.select2('destroy');
        */

        this._notes.unsetActive();

        var note = $('.notes-grid [data-id=' + id + ']').parent();
        modal.fadeOut(
            250,
            function() {
                modal.css({"display": ""});
                modal.removeClass("show-modal-note");
                modal.addClass("hide-modal-note");
                note.css({"opacity": ""});

                // Reset modal
                modalnote.data('id', -1);
                modaltitle.html("");
                modalcontent.html("");

                $.each(modalcolortools, function(i, colortool) {
                    $(colortool).removeClass('icon-checkmark');
                });
            }
        );
    },
    renderContent: function () {
        // Remove all event handlers to prevent double events.
        $("#app-content").off();

        var html = Handlebars.templates['notes']({
            loaded: this._notes.isLoaded(),
            notes: this._notes.getAll(),
            cancelTxt: t('quicknotes', 'Cancel'),
            saveTxt: t('quicknotes', 'Save'),
            loadingMsg: t('quicknotes', 'Looking for your notes'),
            loadingIcon: OC.imagePath('core', 'loading.gif'),
            emptyMsg: t('quicknotes', 'Nothing here. Take your first quick notes'),
            emptyIcon: OC.imagePath('quicknotes', 'app'),
        });
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
            event.stopPropagation();

            if($(this).hasClass('shared')) return; //shares don't allow editing
            var modalnote = $("#modal-note-editable .quicknote");
            var modalid = modalnote.data('id');
            if (modalid > 0) return;

            var id = parseInt($(this).data('id'), 10);

            self.editNote(id);
        });

        // Cancel when click outside the modal.
        $('#app-content').on('click', '.modal-note-background', function (event) {
            event.stopPropagation();
            self.cancelEdit();
        });

        // Handle hotkeys
        $(document).keyup(function(event) {
            if (event.keyCode == 27) {
                event.stopPropagation();
                self.cancelEdit();
            }
            else if (event.keyCode == 13 && event.altKey) {
                event.stopPropagation();
                self.saveNote();
            }
        });

        // Remove note icon
        var self = this;
        $('#app-content').on("click", ".icon-delete-note", function (event) {
            event.stopPropagation();

            var note = $(this).parent().parent();
            var id = parseInt(note.data('id'), 10);

            self._notes.load(id);
            OC.dialogs.confirm(
                t('quicknotes', 'Are you sure you want to delete the note?'),
                t('quicknotes', 'Delete note'),
                function(result) {
                    if (result) {
                        self._notes.removeActive().done(function () {
                            if (self._notes.length() > 0) {
                                $(".notes-grid").isotope('remove', note.parent())
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

        // handle share editing notes.
        $('#modal-note-div #share-button').click(function (event) {
           var id = $('.note-active').data('id');
           var formData = {
                noteId: id
           }
           $.post(OC.generateUrl('/apps/quicknotes/api/0.1/getusergroups'), formData, function(data) {
                var shareOptions = $('#note-share-options');
                var groups = data.groups;
                var users = data.users;
                var pos_groups = data.posGroups;
                var pos_users = data.posUsers;
                var neg = $('#share-neg');
                var pos = $('#share-pos');
                var sear = $('#share-search');
                for(var i=0; i<groups.length; i++) {
                    var li = document.createElement('li');
                    li.appendChild(document.createTextNode(groups[i]));
                    var sp = document.createElement('span');
                    sp.appendChild(document.createTextNode('(group)'));
                    li.className = "unselected-share";
                    li.appendChild(sp);
                    $(li).hide();
                    neg[0].appendChild(li);
                }
                for(var i=0; i<users.length; i++) {
                    var li = document.createElement('li');
                    li.appendChild(document.createTextNode(users[i]));
                    li.className = "unselected-share";
                    $(li).hide();
                    neg[0].appendChild(li);
                }
                for(var i=0; i<pos_groups.length; i++) {
                    var li = document.createElement('li');
                    li.appendChild(document.createTextNode(pos_groups[i]));
                    var sp = document.createElement('span');
                    sp.appendChild(document.createTextNode('(group)'));
                    li.className = "selected-share";
                    li.appendChild(sp);
                    pos[0].appendChild(li);
                }
                for(var i=0; i<pos_users.length; i++) {
                    var li = document.createElement('li');
                    li.appendChild(document.createTextNode(pos_users[i]));
                    li.className = "selected-share";
                    pos[0].appendChild(li);
                }

                $('.unselected-share').click(moveToSelectedShare);
                $('.selected-share').click(moveToUnselectedShare);

                shareOptions.show();
                var modalNote = $('.note-active');
                var startHeight = modalNote.outerHeight(true);
                modalNote.outerHeight(startHeight + shareOptions.outerHeight(true));
                sear.on('input', function() {
                    var val = $(this).val().toLowerCase().trim();
                    var lis = neg.children();
                    if(val.length == 0) {
                        lis.hide();
                    } else {
                        for(var i=0; i<lis.length; i++) {
                            if(lis[i].innerHTML.toLowerCase().indexOf(val) >= 0) {
                                $(lis[i]).show();
                            } else {
                                $(lis[i]).hide();
                            }
                        }
                    }
                    modalNote.outerHeight(startHeight + shareOptions.outerHeight(true));
                });
           });
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
            tags: this._tags.getAll(),
            newNoteTxt: t('quicknotes', 'New note'),
            allNotesTxt: t('quicknotes', 'All notes'),
            colorsTxt: t('quicknotes', 'Colors'),
            notesTxt: t('quicknotes', 'Notes'),
            tagsTxt: t('quicknotes', 'Tags'),
        });

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
            var note = {
                title: t('quicknotes', 'New note'),
                content: '',
                color: '#F7EB96'
            };
            self._notes.create(note).done(function() {
                if (self._notes.length() > 1) {
                    note = self._notes.getActive();
                    var $notehtml = $(Handlebars.templates['note-item']({
                        color: note.color,
                        id: note.id,
                        title: note.title,
                        content: note.content,
                        timestamp: note.timestamp,
                    }));

                    $(".notes-grid").prepend($notehtml)
                                    .isotope('prepended', $notehtml)
                                    .isotope({ filter: '*'})
                                    .isotope('layout');
                    self._notes.unsetActive();
                    self.renderNavigation();
                } else {
                    self._notes.unsetActive();
                    self.render();
                }
            }).fail(function () {
                alert('Could not create note');
            });
        });

        $('#colors-folder').click(function () {
            $(this).toggleClass("open");
        });

        $('#colors-folder > ul').click(function (event) {
            event.stopPropagation();
        });

        $('#notes-folder').click(function () {
            $(this).toggleClass("open");
        });

        // show a note
        $('#app-navigation .note > a').click(function (event) {
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

        // Handle colors.
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

    },
    render: function () {
        this.renderNavigation();
        this.renderContent();
    }
};

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
var tags = new Tags(OC.generateUrl('/apps/quicknotes/tags'));

var view = new View(notes, tags);

/*
 * Render initial loading view
 */
view.renderContent();

/*
 * Loading notes and render final view.
 */
notes.loadAll().done(function () {
    tags.loadAll().done(function () {
        view.render();
    }).fail(function () {
        alert('Could not load tags');
    });
    // FIXME: So ugly...
}).fail(function () {
    alert('Could not load notes');
});


});

})(OC, window, jQuery);
