#!/usr/bin/env bash
# Stops the containers and (optionally) wipes their volumes.
set -euo pipefail

cd "$(dirname "$0")/.."  # -> docker/

if [[ "${1:-}" == "--purge" || "${1:-}" == "-p" ]]; then
  echo ">> Stopping containers and removing volumes..."
  docker compose down -v
else
  echo ">> Stopping containers (volumes are preserved)..."
  docker compose down
fi
