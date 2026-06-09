# AGENTS.md — Quick notes (Nextcloud app)

## What this is
A Nextcloud app (app id `quicknotes`, namespace `OCA\QuickNotes`) that provides rich‑text notes with colors, tags, attachments, sharing, dashboard widget, Talk integration, and a search provider.

- Entry points:
  - PHP bootstrap: `lib/AppInfo/Application.php` (registers search provider, capabilities, dashboard widget, `BeforeTemplateRendered` listener, navigation entry).
  - Routes: `appinfo/routes.php` (page `#index` at `/`, API under `/api/v1/...`).
  - DB layer: `lib/Db/*` (mappers for Note, NoteTag, NoteShare, Color, Tag, Attach).
  - Front‑end bundles: `webpack.config.js` builds **two** entries from `src/`:
    - `src/dashboard.js` → `js/quicknotes-dashboard.js` (Dashboard widget, Vue 2)
    - `src/talk.js` → `js/quicknotes-talk.js` (Talk message action, `OCA.Talk.registerMessageAction`)
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

`docker/` runs Nextcloud 33 + MariaDB 11.4 + a Node 20 `builder` service, and bind‑mounts the repo into `custom_apps/quicknotes`.

- Start: `./docker/scripts/up.sh` (expects `.env` at the repo root — copy from `docker/.env.example` first; the script reads it and passes it to `docker compose`).
- Build front‑end inside the stack: `./docker/scripts/build.sh` (uses the `builder` service; auto‑triggered by `up.sh` if `js/templates.js`, `js/vendor/`, or `js/quicknotes-dashboard.js` are missing).
- Re‑enable the app after PHP/template changes: `./docker/scripts/enable-app.sh`.
- Stop: `./docker/scripts/down.sh` (add `--purge` to wipe DB + Nextcloud data + npm cache).
- `.env` is gitignored and intentionally lives at the **repo root**, not in `docker/`, so `docker compose` can interpolate it. Do not move it without updating `up.sh`.
- After editing `.vue`, `.handlebars`, or any JS, hard‑reload the browser (Ctrl+Shift+R) — Nextcloud caches JS aggressively.

## Lint / static analysis / tests

- PHP lint: `composer run lint` (CI runs it on PHP 8.0, 8.1, 8.2, 8.3 — `php -l` over every `.php` outside `vendor/`). `composer install` first to pull the `psalm.phar`.
- Static analysis: `composer run psalm` (uses `psalm.phar`, config in `psalm.xml`, error level 4, scope `lib/`, suppresses a few `OC*` and Doctrine classes).
- Unit tests: `phpunit -c phpunit.xml` (boots `tests/bootstrap.php` which `require_once`s the Nextcloud server's `tests/bootstrap.php` from `../../../tests/bootstrap.php` — i.e. **the app must sit at `nextcloud/apps/quicknotes`** for unit tests to resolve).
- Integration tests: `phpunit -c phpunit.integration.xml` (same bootstrap constraint; only one test exists, `tests/integration/AppTest.php`, which boots the app and checks it is installed).
- XML schema lint: CI validates `appinfo/info.xml` against `https://apps.nextcloud.com/schema/apps/info.xsd`.
- No JavaScript lint/format scripts are wired up at the repo level; `package.json` only has `build`. `@nextcloud/eslint-config` and `@nextcloud/stylelint-config` are present in `devDependencies` but not invoked — don't assume `npm run lint` exists.

## Conventions / things easy to miss

- `info.xml` pins `nextcloud min-version="33" max-version="33"` — update when changing Nextcloud compat.
- PHP namespace root: `OCA\QuickNotes\` (see `lib/AppInfo/Application.php:23`).
- Two parallel route sets: human pages (`note#*`, `share#*`, `page#index`, `settings#*`) and JSON API (`noteApi#*`, `AttachmentApi#upload`) under `/api/v1/...`. When adding endpoints, add both forms if the UI needs them — the legacy `js/script.js` calls the non‑API routes, while `src/NotesService.js` calls `/api/v1/notes`.
- CORS preflight route: `noteApi#preflighted_cors` at `OPTIONS /api/v1/{path}` — keep the `requirements: ['path' => '.+']` when modifying.
- `lib/Controller/Errors.php` exists — use it for consistent error rendering.
- Migrations live in `lib/Migration/` and follow the `Version<appVersion>Date<timestamp>.php` convention (the latest is `Version00850Date20260608170000.php`). Add a new one when changing schema; do not edit historical migrations.
- L10n: strings go through `t('quicknotes', '…')`. `.tx/config` drives Transifex. `make l10n-deps` fetches `translationtool.phar`; `make l10n-update-pot`, `make l10n-transifex-pull/push/apply` are the workflow. `translationfiles/` and `translationtool.phar` are gitignored.
- The `js/templates/*.handlebars` are precompiled into a single `js/templates.js` (not loaded at runtime from individual files) — editing a template without rebuilding is invisible.
- Vue 2 (not 3). `@nextcloud/vue` is on the `^5.x` line that still supports Vue 2; don't bump to `^8` without a migration.
- `js/script.js` and friends are the original non‑Vue app; the Vue dashboard lives alongside it. They are not mutually exclusive — both are shipped.

## CI

`.github/workflows/`:
- `lint.yml` — XML schema check for `appinfo/info.xml` + `composer run lint` matrix on PHP 8.0–8.3.
- `static-analysis.yml` — `composer run psalm` against `nextcloud/ocp:dev-stable28` on PHP 8.2.

Both trigger on `push` to `master` and on `pull_request`.
