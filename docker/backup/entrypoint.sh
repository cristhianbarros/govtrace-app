#!/usr/bin/env bash
# Una copia al arrancar y después una cada hora en punto (R-BCK-01: se
# pierde a lo sumo 1 hora de datos). Sin cron: un bucle hereda el entorno
# del contenedor y escribe en su log.
set -uo pipefail

interval=${BACKUP_INTERVAL_SECONDS:-3600}

backup.sh || echo "[backup] la copia al arrancar falló; se reintenta en la próxima hora" >&2

while true; do
    sleep $(( interval - $(date +%s) % interval ))
    backup.sh || echo "[backup] la copia falló; se reintenta en la próxima hora" >&2
done
