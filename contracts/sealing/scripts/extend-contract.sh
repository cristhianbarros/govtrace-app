#!/usr/bin/env sh
# D12: la tesorería extiende la vigencia de la instancia del contrato
# desplegado y de su código hasta el máximo de la red. Corre dentro del
# contenedor "soroban":
#   make contract-extend (red local) y make testnet-extend (testnet): con la
#     identidad de la tesorería y el contrato del .env que escribió el despliegue;
#   make network-extend NETWORK=testnet|mainnet (it. 37a): con la llave de la
#     tesorería y el contrato por el entorno (STELLAR_TREASURY_SECRET,
#     STELLAR_SEALING_CONTRACT_ID); en la red principal, además,
#     STELLAR_MAINNET_RPC_URL.
#
# Hay que correrlo antes de que venzan (unos 180 días en testnet y en la red
# principal, con la vigencia máxima de hoy). La it. 32 avisa 30 días antes.
set -eu

case "${1:-}" in
  local) treasury=govtrace-treasury ;;
  testnet)
    . "$(dirname "$0")/testnet.sh"
    treasury=govtrace-testnet-treasury
    ;;
  mainnet) ;;
  *)
    echo "Uso: $0 local|testnet|mainnet" >&2
    exit 2
    ;;
esac

. "$(dirname "$0")/stellar-common.sh"

if [ "$1" = mainnet ]; then
  use_mainnet
  assert_network "la red principal"
fi

# La llave de la tesorería, si viene por el entorno; si no, su identidad de desarrollo.
treasury=${STELLAR_TREASURY_SECRET:-${treasury:-}}
[ -n "$treasury" ] || { echo "Falta STELLAR_TREASURY_SECRET: la llave de la tesorería, desde el gestor de secretos" >&2; exit 2; }

contract_id=${STELLAR_SEALING_CONTRACT_ID:-$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$ENV_FILE" 2>/dev/null)}
if [ -z "$contract_id" ]; then
  echo "Falta el contrato: STELLAR_SEALING_CONTRACT_ID, o el .env que escribió el despliegue." >&2
  exit 1
fi

extend_contract "$contract_id" "$treasury"
