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

## Reminders and the virtual calendar

A note can carry a reminder. Two columns on `quicknotes_notes` hold it, both
`datetime` and nullable, both left without an `addType()` in `lib/Db/Note.php`
so QBMapper hands back the raw string (same convention as `archived_at` /
`deleted_at`): `reminder_at`, and `reminder_notified_at` so the job does not
notify twice. **Everything server side is UTC `Y-m-d H:i:s`**
(`ReminderService::DATE_FORMAT`).

- Delivery is a plain Nextcloud notification, nothing to do with the calendar:
  `lib/BackgroundJob/NoteReminderJob.php` (every 5 min, registered in
  `info.xml`) asks `ReminderService::notifyDue()`, which notifies and stamps
  `reminder_notified_at`. `lib/Notification/Notifier.php` renders it.
  - The job is `TIME_SENSITIVE`, unlike `PurgeOldTrashJob`. A late reminder is a
    useless one.
  - **Accuracy is bounded by the cron**, which runs every 5 minutes at best and
    only while somebody browses on AJAX cron. Don't promise the minute.
  - `ReminderService::dismiss()` withdraws a pending notification through
    `markProcessed()`. It has to be called whenever the reminder stops being
    valid — rescheduled, cancelled, note trashed, note destroyed — or a
    notification for a date that no longer exists sits in the list forever.
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
  cancel. `js/script.js` therefore saves the note first and fires the reminder
  right after, only when it changed.
- Timezones: **every conversion lives in `src/dialogs.js`** and nowhere else.
  `QnDialogs.reminder()` takes and returns the UTC string, and
  `QnDialogs.formatReminder()` renders it for display — which is also why
  `js/script.js` registers a `reminderLabel` Handlebars helper that delegates
  to it. Keep it that way; the rest of the legacy code treats the value as an
  opaque string.
- `lib/Calendar/` publishes the notes that have a reminder as a **read‑only
  calendar**, off unless the user ticks it in the app settings
  (`SettingsService::CALENDAR_ENABLED_KEY`, default false — it shows up in the
  Calendar app and in every CalDAV client, so it is not turned on for people).
  Nothing is stored: every read derives the events from the table, which is the
  whole reason for going through a provider instead of writing real events.
  `OCP\Calendar` has no update or delete API (only `ICreateFromString`, which is
  create‑only and throws `UidConflict` on a repeated UID), so a written copy
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
- **Nextcloud 34 does not serve `dist/icons.css` to the app pages**, so none of the `icon-*` classes it used to define exist any more, and neither do the `--original-icon-*` variables behind them (checked across all 20 stylesheets the page links). The icons that render are exactly the ones the app ships itself as inline data URIs — `icon-pin`, `icon-pinned`, `icon-archived`, `icon-unarchive`, `icon-restore` in `css/style.css`, and `icon-quicknotes` / `icon-calendar` in `css/icons.css`. Anything else needs its own svg here; pointing at `var(--original-icon-…)` renders **blank**, which is easy to miss because it fails silently. Still unresolved, all pre‑existing: `icon-tag`, `icon-share`, `icon-shared`, `icon-search`, `icon-picture`, `icon-toggle-background`, plus `.slim-tag` / `.slim-share` and the `--original-icon-{add-white,delete-dark,checkmark-dark}` users.
- **A new icon needs a name the server does not use and a direct `url(data:…)`** — never a bare `icon-<something-nextcloud-has>` and never `var(--original-icon-…)`. Those identifiers belong to the server: where it ships its icon stylesheet it defines `body .icon-calendar` itself, which at specificity (0,1,1) beats a plain `.icon-calendar` in the app's CSS and resolves to whichever asset the current theme wants — a white one under a dark theme, on a sidebar or a note that is not dark. It fails *differently* depending on whether the instance serves that stylesheet, which makes it miserable to debug. Every icon of the app that renders correctly avoids both traps: `icon-pin`, `icon-pinned`, `icon-archived`, `icon-unarchive`, `icon-restore`, `icon-qn-reminder`, and `.slim-reminder`.
- **A new icon should also be a plain dark svg, with no `filter` and no media query**, like all of the above. Two things that seem like the right call and are not, for a single icon:
  - `@media (prefers-color-scheme: dark)`, which follows the **browser**, not the theme the user picked in Nextcloud. The two disagree constantly.
  - `filter: var(--background-invert-if-dark)`, which does follow the theme (`invert(100%)` under `[data-theme-dark]`, the deliberately invalid `no` otherwise) but only makes sense where the background follows the theme too. A **note keeps its light colour in every theme**, so the editor toolbar buttons, the `.slim-*` badges and `.icon-header-note` must stay dark unconditionally.
  - Dark mode for the icons is worth doing across the app in one pass — `icon-quicknotes` is the only one that attempts it today, through the media query, so it has the browser/theme mismatch built in.
- `OC.Files` is also gone in 34: the file picker only returns a path, so the file id is resolved server side through `AttachmentApi#info` (`GET /api/v1/attachments/info?path=…`).
- Off‑canvas navigation below 1024px: the server used to open it with snap.js (`snapjs-left` on the body), which 34 dropped. The app now renders its own `#app-navigation-toggle` in `templates/main.php`, toggles `qn-nav-open` on the body from `js/script.js`, and lifts `#app-navigation` above `#app-content` (which core gives `z-index: 1000`) in `css/not-vue.css`. Without that z‑index the navigation is visible but not clickable.
- Controller permissions use PHP attributes (`#[NoAdminRequired]`, `#[NoCSRFRequired]`, `#[CORS]`), not the old `@NoAdminRequired` docblocks.
- PHP namespace root: `OCA\QuickNotes\` (see `lib/AppInfo/Application.php:23`).
- Two parallel route sets: human pages (`note#*`, `share#*`, `page#index`, `settings#*`) and JSON API (`noteApi#*`, `AttachmentApi#upload`) under `/api/v1/...`. When adding endpoints, add both forms if the UI needs them — the legacy `js/script.js` calls the non‑API routes, while `src/NotesService.js` calls `/api/v1/notes`.
- CORS preflight route: `noteApi#preflighted_cors` at `OPTIONS /api/v1/{path}` — keep the `requirements: ['path' => '.+']` when modifying.
- `lib/Controller/Errors.php` exists — use it for consistent error rendering.
- Migrations live in `lib/Migration/` and follow the `Version<appVersion>Date<timestamp>.php` convention (the latest is `Version00900Date20260731120000.php`). Add a new one when changing schema; do not edit historical migrations.
- L10n: strings go through `t('quicknotes', '…')`. `.tx/config` drives Transifex. `make l10n-deps` fetches `translationtool.phar`; `make l10n-update-pot`, `make l10n-transifex-pull/push/apply` are the workflow. `translationfiles/` and `translationtool.phar` are gitignored.
- The `js/templates/*.handlebars` are precompiled into a single `js/templates.js` (not loaded at runtime from individual files) — editing a template without rebuilding is invisible.
- Vue 2 (not 3). `@nextcloud/vue` is on the `^5.x` line that still supports Vue 2; don't bump to `^8` without a migration.
- `js/script.js` and friends are the original non‑Vue app; the Vue dashboard lives alongside it. They are not mutually exclusive — both are shipped.

## CI

`.github/workflows/`:
- `lint.yml` — XML schema check for `appinfo/info.xml` + `composer run lint` matrix on PHP 8.2–8.5.
- `static-analysis.yml` — `composer run psalm` against `nextcloud/ocp:dev-stable34` on PHP 8.2.

Both trigger on `push` to `master` and on `pull_request`.
