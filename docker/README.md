# Local development environment (Docker)

Spins up a Nextcloud 33 + MariaDB stack and mounts this repository into
`custom_apps/quicknotes`, so you can try the app without installing
Nextcloud manually.

Everything required for the environment lives in this directory:

```
docker/
├── docker-compose.yml
├── .env.example
├── scripts/
│   ├── up.sh
│   ├── down.sh
│   ├── build.sh
│   └── enable-app.sh
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

## Useful commands

```bash
# Re-enable the app after PHP/templates changes
./docker/scripts/enable-app.sh

# Re-build assets after editing .vue, .handlebars or .js files
./docker/scripts/build.sh

# Open a shell inside the Nextcloud container
docker exec -it -u www-data quicknotes-app bash

# Tail the Nextcloud logs
docker logs -f quicknotes-app

# Tail the database logs
docker logs -f quicknotes-db

# Stop the containers (preserving data)
./docker/scripts/down.sh

# Stop the containers and wipe EVERYTHING (DB + Nextcloud files + npm cache)
./docker/scripts/down.sh --purge
```

## How it works

- `docker-compose.yml` defines three services: `db` (MariaDB 11.4),
  `app` (Nextcloud 33) and `builder` (Node 20, used to build assets).
- The repository root is bind-mounted into
  `/var/www/html/custom_apps/quicknotes` (the compose volumes use
  `../` to step out of `docker/` and mount the whole project).
- Persistent data (database + `data/`, `config/`, unmounted `apps/`)
  lives in the `quicknotes_db`, `quicknotes_nc` and `quicknotes_npm_cache`
  volumes.

## Limitations

- `info.xml` declares the app for Nextcloud 32–33. To pin a different
  version, change the image tag in `docker-compose.yml` (e.g.
  `nextcloud:32`).
- SELinux in Enforcing mode requires the `:z` suffix on the bind mounts
  (already configured). If your distribution does not use SELinux, you
  can remove it.
- The dashboard Vue bundle is ~3 MB (webpack warning). That is
  acceptable for development; the shipped app reduces it with
  code-splitting.
