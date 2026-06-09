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

// Defensive references: if a host page forgot to expose the OCS helpers,
// the sharees API would throw on first use. Fall back to a manual
// composition against the apps path.
var ocsBasePath = (OC.linkToOCS && OC.linkToOCS('apps/files_sharing/api/v1/', 2))
    || '/ocs/v2.php/apps/files_sharing/api/v1/';

var shareesParams = {
    format: 'json',
    perPage: 200,
    itemType: 0
};

// Convert the raw sharees API payload into a flat list of
// {id, text} objects (or [shareWith, label] pairs).
function parseSharees(shares) {
    var users = [];
    var d = shares && shares.ocs && shares.ocs.data;
    if (!d) return users;
    ['exact', ''].forEach(function (bucket) {
        var list = bucket === 'exact' ? d.exact && d.exact.users : d.users;
        if (list) {
            list.forEach(function (user) {
                users.push({id: user.value.shareWith, text: user.label});
            });
        }
    });
    return users;
}

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

    this._usersSharing = [];
    this._loadUsersSharing();
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
    getUsersSharing: function () {
        return this._usersSharing;
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
    update: function (note) {
        var id = assertId(note.id);
        return this._request('PUT', this._baseUrl + '/' + id, note, function (dbnote) {
            this._removeFromBuckets(id);
            this._notes.unshift(dbnote);
            return dbnote;
        }.bind(this));
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
    // Purge a note: hard DELETE /notes/{id}. Used by the trash view
    // to permanently remove a soft-deleted note.
    remove: function (note) {
        var id = assertId(note.id);
        return this._request('DELETE', this._baseUrl + '/' + id, null, function () {
            this._removeFromBuckets(id);
        }.bind(this));
    },
    // Delete a shared note (i.e. one shared with the current user).
    forgetShare: function (note) {
        var id = assertId(note.id);
        return this._request('DELETE', OC.generateUrl('/apps/quicknotes/share') + '/' + id, null, function () {
            this._removeFromBuckets(id);
        }.bind(this));
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
    _request: function (method, url, body, onSuccess) {
        var options = {
            url: url,
            method: method,
            contentType: 'application/json'
        };
        if (body !== null) {
            options.data = JSON.stringify(body);
        }
        var promise = $.ajax(options);
        if (onSuccess) {
            promise = promise.then(function (response) {
                return onSuccess(response);
            });
        }
        return promise;
    },
    // Get the users to share notes with.
    _loadUsersSharing: function () {
        this._usersSharing = [];
        this._loadUsersSharingPage(1);
    },
    _loadUsersSharingPage: function (page) {
        var self = this;
        $.extend(shareesParams, {search: '', page: page});
        return $.get(ocsBasePath + 'sharees', shareesParams, {headers: {'OCS-APIREQUEST': true}})
            .then(function (shares) {
                parseSharees(shares).forEach(function (u) {
                    self._usersSharing.push([u.id, u.text]);
                });
            })
            .fail(function () {
                console.error("Could not get users to share.");
            });
    },
    // Search users dynamically via the sharees API.
    searchUsersSharing: function (query, callback) {
        $.extend(shareesParams, {search: query, page: 1});
        return $.get(ocsBasePath + 'sharees', shareesParams, {headers: {'OCS-APIREQUEST': true}})
            .then(function (shares) {
                callback(parseSharees(shares));
            })
            .fail(function () {
                callback([]);
            });
    }
};

// Expose to other scripts loaded after this one (View in script.js).
window.QuickNotesNotes = Notes;

})(OC, jQuery);
