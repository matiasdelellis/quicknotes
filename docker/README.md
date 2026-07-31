# Local development environment (Docker)

Spins up a Nextcloud 34 + MariaDB stack and mounts this repository into
`custom_apps/quicknotes`, so you can try the app without installing
Nextcloud manually.

Everything required for the environment lives in this directory:

```
docker/
├── docker-compose.yml
├── Dockerfile.dev
├── .env.example
├── scripts/
│   ├── up.sh
│   ├── down.sh
│   ├── build.sh
│   ├── enable-app.sh
│   └── test.sh
└── README.md   ← this file
```

## Requirements

- Docker 20.10+ and Docker Compose v2 (or `docker-compose` v1.29+)
- ~2 GB of free RAM
- Port: `8080` (configurable in `.env`)

## Quick start

```bash
# 1) Configure credentials (only the first time)
cp .env.example .env
# edit .env if you want to change ports/passwords

# 2) Bring the stack up and enable the app
./docker/scripts/up.sh
```

> Note: the `.env` file is kept at the repository root so that
> `docker compose` (which looks for `.env` relative to the compose file)
> can find it. If you prefer to keep everything inside `docker/`, move
> `.env` into that folder as well and drop the `--env-file` flag that
> `up.sh` passes.

The script:

1. Brings MariaDB and Nextcloud up.
2. **If compiled assets are missing** (`js/templates.js`, `js/vendor/`,
   `js/quicknotes-*.js`), it automatically runs `./docker/scripts/build.sh`
   using the `builder` service (Node 20) from the compose file.
3. Waits for Nextcloud to finish its first-time setup.
4. Asks whether you want to enable Quick notes (answer `y`).

Once everything is ready, open <http://localhost:8080> and sign in with the
credentials defined in `.env` (`NEXTCLOUD_ADMIN_USER` /
`NEXTCLOUD_ADMIN_PASSWORD`).

## Building assets manually

If you edit a `.vue` file in `src/components/`, a Handlebars template in
`js/templates/`, or any other JS file, you need to re-build:

```bash
./docker/scripts/build.sh
```

The build runs inside the `quicknotes-builder` container (Node 20) and
writes the artifacts to the host, where they are already mounted into
`custom_apps/quicknotes` inside Nextcloud. For the browser to pick up
the changes, hard-reload with Ctrl+Shift+R (Nextcloud caches JS
aggressively).

## File ownership of the repository

Both the `app` and the `builder` services write to the bind‑mounted repo as
their own user, so files in your checkout can end up owned by somebody else:

- the official Nextcloud image chowns `custom_apps/` to `www-data` (uid 33)
  when the `app` container starts, which can take the **whole repository**
  with it — including `.git` — and leave you unable to edit your own files;
- `build.sh` writes `js/templates.js`, `js/vendor/*` and `js/quicknotes-*.js`
  as the `node` user of the `builder` container.

Give them back with a throwaway container (no `sudo` on the host needed):

```bash
docker run --rm -v "$PWD:/repo:z" alpine chown -R "$(id -u):$(id -g)" /repo
```

Check whether it happened with `find . -path ./node_modules -prune -o -not -user "$(id -un)" -print`.

Adding `user: "1000:1000"` to the `app` and `builder` services in
`docker-compose.yml` avoids it, at the cost of pinning the uid.

## Installing other apps from the App Store

`occ app:install` fails with *Cannot write into "apps" directory*: in the
release image `apps/` is not writable and `custom_apps/` — the only path
marked writable in `apps_paths` — belongs to root. Fix the directory itself,
**without `-R`**, or you will chown the bind‑mounted repository inside it:

```bash
docker compose --env-file .env -f docker/docker-compose.yml exec -u root app \
    chown www-data:www-data /var/www/html/custom_apps

docker compose --env-file .env -f docker/docker-compose.yml exec -u www-data app \
    php occ app:install calendar
```

Handy for trying the virtual calendar of the app against the real Calendar
app. `notifications` already ships enabled in the release image, so the
reminder notifications work out of the box.

## Useful commands

