#!/usr/bin/env bash
# It. 50 — make load-test: k6 (en Docker) contra BASE_URL. Por defecto, la
# organización de pruebas de make up; para staging, BASE_URL=https://veeduria-pruebas.govtrace.duckdns.org
# (docs/prueba-de-carga.md). Prepara la organización de pruebas solo en local.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

HTTP_PORT=${HTTP_PORT:-$(sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker | head -1)}
BASE_URL=${BASE_URL:-http://veeduria-e2e.govtrace.localhost:${HTTP_PORT:-8080}}

if [[ $BASE_URL == *.localhost* ]]; then
    docker compose --env-file .env.docker exec -T -u workspace app php tests/e2e/fixture.php >/dev/null || { echo "✘ No se pudo preparar la organización de pruebas (¿make up?)."; exit 1; }
fi

mkdir -p storage/framework/testing/load
docker run --rm --network host --user "$(id -u):$(id -g)" -v "$PWD:/work" -w /work \
  -e BASE_URL="$BASE_URL" -e PUBLIC_RATE -e VEEDORES -e DURATION -e VEEDOR_EMAIL -e VEEDOR_PASSWORD -e CONTRACT_ID -e LAT -e LNG \
  grafana/k6:1.3.0 run --summary-export=storage/framework/testing/load/resumen.json tests/load/carga.js "$@"
