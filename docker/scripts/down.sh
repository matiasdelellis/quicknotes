#!/usr/bin/env bash
# Baja los contenedores y opcionalmente borra volúmenes.
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

if [[ "${1:-}" == "--purge" || "${1:-}" == "-p" ]]; then
  echo ">> Bajando contenedores y borrando volúmenes..."
  docker compose down -v
else
  echo ">> Bajando contenedores (los volúmenes se conservan)..."
  docker compose down
fi
