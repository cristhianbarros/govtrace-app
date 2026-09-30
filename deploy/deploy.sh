#!/usr/bin/env bash
# It. 42a — despliega GovTrace en un servidor con Docker (staging o producción)
# desde el código que ya está en la máquina: el `git pull` o el tag que toque.
# Se puede repetir.
#
# Lo público sale de la plantilla (.env.staging.example o la de producción),
# cargada antes en el entorno. Los secretos llegan por el entorno de este
# proceso (en AWS, deploy/secrets-from-ssm.sh): docker-compose.prod.yml nombra
# cada variable y Docker Compose la toma de aquí. Nada se escribe en un archivo.
#
#   1. las imágenes: la inmutable (etapa qa) y la de los respaldos;
#   2. PostgreSQL, las migraciones (la central y la de cada organización) y la
#      DIVIPOLA, antes de cambiar los contenedores: los que corren siguen
#      atendiendo mientras tanto;
#   3. todo arriba con las imágenes nuevas; la configuración y las rutas, en caché;
#   4. /up por HTTPS, desde esta misma máquina.
#
# Volver a la versión anterior: APP_IMAGE=govtrace-app:<la anterior> SKIP_BUILD=1 deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")/.."

: "${DOMAIN:?Falta DOMAIN: el dominio del certificado comodín}"
: "${APP_KEY:?Falta APP_KEY (gestor de secretos)}"
: "${DB_PASSWORD:?Falta DB_PASSWORD (gestor de secretos)}"
export APP_IMAGE=${APP_IMAGE:-govtrace-app:qa}
export BACKUP_IMAGE=${BACKUP_IMAGE:-govtrace-backup:qa}

# shellcheck disable=SC2086 # COMPOSE_EXTRA_FILES son varios -f a propósito
COMPOSE="docker compose -f docker-compose.prod.yml ${COMPOSE_EXTRA_FILES:-}"
# Como el usuario de la aplicación: un archivo que escribe root (un log del día)
# después no lo podría escribir Apache.
ARTISAN="$COMPOSE run --rm --no-deps -T -u workspace app php artisan"
step() { printf '\n▶ %s\n' "$1"; }

step "1/4 Las imágenes"
if [ "${SKIP_BUILD:-0}" != 1 ]; then
    docker build -q --network "${DOCKER_BUILD_NETWORK:-default}" -f docker/app/Dockerfile --target qa -t "$APP_IMAGE" . >/dev/null
    docker build -q --network "${DOCKER_BUILD_NETWORK:-default}" -f docker/backup/Dockerfile -t "$BACKUP_IMAGE" . >/dev/null
fi

step "2/4 La base de datos y las migraciones"
$COMPOSE up -d --wait pgsql
$ARTISAN migrate --force
$ARTISAN tenants:migrate --force
$ARTISAN db:seed --class=DivipolaSeeder --force

step "3/4 Todo arriba, con las imágenes nuevas"
$COMPOSE up -d --wait --remove-orphans
$COMPOSE exec -T -u workspace app php artisan optimize

step "4/4 /up por HTTPS"
# shellcheck disable=SC2086 # DEPLOY_CURL_OPTS: opciones extra (la CA de prueba de make staging-check)
curl -fsS --max-time 20 ${DEPLOY_CURL_OPTS:-} --resolve "$DOMAIN:${HTTPS_PORT:-443}:127.0.0.1" \
    "https://$DOMAIN:${HTTPS_PORT:-443}/up" >/dev/null
echo "GovTrace desplegado: https://$DOMAIN"
