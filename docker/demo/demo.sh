#!/usr/bin/env bash
# make demo (it. 38): todo lo que hace falta para una demostración en vivo, en
# un solo comando, desde un equipo con solo Docker y make:
#   1. la aplicación arriba (make setup la primera vez), con sus migraciones;
#   2. la red Stellar local y el contrato de sellado, si no están;
#   3. la organización de demostración: su gente, sus obras y sus reportes,
#      sellados de verdad en esa red, y unos publicados.
# Se puede repetir: cada corrida deja la demostración como nueva.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
EXEC="$COMPOSE exec -T -u workspace app"
step() { printf '\n▶ %s\n' "$1"; }
die() { echo "✘ $1" >&2; exit 1; }

step "1/3 La aplicación"
running=$($COMPOSE ps --status running --services 2>/dev/null | grep -cx app)
if [ ! -d vendor ] || [ ! -d public/build ] || [ "$running" -eq 0 ]; then
    make setup || die "make setup falló."
else
    $COMPOSE up -d --wait || die "los servicios no arrancaron."
    $EXEC php artisan migrate --force >/dev/null && $EXEC php artisan tenants:migrate --force >/dev/null || die "las migraciones fallaron."
fi

step "2/3 La red Stellar y el contrato de sellado"
make stellar-up || die "la red Stellar local no arrancó."
contract_before=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' .env)
$COMPOSE --profile stellar run --rm -T soroban ./scripts/ensure-local-contract.sh || die "no hay contrato de sellado en la red local."
if [ "$contract_before" != "$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' .env)" ]; then
    # El worker es un proceso largo: no ve el contrato nuevo hasta reiniciarse.
    $COMPOSE restart worker >/dev/null && $COMPOSE up -d --wait worker >/dev/null || die "el worker no volvió a arrancar."
fi

step "3/3 La organización de demostración"
$EXEC php artisan demo:prepare || die "la demostración no quedó lista."

cat <<'TEXT'

Para la demostración:
  make invites            los enlaces de los correos (invitar a un veedor, recuperar una contraseña)
  make demo               vuelve a dejar todo como nuevo
  make admin EMAIL=…      otro Super Administrador
TEXT
