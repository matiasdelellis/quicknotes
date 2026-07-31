#!/usr/bin/env bash
# Builds the app assets (JS vendors, precompiled Handlebars templates, Vue bundles).
# Requires the `builder` service from docker-compose (node:20).
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/
REPO_ROOT="$(cd .. && pwd)"

# Match up.sh: the `.env` lives at the repo root so `docker compose`
# can interpolate it from any cwd. We pass it explicitly here so the
# builder service starts with the same env the rest of the stack uses.
COMPOSE_ENV_FILE="$REPO_ROOT/.env"
if [[ ! -f "$COMPOSE_ENV_FILE" ]]; then
  echo "Missing $COMPOSE_ENV_FILE. Copy docker/.env.example first:" >&2
  echo "  cp $REPO_ROOT/docker/.env.example $COMPOSE_ENV_FILE" >&2
  exit 1
fi

# `docker compose ps` exits 0 even when the service is not there, so the
# container id is what actually tells us whether it is up.
builder_running() {
  [[ -n "$(docker compose --env-file "$COMPOSE_ENV_FILE" ps -q --status running builder)" ]]
}

if ! builder_running; then
  echo ">> The 'builder' service is not running. Starting it..."
  docker compose --env-file "$COMPOSE_ENV_FILE" up -d builder
  echo ">> Waiting for 'builder' to be ready..."
  for i in {1..30}; do
    if builder_running; then
      break
    fi
    sleep 1
  done
  if ! builder_running; then
    echo "The 'builder' service did not come up. Check 'docker compose logs builder'." >&2
    exit 1
  fi
fi

echo ">> Running the build inside the builder container..."
docker compose --env-file "$COMPOSE_ENV_FILE" exec -T builder sh -c '
  set -e
  cd /app

  echo "[1/3] npm install (may take several minutes the first time)..."
  npm ci --no-audit --no-fund || npm install --no-audit --no-fund

  echo "[2/3] Copy vendors (depsmin) + precompile Handlebars templates..."
  mkdir -p vendor js/vendor css/vendor
  rm -rf js/vendor/*
  cp node_modules/handlebars/dist/handlebars.min.js          js/vendor/handlebars.js
  cp node_modules/isotope-layout/dist/isotope.pkgd.min.js    js/vendor/isotope.pkgd.js
  cp node_modules/medium-editor/dist/js/medium-editor.min.js js/vendor/medium-editor.js
  cp node_modules/medium-editor/dist/css/medium-editor.min.css css/vendor/medium-editor.css
  cp node_modules/medium-editor-autolist/dist/autolist.min.js js/vendor/autolist.js
  cp node_modules/lozad/dist/lozad.min.js                    js/vendor/lozad.js

  node_modules/.bin/handlebars js/templates -f js/templates.js

  echo "[3/3] Webpack build (Vue + main JS)..."
  npm run build

  echo ""
  echo "Build OK."
  ls -la js/templates.js js/quicknotes*.js* 2>/dev/null || true
'
