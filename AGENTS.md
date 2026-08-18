# AGENTS.md — Quick notes (Nextcloud app)

## What this is
A Nextcloud app (app id `quicknotes`, namespace `OCA\QuickNotes`) that provides rich‑text notes with colors, tags, attachments, sharing, reminders, dashboard widget, Talk integration, and a search provider.

- Entry points:
  - PHP bootstrap: `lib/AppInfo/Application.php` (registers search provider, capabilities, dashboard widget, notifier, calendar provider, `BeforeTemplateRendered` listener, navigation entry).
  - Routes: `appinfo/routes.php` (page `#index` at `/`, API under `/api/v1/...`).
  - DB layer: `lib/Db/*` (mappers for Note, NoteTag, NoteShare, Color, Tag, Attach).
  - Front‑end bundles: `webpack.config.js` builds **three** entries from `src/`:
    - `src/dashboard.js` → `js/quicknotes-dashboard.js` (Dashboard widget, Vue 2)
    - `src/talk.js` → `js/quicknotes-talk.js` (Talk message action, `OCA.Talk.registerMessageAction`)
    - `src/legacy.js` → `js/quicknotes-legacy.js` (jQuery global + `window.QnDialogs`, see below)
  - Legacy non‑Vue scripts still live in `js/` (`script.js`, `notes-api.js`, `modal-view.js`, `qn-*`) and are loaded by the server‑rendered page via `templates/main.php`.

## Build / install

- One‑shot build (installs npm deps, copies vendored libs, precompiles Handlebars, runs webpack):
  ```
  make
  ```
  This is equivalent to `make build` → `make depsmin` + `js-templates` + `build-vue`. The Makefile assumes it is run with this repo as `../quicknotes` next to a Nextcloud install (paths like `$(project_dir)=$(CURDIR)/../quicknotes` and `../../occ`).
- Per‑step equivalents if you can't use the Makefile:
  - `npm install` then `cp node_modules/handlebars/dist/handlebars.min.js js/vendor/handlebars.js` (and the other 5 libs in `Makefile:77‑82`).
  - `node_modules/handlebars/bin/handlebars js/templates -f js/templates.js` — **must** be re‑run after editing any `js/templates/*.handlebars`.
  - `npm run build` (webpack via `@nextcloud/webpack-vue-config`).
  - If `node` / `npm` is not installed on the host, use `./docker/scripts/build.sh` — it runs the whole front‑end build (Handlebars precompile, vendor copy, webpack) inside the `builder` container using its Node 20 toolchain. Don't try to run `node` or `npm` directly on the host.
- Generated/ignored artifacts (do not edit by hand, they are gitignored):
  `js/templates.js`, `js/quicknotes-*.js*`, `js/vendor/*`, `css/vendor/*`, `vendor/`, `node_modules/`, `translationfiles/`, `translationtool.phar`, `build/`.

## Docker dev environment (preferred for end‑to‑end work)

`docker/` runs Nextcloud 34 + MariaDB 11.4 + a Node 20 `builder` service, and bind‑mounts the repo into `custom_apps/quicknotes`.

- Start: `./docker/scripts/up.sh` (expects `.env` at the repo root — copy from `docker/.env.example` first; the script reads it and passes it to `docker compose`).
- Build front‑end inside the stack: `./docker/scripts/build.sh` (uses the `builder` service; auto‑triggered by `up.sh` if `js/templates.js`, `js/vendor/`, or `js/quicknotes-dashboard.js` are missing).
- Re‑enable the app after PHP/template changes: `./docker/scripts/enable-app.sh` (accepts a service name — default `app`, or `dev`).
- Stop: `./docker/scripts/down.sh` (add `--purge` to wipe DB + Nextcloud data + npm cache + dev volume).
- Run tests: `./docker/scripts/test.sh [unit|integration|all]` — runs phpunit inside the `dev` service, which builds a checkout of `nextcloud/server` with `composer install` already done so phpunit's bootstrap resolves from `apps/quicknotes`. See `docker/README.md` for the full recipe; first‑time build is heavy.
- `.env` is gitignored and intentionally lives at the **repo root**, not in `docker/`, so `docker compose` can interpolate it. Do not move it without updating `up.sh`.
- After editing `.vue`, `.handlebars`, or any JS, hard‑reload the browser (Ctrl+Shift+R) — Nextcloud caches JS aggressively.

## Lint / static analysis / tests

