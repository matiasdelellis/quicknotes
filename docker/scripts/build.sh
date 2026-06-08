#!/usr/bin/env bash
# Builds the app assets (JS vendors, precompiled Handlebars templates, Vue bundles).
# Requires the `builder` service from docker-compose (node:20).
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

if ! docker compose ps builder --status running >/dev/null 2>&1; then
  echo ">> The 'builder' service is not running. Starting it..."
  docker compose up -d builder
  echo ">> Waiting for 'builder' to be ready..."
  for i in {1..30}; do
    if docker compose ps builder --status running >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
fi

echo ">> Running the build inside the builder container..."
docker compose exec -T builder sh -c '
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
