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

(function (OC, $, undefined) {
'use strict';

// Who a note can be shared with used to be answered here, by asking the OCS
// sharees API of files_sharing for every user on the instance as soon as the
// app loaded, whether the user ever opened the share dialog or not. It is a
// server side endpoint of this app now (`/notes/{id}/sharees`), searched as
// the user types from the share dialog itself: it knows about groups, honours
// the enumeration settings of the instance, and leaves out the people the note
// is already shared with. See src/SharesService.js.

// Throw a TypeError if `id` is not a positive integer; used to guard
// URL composition against undefined/NaN/string ids. Accepts both numbers
// and numeric strings (jQuery .attr() always returns strings) by
// coercing through Number first.
function assertId(id) {
    var n = (typeof id === 'string') ? Number(id) : id;
    if (!Number.isInteger(n) || n < 0) {
        throw new TypeError('Notes: expected a positive integer id, got ' + id);
    }
    return n;
}

// This object holds all our notes and encapsulates the REST calls
// against /apps/quicknotes/notes. It is consumed by View in script.js.
var Notes = function (baseUrl) {
    this._baseUrl = baseUrl;
    this._notes = [];
    this._archived = [];
    this._deleted = [];
    this._loaded = false;
};

Notes.prototype = {
    // Load notes from backend.
    load: function () {
        var self = this;
        return $.get(this._baseUrl)
            .then(function (notes) {
                self._splitBuckets(notes.reverse());
                self._loaded = true;
            });
    },
    // Partition a list of notes into the active / archived / deleted
    // buckets, in that order. Notes are placed in exactly one bucket:
    // a note with deletedAt wins over a note with archivedAt.
    _splitBuckets: function (notes) {
        var active = [];
        var archived = [];
        var deleted = [];
        notes.forEach(function (n) {
            if (n.deletedAt) {
                deleted.push(n);
            } else if (n.archivedAt) {
                archived.push(n);
            } else {
                active.push(n);
            }
        });
        this._notes = active;
        this._archived = archived;
        this._deleted = deleted;
    },
    // Check that all the notes were loaded.
    isLoaded: function () {
        return this._loaded;
    },
    // Get the amount of active notes.
    length: function () {
        return this._notes.length;
    },
    // Get all active notes.
    getAll: function () {
        return this._notes;
    },
    // Get all archived notes.
    getArchived: function () {
        return this._archived;
    },
    // Get all soft-deleted notes.
    getDeleted: function () {
        return this._deleted;
    },
    // Get the colors used in the active notes
    getColors: function () {
        var seen = {};
        var Ccolors = [];
        this._notes.forEach(function (note) {
            if (!seen[note.color]) {
                seen[note.color] = true;
                Ccolors.push({color: note.color});
            }
        });
        return Ccolors;
    },
    // Get the tags used in the active notes
    getTags: function () {
        var seen = {};
        var tags = [];
        this._notes.forEach(function (note) {
            note.tags.forEach(function (tag) {
                if (!seen[tag.id]) {
                    seen[tag.id] = true;
                    tags.push(tag);
                }
            });
        });
        return tags;
    },
    // CRUD Create: Need a note template to have the translated title.
    create: function (noteTemplate) {
        return this._request('POST', this._baseUrl, noteTemplate, function (note) {
            this._notes.unshift(note);
            return note;
        }.bind(this));
    },
    // CRUD Read: Load a note to edit (looks in every bucket).
    read: function (id) {
        assertId(id);
        return this._notes.find(function (note) { return note.id === id; })
            || this._archived.find(function (note) { return note.id === id; })
            || this._deleted.find(function (note) { return note.id === id; });
    },
    // CRUD Update
    //
    // `note.etag` is what the note looked like when it was read. It goes out
    // as If-Match, so the server answers 412 instead of overwriting an edit
    // somebody else made in the meantime — which is a real possibility now
    // that a note can be shared for editing. A note without an etag (a
    // freshly created one) is saved unconditionally, as before.
    //
    // The shares are deliberately *not* part of the payload: they have their
    // own endpoints and are already applied by the time the note is saved.
    // Sending the list the editor happens to hold would let an old tab revoke
    // a share made from a new one.
    update: function (note) {
        var id = assertId(note.id);
        var payload = {
            title: note.title,
            content: note.content,
            color: note.color,
            isPinned: note.isPinned,
            tags: note.tags,
            attachments: note.attachments
        };
        var headers = note.etag ? {'If-Match': '"' + note.etag + '"'} : undefined;
        return this._request('PUT', this._baseUrl + '/' + id, payload, function (dbnote) {
            this._removeFromBuckets(id);
            this._notes.unshift(dbnote);
            return dbnote;
        }.bind(this), headers);
    },
    // Archive a note: POST /notes/{id}/archive. Moves the note from
    // its current bucket to the archived one.
    archive: function (note) {
        var id = assertId(note.id);
        return this._request('POST', this._baseUrl + '/' + id + '/archive', null, function (dbnote) {
            this._removeFromBuckets(id);
            this._archived.unshift(dbnote);
            return dbnote;
        }.bind(this));
    },
    // Unarchive a note: POST /notes/{id}/unarchive. If the note was
    // also in trash it stays there; otherwise it returns to active.
    unarchive: function (note) {
        var id = assertId(note.id);
        return this._request('POST', this._baseUrl + '/' + id + '/unarchive', null, function (dbnote) {
            this._removeFromBuckets(id);
            if (dbnote.deletedAt) {
                this._deleted.unshift(dbnote);
            } else {
                this._notes.unshift(dbnote);
            }
            return dbnote;
        }.bind(this));
    },
    // Restore a note from trash: POST /notes/{id}/restore. Clears
    // deleted_at; if the note is still archived it stays archived,
    // otherwise it returns to active.
    restore: function (note) {
        var id = assertId(note.id);
        return this._request('POST', this._baseUrl + '/' + id + '/restore', null, function (dbnote) {
            this._removeFromBuckets(id);
            if (dbnote.archivedAt) {
                this._archived.unshift(dbnote);
            } else {
                this._notes.unshift(dbnote);
            }
            return dbnote;
        }.bind(this));
    },
    // Soft-delete a note: POST /notes/{id}/trash. Moves the note from
    // its current bucket to the deleted one. No row is removed.
    trash: function (note) {
        var id = assertId(note.id);
        return this._request('POST', this._baseUrl + '/' + id + '/trash', null, function (dbnote) {
            this._removeFromBuckets(id);
            this._deleted.unshift(dbnote);
            return dbnote;
        }.bind(this));
    },
    // Set, move or cancel the reminder of a note: PUT /notes/{id}/reminder.
    // `reminderAt` is a UTC 'Y-m-d H:i:s' string, or null to cancel it.
    // The reminder has its own endpoint rather than travelling with the note,
    // so the editor applies it right after saving the note itself.
    setReminder: function (note, reminderAt) {
        var id = assertId(note.id);
        var url = this._baseUrl + '/' + id + '/reminder';
        return this._request('PUT', url, {reminderAt: reminderAt}, function (dbnote) {
            this._removeFromBuckets(id);
            if (dbnote.deletedAt) {
                this._deleted.unshift(dbnote);
            } else if (dbnote.archivedAt) {
                this._archived.unshift(dbnote);
            } else {
                this._notes.unshift(dbnote);
            }
            return dbnote;
        }.bind(this));
    },
    // Purge a note: hard DELETE /notes/{id}. Used by the trash view
    // to permanently remove a soft-deleted note.
    remove: function (note) {
        var id = assertId(note.id);
        return this._request('DELETE', this._baseUrl + '/' + id, null, function () {
            this._removeFromBuckets(id);
        }.bind(this));
    },
    // Empty the trash: hard DELETE /notes/trash. Everything the user has in
    // there goes at once, and the local bucket is emptied with it.
    emptyTrash: function () {
        return this._request('DELETE', this._baseUrl + '/trash', null, function (answer) {
            this._deleted = [];
            return answer;
        }.bind(this));
    },
    // Leave a note somebody shared with the current user. Only a share made
    // with them personally can be left: one that reaches them through a group
    // is not theirs to drop, and the server answers 404 for it.
    forgetShare: function (note) {
        var id = assertId(note.id);
        return this._request('DELETE', this._baseUrl + '/' + id + '/shares/self', null, function () {
            this._removeFromBuckets(id);
        }.bind(this));
    },
    // Resolve a file picked from the Files app into an attachment payload
    // ({file_id, preview_url, redirect_url, deep_link_url}). Nextcloud 34
    // removed the OC.Files javascript client, so the file id lookup is done
    // server side from the path the picker returns.
    getAttachmentInfo: function (path) {
        return $.get(OC.generateUrl('/apps/quicknotes/api/v1/attachments/info'), {path: path});
    },
    // Put a note the server sent back in the place of the copy held here,
    // in whichever bucket its state says it belongs to. Used when a save is
    // refused because the note changed elsewhere and the fresh copy comes
    // back with the rejection.
    replace: function (note) {
        var id = assertId(note.id);
        this._removeFromBuckets(id);
        if (note.deletedAt) {
            this._deleted.unshift(note);
        } else if (note.archivedAt) {
            this._archived.unshift(note);
        } else {
            this._notes.unshift(note);
        }
        return note;
    },
    // Helper: drop the note with the given id from whichever bucket
    // it currently lives in.
    _removeFromBuckets: function (id) {
        var removeFrom = function (arr) {
            var i = arr.findIndex(function (n) { return n.id === id; });
            if (i !== -1) arr.splice(i, 1);
        };
        removeFrom(this._notes);
        removeFrom(this._archived);
        removeFrom(this._deleted);
    },
    // Perform a JSON request against the notes API and apply `onSuccess`
    // (with `this` bound to the Notes instance) to the response. `onSuccess`
    // is optional and its return value is forwarded to the caller.
    _request: function (method, url, body, onSuccess, headers) {
        var options = {
            url: url,
            method: method,
            contentType: 'application/json'
        };
        if (body !== null) {
            options.data = JSON.stringify(body);
        }
        if (headers) {
            options.headers = headers;
        }
        var promise = $.ajax(options);
        if (onSuccess) {
            promise = promise.then(function (response) {
                return onSuccess(response);
            });
        }
        return promise;
    },
    // A cheap description of everything currently loaded, used to tell
    // whether a reload actually brought anything new before throwing the grid
    // away and rendering it again. The etag covers the title and the content,
    // so an edit by somebody else shows up here even within the same second.
    signature: function () {
        var parts = [];
        [this._notes, this._archived, this._deleted].forEach(function (bucket) {
            bucket.forEach(function (note) {
                parts.push(note.id + ':' + note.etag + ':' + (note.isPinned ? 1 : 0));
            });
        });
        return parts.sort().join('|');
    }
};

// Expose to other scripts loaded after this one (View in script.js).
window.QuickNotesNotes = Notes;

})(OC, jQuery);
