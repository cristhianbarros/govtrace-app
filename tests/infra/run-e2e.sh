#!/usr/bin/env bash
# make e2e (R-TST-03): las pruebas de extremo a extremo en un Chromium de
# verdad (Playwright), contra la app de make up. Primero la organización de
# pruebas en la base de desarrollo; después el navegador, en el contenedor
# oficial de Playwright con la red del host: Chrome resuelve *.localhost a
# 127.0.0.1, donde escucha el proxy (LOCAL_IP:HTTP_PORT).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
IMAGE="mcr.microsoft.com/playwright:v$(sed -n 's/.*"@playwright\/test": "\([0-9.]*\)".*/\1/p' package.json)-noble"

$COMPOSE exec -T app php tests/e2e/fixture.php || { echo "✘ No se pudo preparar la organización de pruebas (¿make up?)."; exit 1; }

docker run --rm --network host --ipc=host --user "$(id -u):$(id -g)" -e HOME=/tmp \
  -v "$PWD:/work" -w /work "$IMAGE" \
  npx playwright test -c tests/e2e/playwright.config.js "$@"
