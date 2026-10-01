#!/usr/bin/env bash
# Iteración 1 — Infraestructura del MVP (specs/PLAN.md). Run from the host: bash tests/infra/verify-stack.sh
# Done-when: `make ps` shows app, proxy, pgsql, scheduler, storage, worker, (it. 35) backup and (it. 38b) mailpit healthy/running,
#            and http://<LOCAL_IP>:<HTTP_PORT>/up (Host govtrace.localhost) answers 200.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
# El entorno gana sobre .env.docker, como en docker compose (Jenkins usa sus propios puertos).
LOCAL_IP=${LOCAL_IP:-$(sed -n 's/^LOCAL_IP=//p' .env.docker | head -1)}; LOCAL_IP=${LOCAL_IP:-127.0.0.1}
HTTP_PORT=${HTTP_PORT:-$(sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker | head -1)}; HTTP_PORT=${HTTP_PORT:-8080}
fail=0

for svc in app proxy pgsql scheduler storage worker backup mailpit; do
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

# It. 38b: un correo de la app llega al buzón de desarrollo (Mailpit), por el proxy.
if grep -q '^MAIL_COPY_TO_MAILPIT=true' .env 2>/dev/null; then
    to="verify-stack.$(date +%s)@govtrace.test"
    $COMPOSE exec -T app php artisan tinker --execute="Illuminate\Support\Facades\Mail::raw('verify-stack', fn (\$m) => \$m->to('$to')->subject('verify-stack'));" >/dev/null 2>&1
    found=$(curl -s -H 'Host: mailpit.govtrace.localhost' "http://${LOCAL_IP}:${HTTP_PORT}/api/v1/search?query=to:${to}" | grep -o '"messages_count":[0-9]*' | cut -d: -f2)
    if [ "${found:-0}" -ge 1 ]; then echo "PASS  un correo llega a Mailpit"; else echo "FAIL  el correo no llegó a Mailpit (http://mailpit.govtrace.localhost:${HTTP_PORT})"; fail=1; fi
fi

# It. 38: make setup deja los territorios sembrados (DIVIPOLA): sin ellos no se configura ninguna organización.
db_user=$(sed -n 's/^DB_USERNAME=//p' .env.docker | head -1); db_name=$(sed -n 's/^DB_DATABASE=//p' .env.docker | head -1)
departments=$($COMPOSE exec -T pgsql psql -U "${db_user:-govtrace}" -d "${db_name:-govtrace}" -Atc 'select count(*) from departments' 2>/dev/null)
if [ "${departments:-0}" -gt 0 ] 2>/dev/null; then echo "PASS  DIVIPOLA sembrada ($departments departamentos)"; else echo "FAIL  la DIVIPOLA no está sembrada (departamentos: ${departments:-?})"; fail=1; fi

exit $fail
