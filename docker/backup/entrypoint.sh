#!/usr/bin/env bash
# Una copia al arrancar y después una cada hora en punto (R-BCK-01: se
# pierde a lo sumo 1 hora de datos). Sin cron: un bucle hereda el entorno
# del contenedor y escribe en su log.
set -uo pipefail

interval=${BACKUP_INTERVAL_SECONDS:-3600}
retries=${BACKUP_STARTUP_RETRIES:-10}
delay=${BACKUP_STARTUP_RETRY_SECONDS:-30}

# It. 45g: al arrancar, la base o el S3 pueden no estar listos todavía: después
# de reiniciar el equipo, Docker arranca todos los servicios a la vez, sin el
# orden de depends_on. La copia al arrancar se reintenta un rato (por defecto,
# 10 veces cada 30 s) antes de dejarla para la próxima hora.
for attempt in $(seq 1 "$retries"); do
    if backup.sh; then
        break
    fi
    if [ "$attempt" -lt "$retries" ]; then
        echo "[backup] la copia al arrancar falló (intento $attempt de $retries); se reintenta en $delay s" >&2
        sleep "$delay"
    else
        echo "[backup] la copia al arrancar falló $retries veces; se reintenta en la próxima hora" >&2
    fi
done

while true; do
    sleep $(( interval - $(date +%s) % interval ))
    backup.sh || echo "[backup] la copia falló; se reintenta en la próxima hora" >&2
done
