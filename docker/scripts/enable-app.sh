#!/usr/bin/env bash
# Enables the Quick notes app inside a Nextcloud container.
#
# Usage: enable-app.sh [service]   # default: app
#
# `service` is the docker-compose service name (the container is
# `quicknotes-${service}`). It auto-detects the mount path — `app` ships
# the repo at custom_apps/quicknotes, `dev` at apps/quicknotes.
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

SERVICE="${1:-app}"
CONTAINER="quicknotes-${SERVICE}"
MOUNT_PATH=""

echo ">> Verifying that the app code is mounted in ${CONTAINER}..."
for candidate in apps/quicknotes custom_apps/quicknotes; do
    if docker exec "$CONTAINER" test -f "/var/www/html/${candidate}/appinfo/info.xml"; then
        MOUNT_PATH="$candidate"
        break
    fi
done

if [[ -z "$MOUNT_PATH" ]]; then
    echo "!! appinfo/info.xml not found in apps/quicknotes or custom_apps/quicknotes inside $CONTAINER" >&2
    echo "   Is the repo bind-mounted and is $CONTAINER running?" >&2
    exit 1
fi

echo "   Found at /var/www/html/${MOUNT_PATH}"
echo ">> Fixing file ownership..."
docker exec --user root "$CONTAINER" chown -R www-data:www-data "/var/www/html/${MOUNT_PATH}"

echo ">> Disabling (in case it was enabled) and re-enabling..."
docker exec --user www-data "$CONTAINER" php occ app:disable quicknotes || true
docker exec --user www-data "$CONTAINER" php occ app:enable quicknotes

echo ">> Status:"
docker exec --user www-data "$CONTAINER" php occ app:list | grep -A1 '^Enabled:' | grep quicknotes || true

echo "OK."
