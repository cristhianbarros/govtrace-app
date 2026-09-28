#!/usr/bin/env sh
# D12: la tesorería extiende la vigencia de la instancia del contrato
# desplegado y de su código hasta el máximo de la red. Corre dentro del
# contenedor "soroban": make contract-extend (red local) o make
# testnet-extend (testnet). Lee el ID del contrato del .env que escribió el
# despliegue.
#
# Hay que correrlo antes de que venzan (unos 180 días en testnet y mainnet
# con la vigencia máxima de hoy); vigilarlo es parte de la it. 32.
set -eu

case "${1:-}" in
  local) treasury=govtrace-treasury ;;
  testnet)
    . "$(dirname "$0")/testnet.sh"
    treasury=govtrace-testnet-treasury
    ;;
  *)
    echo "Uso: $0 local|testnet" >&2
    exit 2
    ;;
esac

. "$(dirname "$0")/stellar-common.sh"

contract_id=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$ENV_FILE")
if [ -z "$contract_id" ]; then
  echo "No hay STELLAR_SEALING_CONTRACT_ID en $ENV_FILE: despliega primero." >&2
  exit 1
fi

extend_contract "$contract_id" "$treasury"
