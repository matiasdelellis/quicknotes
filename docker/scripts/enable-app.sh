#!/usr/bin/env bash
# Enables the Quick notes app inside the Nextcloud container.
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

echo ">> Verifying that the app code is mounted in the container..."
docker exec quicknotes-app test -f /var/www/html/custom_apps/quicknotes/appinfo/info.xml

echo ">> Fixing file ownership..."
docker exec --user root quicknotes-app chown -R www-data:www-data /var/www/html/custom_apps/quicknotes

echo ">> Disabling (in case it was enabled) and re-enabling..."
docker exec --user www-data quicknotes-app php occ app:disable quicknotes || true
docker exec --user www-data quicknotes-app php occ app:enable quicknotes

echo ">> Status:"
docker exec --user www-data quicknotes-app php occ app:list | grep -A1 '^Enabled:' | grep quicknotes || true

echo "OK."
