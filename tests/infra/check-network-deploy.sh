#!/usr/bin/env bash
# Iteración 37a — make network-deploy-check: el despliegue de la red
# principal, de punta a punta en testnet, que es gratis.
#   1. Una tesorería de prueba con XLM de friendbot. La selladora y la
#      patrocinadora, sin fondear: las crea la tesorería, como en la red
#      principal, donde no hay friendbot.
#   2. scripts/deploy-network.sh testnet: cuentas, contrato y vigencia; y la
#      vigencia otra vez, con la llave por el entorno (make network-extend).
#   3. Lo que escribe trae solo valores públicos.
#   4. Un reporte llega a "Sellada" sobre ese contrato (la prueba de humo).
#   5. La red principal no se toca sin confirmarlo, ni con un RPC de otra red.
# Todo queda en archivos de prueba que se borran; no toca .env.testnet.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
SOROBAN="$COMPOSE --profile stellar run --rm -T soroban"
OUT=.env.testnet.deploy-check
SMOKE=.env.testnet.smoke-check
fail=0
pass() { echo "PASS  $1"; }
flunk() { echo "FAIL  $1"; fail=1; }
cleanup() {
    rm -f "$OUT" "$SMOKE"
    $SOROBAN sh -c 'for k in govtrace-check-treasury govtrace-check-sealer govtrace-check-sponsor; do stellar keys rm "$k" >/dev/null 2>&1; done; true' >/dev/null 2>&1
}
trap cleanup EXIT

# 1. Las cuentas de prueba.
read -r treasury_secret sealer sponsor < <($SOROBAN sh -c '
    for k in govtrace-check-treasury govtrace-check-sealer govtrace-check-sponsor; do stellar keys generate "$k" --overwrite >/dev/null 2>&1; done
    curl -fsS "https://friendbot.stellar.org?addr=$(stellar keys address govtrace-check-treasury)" >/dev/null
    echo "$(stellar keys show govtrace-check-treasury) $(stellar keys address govtrace-check-sealer) $(stellar keys address govtrace-check-sponsor)"
' 2>/dev/null | tail -1)
[ -n "${sponsor:-}" ] || { echo "FAIL  no se pudieron crear las cuentas de prueba en testnet"; exit 1; }

# 2. El despliegue.
deploy=$($SOROBAN env STELLAR_TREASURY_SECRET="$treasury_secret" STELLAR_SEALER_ADDRESS="$sealer" STELLAR_SPONSOR_ADDRESS="$sponsor" \
    SPONSOR_STARTING_XLM=20 OUTPUT_ENV="/workspace/$OUT" ./scripts/deploy-network.sh testnet 2>&1)
status=$?
echo "$deploy" | sed 's/^/      /'
[ $status -eq 0 ] && pass "desplegado en testnet por la tesorería" || flunk "el despliegue falló"
grep -q "selladora $sealer creada" <<< "$deploy" && grep -q "patrocinadora $sponsor creada" <<< "$deploy" \
    && pass "la tesorería creó la selladora y la patrocinadora" || flunk "la tesorería no creó las cuentas"
grep -q "Vigencia extendida por la tesorería" <<< "$deploy" && pass "la tesorería extendió la vigencia del contrato (D12)" || flunk "no se extendió la vigencia"

# 2b. La vigencia, de nuevo, con la llave y el contrato por el entorno (make network-extend).
contract_id=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$OUT" 2>/dev/null)
extend=$($SOROBAN env STELLAR_TREASURY_SECRET="$treasury_secret" STELLAR_SEALING_CONTRACT_ID="$contract_id" ./scripts/extend-contract.sh testnet 2>&1)
grep -q "Vigencia extendida por la tesorería" <<< "$extend" && ! grep -qF "$treasury_secret" <<< "$extend" \
    && pass "la tesorería vuelve a extenderla con su llave por el entorno" || flunk "no se pudo volver a extender la vigencia"

# 3. Solo valores públicos.
contract=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$OUT" 2>/dev/null)
if [ -n "$contract" ] && grep -q "^STELLAR_SEALER_ADDRESS=$sealer$" "$OUT" && grep -q "^STELLAR_SPONSOR_ADDRESS=$sponsor$" "$OUT"; then
    pass "escribe el contrato y las direcciones en $OUT"
else
    flunk "falta el contrato o una dirección en $OUT"
fi
if grep -qE '\bS[A-Z2-7]{55}\b|_SECRET=' "$OUT" 2>/dev/null || grep -qF "$treasury_secret" <<< "$deploy"; then
    flunk "una llave secreta quedó escrita o en la salida"
else
    pass "ninguna llave secreta quedó escrita ni en la salida (D11)"
fi

# 4. Un reporte hasta "Sellada" sobre ese contrato.
{
    cat "$OUT"
    echo 'STELLAR_RPC_URL=https://soroban-testnet.stellar.org'
    echo "STELLAR_SEALER_SECRET=$($SOROBAN stellar keys show govtrace-check-sealer 2>/dev/null | tail -1)"
    echo "STELLAR_SPONSOR_SECRET=$($SOROBAN stellar keys show govtrace-check-sponsor 2>/dev/null | tail -1)"
} > "$SMOKE"
chmod 600 "$SMOKE"
if $COMPOSE exec -T -u workspace app sh -c "set -a; . ./$SMOKE; set +a; ./vendor/bin/pest --group=testnet" 2>&1 | grep -q "1 passed"; then
    pass "un reporte llegó a \"Sellada\" sobre el contrato recién desplegado"
else
    flunk "la prueba de humo falló sobre el contrato recién desplegado"
fi

# 5. La red principal, con cuidado.
out=$($SOROBAN env STELLAR_TREASURY_SECRET="$treasury_secret" STELLAR_SEALER_ADDRESS="$sealer" STELLAR_SPONSOR_ADDRESS="$sponsor" \
    STELLAR_MAINNET_RPC_URL=https://soroban-testnet.stellar.org ./scripts/deploy-network.sh mainnet 2>&1)
grep -q "gasta XLM reales" <<< "$out" && pass "la red principal no se toca sin CONFIRM_MAINNET=yes" || flunk "desplegó en la red principal sin confirmarlo"
out=$($SOROBAN env STELLAR_TREASURY_SECRET="$treasury_secret" STELLAR_SEALER_ADDRESS="$sealer" STELLAR_SPONSOR_ADDRESS="$sponsor" \
    STELLAR_MAINNET_RPC_URL=https://soroban-testnet.stellar.org CONFIRM_MAINNET=yes ./scripts/deploy-network.sh mainnet 2>&1)
grep -q "no es de la red principal" <<< "$out" && pass "se niega a seguir si el RPC es de otra red" || flunk "siguió con un RPC que no es de la red principal"

exit $fail
