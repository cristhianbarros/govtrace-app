#!/usr/bin/env bash
# R-BCK-05 (it. 35): la restauración de prueba — make restore-drill.
# Una copia nueva (la del servicio backup, igual que la de cada hora), un
# PostgreSQL vacío y descartable (restore-pg), y el script de la
# restauración: restaura todo y verifica cada evidencia con su SHA-256.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"

$COMPOSE up -d --wait backup >/dev/null || { echo "FAIL  el servicio backup no arrancó"; exit 1; }
$COMPOSE exec -T backup backup.sh || { echo "FAIL  la copia falló"; exit 1; }

$COMPOSE --profile restore up -d --wait restore-pg >/dev/null || { echo "FAIL  el PostgreSQL de prueba no arrancó"; exit 1; }
$COMPOSE exec -T -e RESTORE_PGHOST=restore-pg backup restore-drill.sh
status=$?
$COMPOSE --profile restore rm -sf restore-pg >/dev/null 2>&1

exit $status