- PHP lint: `composer run lint` (CI runs it on PHP 8.2, 8.3, 8.4, 8.5 — `php -l` over every `.php` outside `vendor/`). `composer install` first to pull the `psalm.phar`.
- Static analysis: `composer run psalm` (uses `psalm.phar`, config in `psalm.xml`, error level 4, scope `lib/`, suppresses a few `OC*`, Doctrine and `Sabre\VObject` classes — the latter are provided by the server at runtime through the dav app, so psalm has no stubs for what `lib/Calendar/` builds its events with).
- Unit tests: `phpunit -c phpunit.xml` (boots `tests/bootstrap.php` which `require_once`s the Nextcloud server's `tests/bootstrap.php` from `../../../tests/bootstrap.php` — i.e. **the app must sit at `nextcloud/apps/quicknotes`** for unit tests to resolve). The `dev` service in `docker/docker-compose.yml` is set up exactly for this. `./docker/scripts/test.sh` is the wrapper.
- Integration tests: `phpunit -c phpunit.integration.xml` (same bootstrap constraint; only one test exists, `tests/integration/AppTest.php`, which boots the app and checks it is installed). `./docker/scripts/test.sh integration` is the wrapper.
- XML schema lint: CI validates `appinfo/info.xml` against `https://apps.nextcloud.com/schema/apps/info.xsd`.
- No JavaScript lint/format scripts are wired up at the repo level; `package.json` only has `build`. `@nextcloud/eslint-config` and `@nextcloud/stylelint-config` are present in `devDependencies` but not invoked — don't assume `npm run lint` exists.
- Browser scripts: `tests/browser/` drives a headless Chrome over the DevTools protocol (needs `websocket-client`, `pillow` and `google-chrome`). It is **not** a test suite — phpunit never looks there — it is what builds the demo board and takes the pictures of the App Store listing. `cdp.py` is the reusable part; read `tests/browser/README.md` before touching the screenshots.

## Reminders and the virtual calendar

A note can carry a reminder **per user**. Personal since 0.9.2: it used to be
two columns on the note, which made it the owner's, and the fifteen people a
note was shared with saw a badge of a date somebody else had picked. A reminder
is somebody deciding when *they* want to be interrupted, so it lives in
`quicknotes_note_states` next to the pin — `reminder_at` and
`reminder_notified_at`, both nullable, both left without an `addType()` in
`lib/Db/NoteState.php` so QBMapper hands back the raw string (same convention
the note columns followed). **Everything server side is UTC `Y-m-d H:i:s`**
(`ReminderService::DATE_FORMAT`).

One per note and user, which the unique index of that table already enforces;
several per person on the same note would need a table of their own.

- **Read access is all it takes.** `NoteService::setReminder()` goes through
  `get()` and not `getOwned()`: the row is the caller's and the note is not
  touched, so a note shared read only can still be one you want to be reminded
  of. The editor shows the reminder button in *both* toolbars for that reason
  (class `.reminder-button`, not the id), and it is the only tool the read-only
  editor offers.
- **It is applied the moment it is picked**, like the shares and unlike
  everything else in the editor: `View._setReminder()` calls the endpoint
  straight away. It used to ride along with the save of the note, which cannot
  work when there is nothing to save.
- Delivery is a plain Nextcloud notification, nothing to do with the calendar:
  `lib/BackgroundJob/NoteReminderJob.php` (every 5 min, registered in
  `info.xml`) asks `ReminderService::notifyDue()`, which notifies **the user
  the state row belongs to** and stamps `reminder_notified_at`.
  `lib/Notification/Notifier.php` renders it.
  - The job is `TIME_SENSITIVE`, unlike `PurgeOldTrashJob`. A late reminder is
    a useless one.
  - **Accuracy is bounded by the cron**, which runs every 5 minutes at best and
    only while somebody browses on AJAX cron. Don't promise the minute.
  - `notifyDue()` **checks access before notifying**, and drops the reminder
    when it is gone. Losing a personal share already deletes the row
    (`ShareService::forgetRecipientState()`), but a group share taken back — or
    a user quietly leaving the group — leaves one behind, and reminding
    somebody of a note they can no longer open is worse than not reminding
    them. This is why `ReminderService` depends on `ShareService`; the reverse
    dependency does not exist, on purpose.
  - `ReminderService::dismiss()` withdraws a pending notification through
    `markProcessed()`, and `dismissForNote()` does it for everybody who armed
    one — which is what the trash and destroy paths call, since a note may
    carry reminders of several people.
  - **`dismiss()` sets the subject**, and that matters: `markProcessed()`
    matches on app + user + object + *whatever else is set*, so without it
    rescheduling a reminder would also wipe the "shared with you" notification
    the same user holds about the same note. The one place that deliberately
    does not scope by subject is `ShareService::dismissNotification()`: losing
    access should take everything about that note with it.
  - Required fields are enforced by the manager and documented in the OCP
    exceptions: app, user, dateTime, objectType, objectId, subject to notify,
    plus parsedSubject to parse. `setPriorityNotification()` looks tempting and
    is useless here: it throws unless the app is on the server's allowlist.
- The **Reminders** entry of the navigation is a *filter*, not a bucket like
  Archive and Trash: `_filterReminders()` leans on isotope and keys off the
  `.slim-reminder` badge being in the DOM, the way the shared entries key off
  `.shared` / `.shareowner`. So there is no fourth list in `notes-api.js`, and
  it only ever narrows the active notes. Its url parameter is `r`.
- The reminder does **not** travel with the note. It has its own endpoint,
  `PUT /notes/{id}/reminder` (both route sets), with `reminderAt` null to
  cancel.
- Timezones: **every conversion lives in `src/dialogs.js`** and nowhere else.
  `QnDialogs.reminder()` takes and returns the UTC string, and
  `QnDialogs.formatReminder()` renders it for display — which is also why
  `js/script.js` registers a `reminderLabel` Handlebars helper that delegates
  to it. Keep it that way; the rest of the legacy code treats the value as an
  opaque string.
- `lib/Calendar/` publishes the reminders as a **read‑only calendar**, off
  unless the user ticks it in the app settings
  (`SettingsService::CALENDAR_ENABLED_KEY`, default false — it shows up in the
  Calendar app and in every CalDAV client, so it is not turned on for people).
  It is per principal and therefore per user: everybody sees the dates they
  armed, on their own notes and on the ones shared with them, and nobody sees
  anybody else's. `NotesCalendar` asks
  `ReminderService::findNotesWithRemindersOf()`, which hands back notes already
  carrying that user's date in the (now transient) `reminderAt` of the entity.
  Nothing is stored: every read derives the events from the tables, which is
  the whole reason for going through a provider instead of writing real events.
  `OCP\Calendar` has no update or delete API (only `ICreateFromString`, which
  is create‑only and throws `UidConflict` on a repeated UID), so a written copy
  could never be kept in sync.
  - It implements the **public** `OCP\Calendar\ICalendarProvider`, not the
    `OCA\DAV\CalDAV\Integration\ICalendarProvider` that Deck uses. The
    `AppCalendarPlugin` of the dav app wraps any OCP provider into an
    `ExternalCalendar`, so one implementation covers `IManager` for other apps
    *and* CalDAV, and so the Calendar app. It lands under the uri
    `app-generated--dav-wrapper--quicknotes`.
  - `ICalendar::search()` must return **`Sabre\VObject\Component\VEvent`
    objects**. The docblock says "arrays of key‑value‑pairs", which is what the
    real CalDAV backend returns, but `AppCalendar` feeds the result straight
    into `new VCalendar(...)`, and only Nodes come out as components there.
  - **`search()` has to honour `['X-FILENAME']`.** `AppCalendar::getChild()`
    tries that first and falls back to `['UID']` only on an empty result, so
    treating the unknown property as "nothing to filter on" and returning
    everything makes *every* `.ics` url of the calendar serve the whole
    calendar. These events carry no `X-FILENAME`, so match against
    `<uid>.ics`, which is the name `CalendarObject::getName()` derives.
  - No `VALARM`, on purpose. These events never reach `calendarobjects`, so the
    `ReminderService` of the dav app would not act on one anyway — but a CalDAV
    client would, and the user would be notified twice.
  - `VEVENT` and not `VTODO`, which would be the closer fit semantically: a
    `VTODO` is only visible if the Tasks app is installed.
- `SettingsService::isCalendarEnabled()` takes an **explicit user id**, because
  the provider runs on the CalDAV path where there is no logged in user — the
  principal is all there is. Every other getter there reads the session user.

## Filtering the grid

The navigation entries other than Archive and Trash are **filters over what is
on screen**, not queries: they call `isotope.arrange({filter: fn})` on the notes
the current view already rendered. `?t=` (tag), `?c=` (colour), `?r=`
(reminders) and `?q=` (text) put the active one in the url so it can be linked
and survives a reload; `_cleanNavigation()` runs first because they are
exclusive with each other.

- **The text filter** (`View._filterText()`, 1.0) matches the title, the body
  and the tag names, read off the DOM the way `_filterTag()` reads the badges —
  which scopes it to the bucket on screen for free. Terms are AND and
  `normalizeForFilter()` strips case and accents, because requiring the right
  accent to find your own note is strictness nobody asked for. It costs nothing
  because every note is already in memory: `Notes.load()` fetches all of them.
  This is *not* the unified search of Nextcloud — that one is server side
  (`NoteSearchProvider`) and lands here as `?n=<id>`. It is called a *filter*
  everywhere for that reason: ids, css, helpers and the label.
- **Collapsed it is an entry of the navigation** (`#filter-notes`) and the field
  (`#note-filter-fixed`, `display: none` until `.open`) takes its place, matching
  the 44px row so nothing below moves. `View._showTextFilter()` swaps them;
  `_closeTextFilter()` — Escape and the × — drops the filter and puts the entry
  back. Two rules the swap follows: a **non-empty** filter is always on screen
  (`_setTextFilter()` forces it open, since it is the only thing explaining why
  the grid is short), and it is **never** collapsed on an empty query, which
  would pull the field out from under the caret while somebody deletes what they
  typed. That is also why `_showTextFilter()` takes a `focus` argument: a filter
  restored from `?q=` must not steal the caret.
- **A re-render has to re-apply it.** `renderContent()` throws the isotope
  arrangement away, so it ends by putting the active query back; the field
  itself is refilled by `renderNavigation()` from `View._query`, since the
  navigation is redrawn from scratch.
- **`View._afterFilter()` is where every filter ends**, and it shows
  `#no-matches` when nothing is left. Two traps it is written around, both found
  in a browser:
  - **Do not hide `#notes-grid-div`.** Isotope hides an item by fading it and
    only writes `display: none` when that transition ends — which never happens
    inside a hidden subtree, so the last item standing came back as a ghost the
    next time a query matched something. An isotope with everything filtered out
    lays itself out at zero height anyway.
  - `#notes-grid-div` is capped at `max-width: 100%` in `css/style.css`: with
    nothing to lay out, isotope's `fitWidth` arithmetic lands on a container
    wider than the space it has (1214px inside 1184px, measured) and puts a
    horizontal scrollbar under the whole app.

## Attachments

An attachment is a **pointer to a real file of whoever attached it** — picked
with the files picker, or uploaded into their "Quicknotes" folder — and
`quicknotes_attach` carries the `user_id` of that person for exactly that
reason. The file is never copied and never shared in Files.

- **The app serves them itself** (0.9.4), through
  `GET /api/v1/notes/{noteId}/attachments/{fileId}/{preview,download}`. Access
  is granted by the *note*: `AttachmentApiController::resolveAttachment()` asks
  `NoteService::get()` whether the caller may see the note, then whether the
  file is really attached to *that* note, and only then reads it with
  `FileService::getFileOf($attach->getUserId(), …)` — the storage of the person
  who attached it. Those three questions are the only thing between a
  recipient and somebody else's files; keep them in that order and keep the
  answer a 404 in all three cases.
- **This is what "sharing a note shares its attachments" means here.** Until
  0.9.4 everything went through `FileService`, which resolved against the
  *session* user, so for somebody the note was shared with the lookup came back
  empty and `hydrate()` dropped the attachment from the response entirely — the
  note arrived with no attachments and no hint that it ever had any. The share
  dialog told people to go and share the files by hand in Files.
- Nothing is shared in Files **on purpose**: no copy, nothing to keep in sync,
  no shares of ours to tell apart from shares the user made, and access ends
  when the note stops being shared. The price is that the file does not land in
  the recipient's Files, so there is no sync and no editing — which is what an
  explicit "share the files too" action would be for, and that action does not
  exist yet.
- `preview_url` and `download_url` therefore always point at the app.
  `redirect_url` / `deep_link_url` point into Files and are **null for anybody
  who cannot reach the file there**; `link_url` is the one the templates use
  and falls back from the first to the download. The one place still using the
  server's `/core/preview` is `AttachmentApi#info` / `#upload`, which answer
  about a file the caller just picked, before the note is saved and before
  there is an attachment to serve.
- **`OCA.Viewer` can open them**, other people's included, and it takes three
  things to work without anything being shared in Files:
  - `OCA.Viewer.open({fileInfo, list, enableSidebar: false})` — a `fileInfo`
    and not a `path`, because a path is resolved against the caller's own
    Files. Inside the viewer, `source` overrides the dav path it would
    otherwise derive (`source: e.source ?? H(e)`), and that is what the image
    and media components load from.
  - **`list` has to be given.** With an empty one, `openFileInfo()` does a
    PROPFIND on the folder of `filename` to build the gallery — a folder that
    is not the viewer's. With one, it says so itself: "A files list have been
    provided. No folder content will be fetched."
  - **`permissions` must *not* be given.** `canEdit` and `canDelete` of the
    viewer are `permissions?.includes('W'|'D')`, and both act on the file over
    WebDAV as the person looking, which is exactly what they cannot do. Left
    out, the buttons are not rendered. Same for `enableSidebar`, since the
    Files sidebar wants a real dav node. `hasPreview` is left false too, so
    nothing goes asking `/core/preview` with a file id that user cannot see —
    the image comes from `source`, i.e. the original file. Handing it our
    `preview_url` instead would be lighter on the wire and softer on a big
    screen; the endpoint caps previews at 1024px.
  - **Only image, video and audio** (`VIEWABLE_ATTACHMENT_MIMES` in
    `js/script.js`). Those three handlers are the viewer's own. The ones other
    apps register — Text, richdocuments, files_pdfviewer — resolve the file
    themselves as the current user, so they cannot open somebody else's
    attachment; those are left to the plain link, which is a download for
    anybody who does not have the file in their Files.
  - The viewer is not on our page by default: `BeforeTemplateRenderedListener`
    dispatches `OCA\Viewer\Event\LoadViewer`, guarded by `class_exists()`
    because the app can be disabled, and **only on `/apps/quicknotes`** — the
    listener on the other side declares a dependency on the scripts of the
    Files app, so the page pays for `files`, `files_sharing` and
    `files_pdfviewer` too. `OCA\Viewer\Event\LoadViewer` is suppressed in
    `psalm.xml` as an `UndefinedClass` for the same reason.
- **The layout is a mosaic in CSS, keyed off `data-count`** on
  `.note-attachts` (1.0). It used to be a strip: javascript gave the
  container a height of `500 / n` pixels and each attachment a width of
  `100 / n` percent, so every file added made all of them thinner *and*
  shorter — five were slivers. Now the container holds a 3:2 ratio up to six
  and the shapes divide it: 1 whole, 2 columns, **3 = one tall plus two
  stacked**, 4 = 2×2, **5 = two wide over three**, 6 = 3×2. Past six the ratio
  is dropped, the cells go square and the container grows, because no shape
  fills a rectangle with seven. The card shows the first six and counts the
  rest in a `+N` badge (`data-more`, emitted by the `attachtsExtra` Handlebars
  helper so the attribute is *absent* rather than empty — `[data-more]` is what
  draws the badge); the editor shows them all, since that is where they are
  removed. `View._layoutAttachts()` only keeps those two attributes in step
  with the DOM after an add or a remove; there is no geometry in javascript any
  more, and none should come back.
- **A thumbnail fills its cell (`cover`), an icon does not.** Blowing the pdf
  icon up to cover a tile looks like a bug, so the payload carries
  `has_preview` (from `IPreview::isAvailable()` through
  `FileService::hasPreview()`) and the template adds `attach-is-icon`, which
  centres it at 40%. A single attachment keeps `contain`: there is nothing to
  line it up with, and cropping the only thing on the note loses more than it
  gains. The trade-off of `cover` is that a small image gets blown up — if that
  ever matters more than the mosaic reading as one block, that is the line to
  change.
- **A file with no preview answers with the icon of its type**, not a 404:
  `/core/preview` does that too (its `forceIcon` defaults to true), and it is
  what the grid showed for a pdf or a zip before the app served its own
  previews. `IMimeTypeDetector::mimeTypeIcon()` plus a redirect; a blank tile
  instead of an icon is a regression nobody asked for, and it is the kind that
  is only visible in a browser.
- The two endpoints deliberately carry **no `#[CORS]`**, unlike the rest of
  that controller: they are loaded by the browser as an `<img>` and a link, and
  the CORS middleware of the server insists on basic auth as soon as an Origin
  header shows up. The download also forces `Content-Disposition: attachment`
  *after* constructing the `FileDisplayResponse`, which writes its own `inline`
  over whatever it was handed — arbitrary files of another user, served from
  the origin of the instance, have no business rendering there.
- **Anybody who can edit the note can attach**, since the proxy removed the
  reason not to. The rules that make that safe, all in `syncAttachments()`:
  - It is **scoped to the caller in both directions**. The editor round-trips
    the whole attachment list of a note through the DOM, other people's
    included, so a save only deletes rows of the caller that are missing from
    the list, and only inserts entries that are the caller's. Everybody else's
    are left exactly as they are — a collaborator cannot drop the owner's
    attachment by saving, and the owner cannot drop theirs.
  - Which entries are the caller's is what `user_id` in the attachment payload
    is for (`data-attach-user` in the DOM). An entry **without** one is a file
    just picked and not saved yet, so it counts as theirs.
  - **You can only attach a file you can reach**: before inserting, the file is
    resolved in the caller's own storage. A client sending somebody else's file
    id as one of its own would otherwise leave a row the app could never serve
    on their behalf.
  - `is_mine` in the payload is what the editor keys the remove button off, so
    the "x" only shows on your own — the server enforces it either way.
- **An attachment of somebody who is out of the note stops being part of it.**
  Being unshared personally takes their rows with it
  (`ShareService::forgetRecipientState()`); losing access some other way — a
  group share taken back, or leaving the group — leaves the row behind, so
  `hydrate()` and the serving endpoint both ask
  `ShareService::canSee($attach->getUserId(), $note)` and skip it. Free for the
  owner, and one cached group lookup per distinct outside attacher for the
  rest. A file of theirs going on being served to the note's audience after
  they are out of it is the thing to avoid here.

## Sharing, permissions and concurrent edits

Rewritten in 0.9.1. Until then a share was a row with a `shared_user` on it and
nothing else: every recipient could only read, and what enforced it was not a
check but the shape of the queries — `NoteMapper::find()` filters by owner, and
everything went through it, so a shared note could not be written because it
could not be *found*. That is gone. Read this before touching anything that
reaches a note by id.

- **The model is the server's own.** `quicknotes_shares` carries a
  `share_type` (`NoteShare::TYPE_USER` / `TYPE_GROUP`, the values of
  `IShare::TYPE_*`), a `share_with` (uid or gid), a `permissions` bitmask
  (`NoteShare::PERMISSION_*`, the values of `OCP\Constants`), plus `uid_owner`,
  `uid_initiator` and `created_at`. The constants are redeclared on the entity
  rather than imported, so what goes into the database does not depend on the
  server, but **the numbers are the same on purpose** — do not renumber them.
  The old `shared_user` / `shared_group` columns are migrated and dropped
  (`Version00901Date2026081612…`, two files: the first adds and copies in
  `postSchemaChange()`, the second drops, because the copy runs before the
  schema change of the *next* migration and after its own).
- **A share cannot outlive the access of whoever made it.** `delete()` and
  `leave()` finish with `pruneOrphanReshares()`, which sweeps the note and drops
  every share whose `uid_initiator` can no longer see it, repeating until a pass
  changes nothing so a chain of reshares unwinds in full. The owner's own shares
  are skipped — their access *is* the note. It is a sweep and not a walk down
  the tree on purpose: the recipient of a group share is a set of people, any of
  whom may still reach the note another way, and the sweep states the invariant
  directly instead of trying to model the tree.
- **`ShareService` is the only thing that answers "may they".** Everything
  else asks it. `getPermissions($userId, $note)` returns the union of every
  share that reaches the user — theirs plus their groups' — and
  `PERMISSIONS_ALL` for the owner. Pass the already-fetched shares as the third
  argument inside a listing; without it, it queries per note.
- **`NoteService::get()` vs `getOwned()` is the security model.** `get()` finds
  a note the user may *see* (their own, or one shared with them); `getOwned()`
  only ever finds their own. Anything that belongs to the owner alone —
  colour, attachments, reminder, archive, trash, destroy — goes through
  `getOwned()`, and no share can reach it however generous. Editing goes
  through `get()` plus a `PERMISSION_UPDATE` check in `update()`. When adding a
  method, pick one deliberately; there is no third option.
- **What a share can grant is title, content and attachments.** The colour is
  still a property of the note and the owner's alone. Leaving a shared note is
  `ShareService::leave()` — and only for a share made with the user
  personally: dropping a group share would take the note from the whole group,
  so the endpoint answers 404. Which is why **archiving is personal too**
  (0.9.3): a note that reaches somebody through a group cannot be left, and
  archiving is the only way they have of getting it out of their own grid. The
  payload carries `canLeave` so the UI offers the leave icon only where it can
  work, and the archive icon everywhere.
- **What is personal lives per user.** Tags always did — `quicknotes_note_tags`
  carries the `user_id` of whoever tagged, so `hydrate()` reads *the viewer's*
  tags, not the owner's. The pin moved to `quicknotes_note_states` for the same
  reason (the owner pinning a shared note used to pin it for everyone), the
  reminder followed it in 0.9.2 and the archive state in 0.9.3. A row of that
  table exists once a user pinned, armed or archived something and is deleted
  again when none of that is left; `NoteStateMapper` keeps that invariant in
  one place (`write()`), so do not insert or delete there by hand.
  `quicknotes_notes.pinned`, `archived_at`, `reminder_at` and
  `reminder_notified_at` are all gone — do not bring back a second answer to
  any of those questions.
- **The trash is not personal, and that is the line.** `deleted_at` stays a
  column of the note: archiving says where a note sits in one grid, trashing
  says whether the note goes on existing, and only its owner decides that.
  `NoteMapper::updateDeletedAt()` is what is left of the old
  `updateArchiveState()`. `NoteService::destroy()` returns a bool so the
  controllers can answer 404 to somebody destroying a note that is not theirs,
  instead of a cheerful 200 after doing nothing. `emptyTrash()` is that same
  `destroy()` over `NoteMapper::findDeletedByUser()`, one note at a time, so
  there is a single cascade to keep right; it hangs off `DELETE /notes/trash`,
  declared among the explicit routes so it answers before `note#destroy` reads
  "trash" as a note id.
- **`hydrate()` takes the viewer and nothing on the entity may be cached.**
  Two users asking for the same row get two different payloads: their own tags,
  their own pin, their permissions, and `sharedWith` only if they are the owner
  or may reshare (a plain recipient has no business knowing who else has it).
- **`doc/openapi.yml` is the contract, and it is checked in.** It describes
  the `/api/v1` surface — the one clients talk to, CORS and no CSRF — and not
  the page routes the web interface uses. Change an endpoint or a payload and
  it changes with them, along with `Application::API_VERSION` and its
  docblock; a spec that lies is worse than none. It validates as OpenAPI 3.0
  (`openapi-spec-validator`), and every shape in it was read off a live
  instance, not off the entities.
- **Shares are their own endpoints, applied immediately.** `share#*` /
  `shareApi#*` over `/notes/{noteId}/shares`, `/shares/{shareId}`,
  `/notes/{noteId}/shares/self` and `/notes/{noteId}/sharees`; the logic is in
  `ShareActions`, and the two controllers are attributes over it. They used to
  ride inside the note payload and be written on save, which meant a share was
  lost by pressing Escape and an old browser tab could revoke, on save, a share
  made in a newer one. The `sharedWith` field of `PUT /notes/{id}` still works
  for the v1 API (`ShareService::syncUserShares()`, owner only, existing
  permissions preserved) but the app does not use it any more.
- **`sharees` goes through `OCP\Collaboration\Collaborators\ISearch`**, the
  same search the files dialog uses, so `shareapi_allow_share_dialog_user_…`
  and friends are honoured without this app knowing they exist. It replaced an
  OCS call that pulled *every user of the instance* on page load, whether the
  share dialog was ever opened or not. `shareapi_allow_group_sharing` and
  `shareapi_only_share_with_group_members` are checked in `ShareService` too,
  because a POST can be made without asking the search first.
- **Concurrency: `Note::getEtag()` and `If-Match`.** The etag is derived from
  the row (id, timestamp, title, content) and **not** from the JSON of the
  response, which also carries preview urls, display names and the viewer's
  permissions — two users would otherwise disagree on the tag of a note in the
  same state. A `PUT` with a stale `If-Match` is answered 412 with the current
  note in the body; `js/script.js` then asks whether to overwrite or reload.
  `If-Match: *` means no condition. Omitting the header keeps the old
  last-write-wins behaviour, which is what the v1 API clients get.
- **The grid refreshes on focus** (`View._refresh()`), because a shared note
  can change while the page sits open. It compares `Notes.signature()` before
  and after and only re-renders when something actually changed — re-rendering
  drops the active filter and rebuilds the isotope layout — and never while the
  editor is open.
- **Sharing notifies the recipient** (`Notifier::SUBJECT_SHARE`), and
  unsharing withdraws it with `markProcessed()`. User shares only: a group
  share would mean one notification per member. Note that `markProcessed()`
  matches on app + user + object, which is fine here only because the reminder
  notification of the same note belongs to the *owner*, a different user.
- Front end: `src/components/QnShareDialog.vue` (a proper list with avatars, a
  per-share permission menu and a search) plus `src/SharesService.js` for the
  calls. `QnDialogs.shares(noteId, shares, canShare, cb)` no longer returns a
  selection to save — the callback only gets the resulting list so the badges
  can be redrawn.

## Screenshots

The pictures `appinfo/info.xml` and the README point at are **not in this
repository**: they live in the site one, `matiasdelellis.github.io`, under
`img/quicknotes/`, and are served from `https://matiasdelellis.github.io/img/quicknotes/…`
— the same arrangement the facerecognition app uses. They are heavy, and every
new version would leave the old one behind in the history of the app forever.

They are of a board whose every card explains one feature of the app, built by
`tests/browser/seed_demo.py` and photographed by `tests/browser/screenshots.py`.
Take them again whenever the interface changes, and remember that the listing
breaks until the site repository is pushed: publish the site first, the app
version second.

## Conventions / things easy to miss

- `info.xml` pins `nextcloud min-version="34" max-version="34"` — update when changing Nextcloud compat.
- **Nextcloud 34 removed jQuery, jQuery UI, select2, Backbone and the `ocdialog` / `octemplate` jQuery plugins from the server.** The app ships its own jQuery in the `src/legacy.js` bundle (`window.$` / `window.jQuery`), which `templates/main.php` loads *first* — everything under `js/` expects `$` as a global, so keep it first. The tags/shares dialogs that used select2 + ocdialog now live in `src/dialogs.js` + `src/components/QnSelectDialog.vue` and are exposed as `window.QnDialogs` with the same API (`tags()`, `shares()`), so `js/script.js` calls them unchanged.
- Four more things the server used to do for the legacy scripts and no longer does, all handled in `src/legacy.js` / `js/script.js` — don't drop them:
  - the jQuery `ajaxSend` prefilter that adds the `requesttoken` / `OCS-APIREQUEST` headers (without it every request answers `412 Precondition failed`);
  - the `t` Handlebars helper used by every `js/templates/*.handlebars` (registered next to `tSW`/`tSB` in `js/script.js`);
  - error dialogs: `OC.dialogs.alert()` is broken in Nextcloud 34 (it asks for a button set that no longer exists and renders nothing), so use `QnDialogs.error()`, which shows a toast. `OC.dialogs.confirm()` and `OC.dialogs.filepicker()` still work.
  - the settings panel of the navigation: `data-apps-slide-toggle` on the button was picked up by a jQuery plugin of the core that slid the target open, and with it gone the button did *nothing at all* while `renderSettings()` kept filling a panel the server's own CSS holds at `display: none`. The handler in `js/script.js` sets the `opened` class on `#app-settings`, which `core/css/apps.css` still watches for. It does not close on an outside click, unlike the plugin — the panel holds a colour picker and checkboxes, and closing under the pointer was worse.
- In `QnSelectDialog.vue` the select opens its list **upwards** (`open-direction="above"`) and the modal box is forced to `overflow: visible` from the non‑scoped style block. Both are needed: below the input are the buttons, and the modal clips its content, which cut the list in half. Don't reserve space for the list with `min-height` either — the dialog then resizes on blur and swallows the click on `Done`.
- `QnReminderDialog.vue` solves the same clipping the other way round, with `append-to-body` on the `DatetimePicker`. That drops the popup out of the modal's stacking context, so it also needs `.mx-datepicker-main.mx-datepicker-popup { z-index: 100001 !important }` from the non‑scoped block: `@nextcloud/vue` pins the popup at `z-index: 2000` with **two classes**, so a single‑class selector loses on specificity no matter where it lands, and the calendar renders behind the dialog. The `!important` is for the order, not the specificity — webpack emits no CSS file, style‑loader injects at runtime and this file does not get to decide who goes last.
- **The icons, measured in a browser on 34.0.2** (`getComputedStyle` over every one of them, not a grep): every icon of the app resolves to an image. That includes the ones that go through `var(--original-icon-…)` — `icon-delete-note`, `icon-button-add`, `icon-filter-checkmark`, `.slim-tag` — because **those variables are defined on this version**, contrary to what this file claimed until 0.9.4. Do not repeat that claim without measuring: what was written here ("neither do the `--original-icon-*` variables behind them") does not hold on 34.0.2, and a doc that is wrong about what renders is worse than one that says nothing. The one icon that really was blank was `.attach-remove`, and for a different reason: the template carried the class `original-icon-delete-dark`, which is not a class at all but the *name of a variable*, so no rule ever matched it and the button was a grey circle with nothing in it. It has its own inline svg in `css/style.css` now.
- **Still prefer the app's own name and a direct `url(data:…)` for anything new**, even though the variables happen to work: the reason is not that they are missing, it is the two traps below — the server owns those identifiers and resolves them per theme, and a note keeps its light colour under every theme. `icon-qn-share`, `icon-qn-shared`, `icon-qn-reminder`, `.slim-share`, `.slim-share-group`, `.slim-reminder` and `.attach-remove` are the ones that follow that rule today.
- **A new icon needs a name the server does not use and a direct `url(data:…)`** — never a bare `icon-<something-nextcloud-has>` and never `var(--original-icon-…)`. Those identifiers belong to the server: where it ships its icon stylesheet it defines `body .icon-calendar` itself, which at specificity (0,1,1) beats a plain `.icon-calendar` in the app's CSS and resolves to whichever asset the current theme wants — a white one under a dark theme, on a sidebar or a note that is not dark. It fails *differently* depending on whether the instance serves that stylesheet, which makes it miserable to debug. Every icon of the app that renders correctly avoids both traps: `icon-pin`, `icon-pinned`, `icon-archived`, `icon-unarchive`, `icon-restore`, `icon-qn-reminder`, `icon-qn-share`, `icon-qn-shared`, `.slim-reminder` and `.slim-share`. The single exception, and why it is one: the navigation *does* sit on a background that follows the theme, so `#app-navigation .icon-qn-share` takes the `--background-invert-if-dark` filter in `css/icons.css` — scoped to that selector, never to the icon itself, which also renders on notes.
- **A new icon should also be a plain dark svg, with no `filter` and no media query**, like all of the above. Two things that seem like the right call and are not, for a single icon:
  - `@media (prefers-color-scheme: dark)`, which follows the **browser**, not the theme the user picked in Nextcloud. The two disagree constantly.
  - `filter: var(--background-invert-if-dark)`, which does follow the theme (`invert(100%)` under `[data-theme-dark]`, the deliberately invalid `no` otherwise) but only makes sense where the background follows the theme too. A **note keeps its light colour in every theme**, so the editor toolbar buttons, the `.slim-*` badges and `.icon-header-note` must stay dark unconditionally.
  - Dark mode for the icons is worth doing across the app in one pass — `icon-quicknotes` is the only one that attempts it today, through the media query, so it has the browser/theme mismatch built in.
- `OC.Files` is also gone in 34: the file picker only returns a path, so the file id is resolved server side through `AttachmentApi#info` (`GET /api/v1/attachments/info?path=…`).
- Off‑canvas navigation below 1024px: the server used to open it with snap.js (`snapjs-left` on the body), which 34 dropped. The app now renders its own `#app-navigation-toggle` in `templates/main.php`, toggles `qn-nav-open` on the body from `js/script.js`, and lifts `#app-navigation` above `#app-content` (which core gives `z-index: 1000`) in `css/not-vue.css`. Without that z‑index the navigation is visible but not clickable.
- **`css/medium.css` is a theme and must be loaded *after* `css/vendor/medium-editor.css`.** Both style the same selectors at the same specificity, so whichever comes last wins, and `templates/main.php` had them the wrong way round until 1.0: the base kept `padding: 15px` on every toolbar button, which left a 9x10 content box and squashed the svg icon of the wikilink button. The order is the fix; the `!important` on the z-index of the toolbar stays as insurance, because inverting it again makes the toolbar *invisible* (painted under the modal overlay) rather than merely ugly. Measured with `getComputedStyle`, not read off the file — the two sheets are minified and the winner is not obvious from either.
- Controller permissions use PHP attributes (`#[NoAdminRequired]`, `#[NoCSRFRequired]`, `#[CORS]`), not the old `@NoAdminRequired` docblocks.
- PHP namespace root: `OCA\QuickNotes\` (see `lib/AppInfo/Application.php:23`).
- Two parallel route sets: human pages (`note#*`, `share#*`, `page#index`, `settings#*`) and JSON API (`noteApi#*`, `AttachmentApi#upload`) under `/api/v1/...`. When adding endpoints, add both forms if the UI needs them — the legacy `js/script.js` calls the non‑API routes, while `src/NotesService.js` calls `/api/v1/notes`.
- CORS preflight route: `noteApi#preflighted_cors` at `OPTIONS /api/v1/{path}` — keep the `requirements: ['path' => '.+']` when modifying.
- Error rendering lives in `lib/Controller/NoteResponses.php` (404/403/412 for a note) and `lib/Controller/ShareActions.php`. There used to be a `lib/Controller/Errors.php` trait recommended here for the job; it was removed in 1.0 because it caught a `OCA\QuickNotes\Service\NotFoundException` that does not exist in this repo, so anybody following the advice got a fatal instead of a 404. Nothing used it.
- Migrations live in `lib/Migration/` and follow the `Version<appVersion>Date<timestamp>.php` convention (the latest is `Version00900Date20260731120000.php`). Add a new one when changing schema; do not edit historical migrations.
- L10n: strings go through `t('quicknotes', '…')` in javascript and `$l->t('…')` in php. `.tx/config` drives Transifex. `make l10n-deps` fetches `translationtool.phar`; then `l10n-update-pot` → `l10n-transifex-push` → (translators) → `l10n-transifex-pull` → `l10n-transifex-apply`. `translationfiles/` and `translationtool.phar` are gitignored; `l10n/*.js` and `l10n/*.json` are what ships and are committed. Three things about it are not obvious and have each cost strings:
  - **A string written inside a `.handlebars` file is invisible to the extractor.** It only reads `.php`, `.js`, `.ts`, `.py`, `.html` and `.vue`. That is what `templates/fake.php` is for: every `{{t "quicknotes" "…"}}` in a template must also be listed there, or nobody can translate it. Twelve of them were missing until 1.0 — the whole reminder, sorting and archive vocabulary — and were untranslated in all sixteen languages. Adding a string to a template means adding a line there in the same change.
  - **`.l10nignore` keeps the build output out.** Entries match as a prefix of the path. Without it xgettext digs through the minified vue runtime inside `js/quicknotes-*.js` and asks translators for things like `key,ref,slot,slot-scope,is` and the list of svg tag names; thirteen such entries were in the pot. The bundles add nothing anyway, since `src/` is read directly.
  - **`convert-po-files` rewrites `l10n/<lang>.{js,json}` from the `.po` alone.** Whatever is not in the file is gone, so `l10n-transifex-apply` after a partial pull truncates the translations. Always `tx pull -a` first. Languages with no `.po` at all are left alone.
- The `js/templates/*.handlebars` are precompiled into a single `js/templates.js` (not loaded at runtime from individual files) — editing a template without rebuilding is invisible.
- Vue 2 (not 3). `@nextcloud/vue` is on the `^5.x` line that still supports Vue 2; don't bump to `^8` without a migration.
- `js/script.js` and friends are the original non‑Vue app; the Vue dashboard lives alongside it. They are not mutually exclusive — both are shipped.

## CI

`.github/workflows/`:
- `lint.yml` — XML schema check for `appinfo/info.xml` + `composer run lint` matrix on PHP 8.2–8.5.
- `static-analysis.yml` — `composer run psalm` against `nextcloud/ocp:dev-stable34` on PHP 8.2.

Both trigger on `push` to `master` and on `pull_request`.
