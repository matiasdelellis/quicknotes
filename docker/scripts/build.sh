#!/usr/bin/env bash
# Compila los assets de la app (vendors JS, templates Handlebars, bundles Vue).
# Requiere el servicio `builder` del docker-compose (node:20).
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

if ! docker compose ps builder --status running >/dev/null 2>&1; then
  echo ">> El servicio 'builder' no está corriendo. Levantando..."
  docker compose up -d builder
  echo ">> Esperando a que 'builder' esté listo..."
  for i in {1..30}; do
    if docker compose ps builder --status running >/dev/null 2>&1; then
      break
    fi
    sleep 1
  done
fi

echo ">> Ejecutando make build dentro del contenedor builder..."
docker compose exec -T builder sh -c '
  set -e
  cd /app

  echo "[1/3] npm install (puede tardar varios minutos la primera vez)..."
  npm ci --no-audit --no-fund || npm install --no-audit --no-fund

  echo "[2/3] copy deps (depsmin) + precompilar templates handlebars..."
  mkdir -p vendor js/vendor css/vendor
  rm -rf js/vendor/*
  cp node_modules/handlebars/dist/handlebars.min.js          js/vendor/handlebars.js
  cp node_modules/isotope-layout/dist/isotope.pkgd.min.js    js/vendor/isotope.pkgd.js
  cp node_modules/medium-editor/dist/js/medium-editor.min.js js/vendor/medium-editor.js
  cp node_modules/medium-editor/dist/css/medium-editor.min.css css/vendor/medium-editor.css
  cp node_modules/medium-editor-autolist/dist/autolist.min.js js/vendor/autolist.js
  cp node_modules/lozad/dist/lozad.min.js                    js/vendor/lozad.js

  node_modules/.bin/handlebars js/templates -f js/templates.js

  echo "[3/3] webpack build (vue + js principal)..."
  npm run build

  echo ""
  echo "Build OK."
  ls -la js/templates.js js/quicknotes*.js* 2>/dev/null || true
'
