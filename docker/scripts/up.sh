#!/usr/bin/env bash
# Brings up Nextcloud 34 + MariaDB and mounts this repo as custom_apps/quicknotes.
set -euo pipefail

cd "$(dirname "$0")/.."          # -> docker/
REPO_ROOT="$(cd .. && pwd)"

if [[ ! -f "$REPO_ROOT/.env" ]]; then
  echo "Missing $REPO_ROOT/.env. Copy docker/.env.example first: cp $REPO_ROOT/.env.example $REPO_ROOT/.env" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source "$REPO_ROOT/.env"
set +a

echo ">> Bringing containers up..."
docker compose --env-file "$REPO_ROOT/.env" up -d

echo
echo ">> Checking compiled assets..."
if [[ ! -f "$REPO_ROOT/js/templates.js" \
   || ! -d "$REPO_ROOT/js/vendor" \
   || ! -f "$REPO_ROOT/js/quicknotes-dashboard.js" ]]; then
  echo "   Missing assets (templates.js / vendor / Vue bundles). Building..."
  "$(dirname "$0")/build.sh"
else
  echo "   Assets OK."
fi

echo
echo ">> Waiting for Nextcloud to finish its first-time setup (may take 1-2 min)..."
for i in {1..60}; do
  if docker exec quicknotes-app curl -fsS http://localhost/status.php >/dev/null 2>&1; then
    echo "   Nextcloud is responding."
    break
  fi
  printf "."
  sleep 5
done
echo

echo ">> Enable Quick notes now? [y/N]"
read -r ans
if [[ "${ans:-N}" =~ ^[yYsS]$ ]]; then
  "$(dirname "$0")/enable-app.sh"
fi

echo
echo "Done. Open http://localhost:${NEXTCLOUD_HTTP_PORT:-8080}"
echo "User: ${NEXTCLOUD_ADMIN_USER:-admin}"
