#!/usr/bin/env bash
# make e2e (R-TST-03): las pruebas de extremo a extremo en un Chromium de
# verdad (Playwright), contra la app de make up. Primero la organización de
# pruebas en la base de desarrollo; después el navegador, en el contenedor
# oficial de Playwright con la red del host: Chrome resuelve *.localhost a
# 127.0.0.1, donde escucha el proxy (LOCAL_IP:HTTP_PORT).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
# El puerto del proxy: el del entorno si lo hay (Jenkins usa el suyo, para no
# chocar con un entorno de desarrollo), si no el de .env.docker.
HTTP_PORT=${HTTP_PORT:-$(sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker | head -1)}
IMAGE="mcr.microsoft.com/playwright:v$(sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' package.json)-noble"

$COMPOSE exec -T -u workspace app php tests/e2e/fixture.php || { echo "✘ No se pudo preparar la organización de pruebas (¿make up?)."; exit 1; }

# make faces-bench (it. 48) usa la misma organización y el mismo navegador, con su configuración.
docker run --rm --network host --ipc=host --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -e E2E_BASE_URL="http://veeduria-e2e.govtrace.localhost:${HTTP_PORT:-8080}" -e CPU_RATE -e RUNS \
  -v "$PWD:/work" -w /work "$IMAGE" \
  npx playwright test -c "${E2E_CONFIG:-tests/e2e/playwright.config.js}" "$@"
