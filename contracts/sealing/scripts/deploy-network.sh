#!/usr/bin/env sh
# It. 37a — el contrato de sellado en testnet o en la red principal, por el
# mismo camino (R-CFG-01, D12, D13). La tesorería, ya fondeada (en la red
# principal, con XLM del presupuesto operativo central; allí no hay
# friendbot):
#   1. crea y fondea la cuenta selladora y la patrocinadora, si no existen;
#   2. despliega el contrato, con la selladora como única que puede sellar;
#   3. extiende la vigencia de la instancia y del código (D12).
#
# Las llaves (D11): la de la tesorería entra solo por el entorno y nunca se
# escribe en un archivo. De la selladora y la patrocinadora bastan sus
# direcciones: sus llaves se generan y se guardan en el gestor de secretos.
#
#   STELLAR_TREASURY_SECRET=S…  STELLAR_SEALER_ADDRESS=G…  STELLAR_SPONSOR_ADDRESS=G…
#   [SPONSOR_STARTING_XLM=100]  [OUTPUT_ENV=/workspace/.env.<red>.deploy]
#   red principal, además: STELLAR_MAINNET_RPC_URL=<endpoint privado del proveedor>  CONFIRM_MAINNET=yes
#
# make network-deploy NETWORK=testnet|mainnet, dentro del contenedor soroban.
# Escribe en OUTPUT_ENV solo valores públicos.
set -eu

network=${1:-}
case "$network" in
  testnet)
    . "$(dirname "$0")/testnet.sh"
    ;;
  mainnet)
    if [ "${CONFIRM_MAINNET:-}" != "yes" ]; then
      echo "La red principal gasta XLM reales de la tesorería (D13): confírmalo con CONFIRM_MAINNET=yes." >&2
      exit 2
    fi
    ;;
  *)
    echo "Uso: $0 testnet|mainnet" >&2
    exit 2
    ;;
esac

. "$(dirname "$0")/stellar-common.sh"
if [ "$network" = mainnet ]; then
  use_mainnet
fi

: "${STELLAR_TREASURY_SECRET:?Falta STELLAR_TREASURY_SECRET: la llave de la tesorería, desde el gestor de secretos}"
: "${STELLAR_SEALER_ADDRESS:?Falta STELLAR_SEALER_ADDRESS: la dirección de la cuenta selladora}"
: "${STELLAR_SPONSOR_ADDRESS:?Falta STELLAR_SPONSOR_ADDRESS: la dirección de la cuenta patrocinadora}"
ENV_FILE=${OUTPUT_ENV:-/workspace/.env.$network.deploy}

# Antes de firmar nada: que el RPC sirva de verdad esa red.
if [ "$network" = mainnet ]; then
  assert_network "la red principal"
else
  assert_network testnet
fi

# La tesorería firma y paga todo lo que sigue.
export STELLAR_ACCOUNT="$STELLAR_TREASURY_SECRET"

# Crea la cuenta con ese saldo inicial (en stroops) si todavía no existe.
ensure_account() {
  role=$1
  address=$2
  stroops=$3
  # Una cuenta que no existe también responde bien, con "entries" vacío.
  found=$(stellar ledger entry fetch account --account "$address" \
    --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" | jq '.entries | length')
  if [ "$found" -gt 0 ]; then
    echo "  $role $address ya existía"
  else
    stellar tx new create-account --destination "$address" --starting-balance "$stroops" \
      --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" >/dev/null
    echo "  $role $address creada con $(echo "$stroops" | awk '{ printf "%g", $1 / 10000000 }') XLM"
  fi
}

echo "Red: $STELLAR_NETWORK_PASSPHRASE"
# La selladora firma pero no paga: la reserva mínima y un margen.
ensure_account selladora "$STELLAR_SEALER_ADDRESS" 15000000
# La patrocinadora es la hot wallet (D11): el saldo de unos días de sellos.
ensure_account patrocinadora "$STELLAR_SPONSOR_ADDRESS" $(( ${SPONSOR_STARTING_XLM:-100} * 10000000 ))

stellar contract build --quiet

contract_id=$(stellar contract deploy \
  --wasm "$WASM" \
  --rpc-url "$STELLAR_RPC_URL" \
  --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
  -- --sealer "$STELLAR_SEALER_ADDRESS")

extend_contract "$contract_id" "$STELLAR_TREASURY_SECRET"

touch "$ENV_FILE"
chmod 600 "$ENV_FILE"
set_env STELLAR_NETWORK_PASSPHRASE "\"$STELLAR_NETWORK_PASSPHRASE\""
set_env STELLAR_SEALING_CONTRACT_ID "$contract_id"
set_env STELLAR_SEALER_ADDRESS "$STELLAR_SEALER_ADDRESS"
set_env STELLAR_SPONSOR_ADDRESS "$STELLAR_SPONSOR_ADDRESS"

echo "Contrato de sellado desplegado: $contract_id"
echo "  Valores públicos en $ENV_FILE. Las llaves de la selladora y la patrocinadora, desde el gestor de secretos."
echo "  Para el verificador independiente (R-MNT-01), agrega el contrato a tools/verify/contracts.json en \"$STELLAR_NETWORK_PASSPHRASE\"."
