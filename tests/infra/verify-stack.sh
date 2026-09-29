#!/usr/bin/env bash
# Iteración 1 — Infraestructura del MVP (specs/PLAN.md). Run from the host: bash tests/infra/verify-stack.sh
# Done-when: `make ps` shows app, proxy, pgsql, scheduler, storage, worker and (it. 35) backup healthy/running,
#            and http://<LOCAL_IP>:<HTTP_PORT>/up (Host govtrace.localhost) answers 200.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
# El entorno gana sobre .env.docker, como en docker compose (Jenkins usa sus propios puertos).
LOCAL_IP=${LOCAL_IP:-$(sed -n 's/^LOCAL_IP=//p' .env.docker | head -1)}; LOCAL_IP=${LOCAL_IP:-127.0.0.1}
HTTP_PORT=${HTTP_PORT:-$(sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker | head -1)}; HTTP_PORT=${HTTP_PORT:-8080}
fail=0

for svc in app proxy pgsql scheduler storage worker backup; do
    state=$($COMPOSE ps --format '{{.Service}} {{.State}} {{.Health}}' 2>/dev/null | awk -v s="$svc" '$1==s {print $2, $3}')
    case "$state" in
        "running healthy"|"running ") echo "PASS  $svc ($state)";;
        "running starting")
            # Give it one more chance before failing a container that just booted.
            sleep 5
            state=$($COMPOSE ps --format '{{.Service}} {{.State}} {{.Health}}' 2>/dev/null | awk -v s="$svc" '$1==s {print $2, $3}')
            if [ "$state" = "running healthy" ] || [ "$state" = "running " ]; then
                echo "PASS  $svc ($state)"
            else
                echo "FAIL  $svc ($state)"; fail=1
            fi
            ;;
        "") echo "FAIL  $svc (no existe o no está corriendo)"; fail=1;;
        *) echo "FAIL  $svc ($state)"; fail=1;;
    esac
done

code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: govtrace.localhost' "http://${LOCAL_IP}:${HTTP_PORT}/up")
if [ "$code" = "200" ]; then echo "PASS  /up -> 200"; else echo "FAIL  /up -> $code"; fail=1; fi

exit $fail
