#!/usr/bin/env bash
# Habilita la app Quick notes dentro del contenedor de Nextcloud.
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

echo ">> Verificando que el código de la app esté montado en el contenedor..."
docker exec quicknotes-app test -f /var/www/html/custom_apps/quicknotes/appinfo/info.xml

echo ">> Ajustando permisos..."
docker exec --user root quicknotes-app chown -R www-data:www-data /var/www/html/custom_apps/quicknotes

echo ">> Deshabilitando por si estaba activa, luego habilitando..."
docker exec --user www-data quicknotes-app php occ app:disable quicknotes || true
docker exec --user www-data quicknotes-app php occ app:enable quicknotes

echo ">> Estado:"
docker exec --user www-data quicknotes-app php occ app:list | grep -A1 '^Enabled:' | grep quicknotes || true

echo "OK."
