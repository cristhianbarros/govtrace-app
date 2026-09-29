#!/usr/bin/env bash
# US-044-MON: prueba la configuración real del monitoreo externo
# (ops/monitoring/gatus.yaml) con Gatus de verdad, en Docker: make monitoring-check.
#
# La regla de producción — chequeo cada minuto, alerta tras 6 fallas
# seguidas (más de 5 minutos caído) — se prueba con chequeos de 1 segundo:
#   1. una caída de 3 s (3 fallas) no genera ninguna alerta;
#   2. una caída de 9 s (más de 6 fallas) genera la alerta por webhook y
#      por correo, y al volver, la de recuperación.
# El "servidor de GovTrace" es un nginx que se detiene y se arranca; el
# webhook lo recibe un servidor mínimo en Python; el correo, Mailpit.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

PREFIX="govtrace-monitoring-check"
NETWORK="$PREFIX-net"
GATUS_IMAGE="twinproduction/gatus:v5.20.0"
MAILPIT_IMAGE="axllent/mailpit:v1.27"
failures=0

cleanup() {
  docker rm -f "$PREFIX-gatus" "$PREFIX-target" "$PREFIX-webhook" "$PREFIX-mail" >/dev/null 2>&1 || true
  docker network rm "$NETWORK" >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup

pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; failures=$((failures + 1)); }

webhooks() { docker logs "$PREFIX-webhook" 2>/dev/null | grep -c '^RECEIVED ' || true; }
emails() {
  docker run --rm --network "$NETWORK" curlimages/curl:8.11.1 -s "http://$PREFIX-mail:8025/api/v1/messages" \
    | sed -n 's/.*"total":\([0-9]*\).*/\1/p'
}

docker network create "$NETWORK" >/dev/null

docker run -d --name "$PREFIX-target" --network "$NETWORK" nginx:1.27-alpine >/dev/null

docker run -d --name "$PREFIX-webhook" --network "$NETWORK" python:3.13-alpine python -u -c '
from http.server import BaseHTTPRequestHandler, HTTPServer
class Receiver(BaseHTTPRequestHandler):
    def do_POST(self):
        body = self.rfile.read(int(self.headers.get("Content-Length", 0))).decode()
        print("RECEIVED " + body, flush=True)
        self.send_response(200); self.end_headers()
    def log_message(self, *args): pass
HTTPServer(("0.0.0.0", 8080), Receiver).serve_forever()
' >/dev/null

# Con STARTTLS y un certificado propio: Gatus (Go) no manda credenciales SMTP
# por una conexión sin cifrar, como debe ser. Solo aquí se acepta el
# certificado sin verificar (MONITOR_SMTP_INSECURE=true).
docker run -d --name "$PREFIX-mail" --network "$NETWORK" \
  -e MP_SMTP_AUTH_ACCEPT_ANY=1 \
  -e MP_SMTP_TLS_CERT="sans:$PREFIX-mail" -e MP_SMTP_TLS_KEY="sans:$PREFIX-mail" \
  "$MAILPIT_IMAGE" >/dev/null

docker run -d --name "$PREFIX-gatus" --network "$NETWORK" \
  -v "$PWD/ops/monitoring/gatus.yaml:/config/config.yaml:ro" \
  -e GOVTRACE_HEALTH_URL="http://$PREFIX-target/" \
  -e MONITOR_INTERVAL=1s \
  -e MONITOR_FAILURE_THRESHOLD=6 \
  -e MONITOR_WEBHOOK_URL="http://$PREFIX-webhook:8080/" \
  -e MONITOR_EMAIL_FROM=monitor@govtrace.test \
  -e MONITOR_EMAIL_TO=superadmin@govtrace.test \
  -e MONITOR_SMTP_HOST="$PREFIX-mail" \
  -e MONITOR_SMTP_PORT=1025 \
  -e MONITOR_SMTP_USERNAME=monitor \
  -e MONITOR_SMTP_PASSWORD=monitor \
  -e MONITOR_SMTP_INSECURE=true \
  "$GATUS_IMAGE" >/dev/null

echo "== Arranque: Gatus ve a GovTrace en pie"
sleep 6
# Arrancó con la configuración y ya hizo un chequeo exitoso.
if ! docker logs "$PREFIX-gatus" 2>&1 | grep -q 'Validated 1 endpoints' \
  || ! docker logs "$PREFIX-gatus" 2>&1 | grep -q 'endpoint=govtrace;.*success=true'; then
  docker logs "$PREFIX-gatus" 2>&1 | tail -20
  fail "Gatus no arrancó con la configuración"
  exit 1
fi
pass "Gatus arrancó con ops/monitoring/gatus.yaml y ve a GovTrace en pie"

echo "== 1. Una interrupción corta (3 fallas, menos que el umbral) no genera alerta"
docker stop -t 0 "$PREFIX-target" >/dev/null
sleep 3
docker start "$PREFIX-target" >/dev/null
sleep 5
[ "$(webhooks)" -eq 0 ] && pass "ningún webhook" || fail "llegó un webhook por una caída corta"
[ "$(emails)" = "0" ] && pass "ningún correo" || fail "llegó un correo por una caída corta"

echo "== 2. Una caída que supera el umbral alerta por webhook y por correo"
docker stop -t 0 "$PREFIX-target" >/dev/null
sleep 9
docker logs "$PREFIX-webhook" 2>/dev/null | grep -q 'RECEIVED .*CAÍDA' && pass "webhook de caída recibido" || fail "no llegó el webhook de caída"
[ "$(emails)" -ge 1 ] && pass "correo de caída recibido" || fail "no llegó el correo de caída"

echo "== 3. Al volver, avisa que se recuperó"
docker start "$PREFIX-target" >/dev/null
sleep 5
docker logs "$PREFIX-webhook" 2>/dev/null | grep -q 'RECEIVED .*RECUPERADA' && pass "webhook de recuperación recibido" || fail "no llegó el webhook de recuperación"

if [ "$failures" -gt 0 ]; then
  echo "--- Gatus"; docker logs "$PREFIX-gatus" 2>&1 | tail -30
  echo "--- webhook"; docker logs "$PREFIX-webhook" 2>&1 | tail -10
fi
exit "$failures"