```bash
# Re-enable the app after PHP/templates changes
./docker/scripts/enable-app.sh

# Re-build assets after editing .vue, .handlebars or .js files
./docker/scripts/build.sh

# Open a shell inside the Nextcloud container
docker exec -it -u www-data quicknotes-app bash

# Fire the reminder job now instead of waiting for the cron (which runs every
# five minutes at best). Get the id from the list first.
docker compose --env-file .env -f docker/docker-compose.yml exec -u www-data app \
    sh -c 'php occ background-job:list | grep NoteReminderJob'
docker compose --env-file .env -f docker/docker-compose.yml exec -u www-data app \
    php occ background-job:execute <id> --force-execute

# Tail the Nextcloud logs
docker logs -f quicknotes-app

# Tail the database logs
docker logs -f quicknotes-db

# Stop the containers (preserving data)
./docker/scripts/down.sh

# Stop the containers and wipe EVERYTHING (DB + Nextcloud files + npm cache)
./docker/scripts/down.sh --purge
```

## Running tests

The `dev` service builds a checkout of `nextcloud/server` (matching
`info.xml`'s max-version) with `composer install` already done, so
phpunit is available at `/var/www/html/lib/composer/bin/phpunit`
inside the container. The repo is bind-mounted at
`/var/www/html/apps/quicknotes` (the canonical dev path) so phpunit's
bootstrap can resolve `../../../tests/bootstrap.php`.

```bash
# 1) Build + start the dev service (first run takes a few minutes:
#    it clones nextcloud/server and runs composer install).
docker compose --env-file .env -f docker/docker-compose.yml up -d dev

# 2) Wait for Nextcloud to finish its first-time auto-install, then
# enable the app (the script auto-detects the mount path).
docker compose --env-file .env -f docker/docker-compose.yml exec -T dev \
    sh -c 'until curl -fsS http://localhost/status.php >/dev/null; do sleep 2; done'
./docker/scripts/enable-app.sh dev

# 3) Run the tests.
./docker/scripts/test.sh              # unit tests (default)
./docker/scripts/test.sh integration  # integration tests
./docker/scripts/test.sh all          # both
./docker/scripts/test.sh --filter testArchive  # passthrough to phpunit
```

Notes:

- The dev image is heavy and the build is slow. If you only want to
  browse the app, stay on the `app` service — `dev` is opt-in.
- The dev service auto-installs Nextcloud with sqlite (it does not
  share the `app` service's MariaDB), so a wiped `app` install does
  not affect the dev one and vice versa.
- `NEXTCLOUD_DEV_BRANCH` (default `stable34`) controls which branch of
  `nextcloud/server` is baked into the dev image. Rebuild the image
  (`docker compose build dev`) to pick up a new branch.
- `--purge` on `down.sh` wipes the `quicknotes_nc_dev` volume along
  with the rest.

## How it works

- `docker-compose.yml` defines four services: `db` (MariaDB 11.4),
  `app` (Nextcloud 34, release image), `builder` (Node 20, used to
  build assets) and `dev` (Nextcloud 34 + dev checkout, used to run
  tests).
- The repository root is bind-mounted into
  `/var/www/html/custom_apps/quicknotes` for `app` and
  `/var/www/html/apps/quicknotes` for `dev` (the compose volumes use
  `../` to step out of `docker/` and mount the whole project).
- Persistent data (database + `data/`, `config/`, unmounted `apps/`)
  lives in the `quicknotes_db`, `quicknotes_nc`, `quicknotes_npm_cache`
  and `quicknotes_nc_dev` volumes.

## Limitations

- `info.xml` declares the app for Nextcloud 34. To pin a different
  version, change the image tag in `docker-compose.yml` (e.g.
  `nextcloud:32`) and the `NEXTCLOUD_DEV_BRANCH` env var (e.g.
  `stable32`).
- SELinux in Enforcing mode requires the `:z` suffix on the bind mounts
  (already configured). If your distribution does not use SELinux, you
  can remove it.
- The dashboard Vue bundle is ~3 MB (webpack warning). That is
  acceptable for development; the shipped app reduces it with
  code-splitting.
