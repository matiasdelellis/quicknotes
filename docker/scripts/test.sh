#!/usr/bin/env bash
# Run phpunit inside the dev container.
#
# Usage:
#   docker/scripts/test.sh                          # unit tests (default)
#   docker/scripts/test.sh unit                     # unit tests
#   docker/scripts/test.sh integration              # integration tests
#   docker/scripts/test.sh all                      # unit + integration
#   docker/scripts/test.sh --filter testArchive    # pass-through to phpunit
#   docker/scripts/test.sh tests/unit/controller/NoteApiControllerTest.php
#
# Requires the `dev` service to be running. See docker/README.md.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="${ENV_FILE:-$PROJECT_DIR/.env}"
COMPOSE_FILE="$PROJECT_DIR/docker/docker-compose.yml"
PHPUNIT="/var/www/html/lib/composer/bin/phpunit"
APP_DIR_IN_CONTAINER="/var/www/html/apps/quicknotes"

run_phpunit() {
    local config="$1"
    local label="$2"
    shift 2 || true
    echo "[test.sh] Running ${label} tests..."
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" exec -T dev \
        "$PHPUNIT" -c "$APP_DIR_IN_CONTAINER/$config" "$@"
}

CMD="${1:-unit}"
shift || true

case "$CMD" in
    -h|--help|help)
        sed -n '2,12p' "$0"
        exit 0
        ;;
    unit)
        run_phpunit phpunit.xml unit "$@"
        ;;
    integration)
        run_phpunit phpunit.integration.xml integration "$@"
        ;;
    all)
        run_phpunit phpunit.xml unit
        echo
        run_phpunit phpunit.integration.xml integration
        ;;
    *)
        # Treat the rest as phpunit passthrough against the unit config.
        run_phpunit phpunit.xml "unit (passthrough)" "$CMD" "$@"
        ;;
esac
