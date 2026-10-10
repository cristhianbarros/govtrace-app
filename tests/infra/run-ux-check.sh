#!/usr/bin/env bash
# make ux-check / make ux-baseline (it. 40a): el checkpoint base de la interfaz.
# Primero la organización de pruebas en la base de desarrollo (la misma de
# make e2e); después cada pantalla de cada rol en el contenedor oficial de
# Playwright, con la red del host. Con UPDATE_BASELINE=1 reescribe
# tests/ux/baseline.json en lugar de compararlo.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
HTTP_PORT=${HTTP_PORT:-$(sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker | head -1)}
IMAGE="mcr.microsoft.com/playwright:v$(sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' package.json)-noble"

$COMPOSE exec -T -u workspace app php tests/e2e/fixture.php || { echo "✘ No se pudo preparar la organización de pruebas (¿make up?)."; exit 1; }

docker run --rm --network host --ipc=host --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -e E2E_BASE_URL="http://veeduria-e2e.govtrace.localhost:${HTTP_PORT:-8080}" \
  -e UPDATE_BASELINE="${UPDATE_BASELINE:-}" \
  -v "$PWD:/work" -w /work "$IMAGE" \
  npx playwright test -c tests/ux/playwright.config.js "$@"
status=$?
echo "Capturas: storage/framework/testing/ux/shots/"
exit $status
