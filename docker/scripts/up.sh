#!/usr/bin/env bash
# Arranca Nextcloud 32 + MariaDB y monta este repo como custom_apps/quicknotes.
set -euo pipefail

cd "$(dirname "$0")/.."          # -> docker/
REPO_ROOT="$(cd .. && pwd)"

if [[ ! -f "$REPO_ROOT/.env" ]]; then
  echo "Falta $REPO_ROOT/.env. Copiá docker/.env.example: cp $REPO_ROOT/.env.example $REPO_ROOT/.env" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source "$REPO_ROOT/.env"
set +a

echo ">> Levantando contenedores..."
docker compose --env-file "$REPO_ROOT/.env" up -d

echo
echo ">> Verificando assets compilados de la app..."
if [[ ! -f "$REPO_ROOT/js/templates.js" \
   || ! -d "$REPO_ROOT/js/vendor" \
   || ! -f "$REPO_ROOT/js/quicknotes-dashboard.js" ]]; then
  echo "   Faltan assets (templates.js / vendor / bundles Vue). Compilando..."
  "$(dirname "$0")/build.sh"
else
  echo "   Assets OK."
fi

echo
echo ">> Esperando a que Nextcloud termine el primer arranque (puede tardar 1-2 min)..."
for i in {1..60}; do
  if docker exec quicknotes-app curl -fsS http://localhost/status.php >/dev/null 2>&1; then
    echo "   Nextcloud está respondiendo."
    break
  fi
  printf "."
  sleep 5
done
echo

echo ">> ¿Habilitar Quick notes ahora? [s/N]"
read -r ans
if [[ "${ans:-N}" =~ ^[sSyY]$ ]]; then
  "$(dirname "$0")/enable-app.sh"
fi

echo
echo "Listo. Abrí http://localhost:${NEXTCLOUD_HTTP_PORT:-8080}"
echo "Usuario: ${NEXTCLOUD_ADMIN_USER:-admin}"
