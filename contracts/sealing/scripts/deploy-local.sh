#!/usr/bin/env sh
# Despliega el contrato de sellado en la red local standalone (it. 12) y deja
# su ID en .env. Corre dentro del contenedor "soroban" (make contract-deploy).
#
# Las cuentas son de DESARROLLO: las crea Stellar CLI y las fondea friendbot.
# Sus llaves quedan en la configuración de la CLI, en .cache/ (fuera de git),
# y nunca en el repositorio ni en .env (R-BLK-04). La custodia en
# producción es la decisión D11 de specs/PLAN.md.
set -eu

. "$(dirname "$0")/stellar-local.sh"

sealer=$(ensure_funded_account govtrace-sealer)
sponsor=$(ensure_funded_account govtrace-sponsor)

stellar contract build --quiet

# Despliega la patrocinadora (tiene los XLM); el constructor fija la selladora.
contract_id=$(stellar contract deploy \
  --wasm "$WASM" \
  --source-account govtrace-sponsor \
  --rpc-url "$STELLAR_RPC_URL" \
  --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
  -- --sealer "$sealer")

# El contrato responde: una raíz que nadie selló no tiene sello.
stellar contract invoke \
  --id "$contract_id" \
  --source-account govtrace-sponsor \
  --rpc-url "$STELLAR_RPC_URL" \
  --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
  -- get_seal --root "$(printf '0%.0s' $(seq 64))" >/dev/null

set_env STELLAR_RPC_URL "$STELLAR_RPC_URL"
set_env STELLAR_NETWORK_PASSPHRASE "\"$STELLAR_NETWORK_PASSPHRASE\""
set_env STELLAR_SEALING_CONTRACT_ID "$contract_id"
set_env STELLAR_SEALER_ADDRESS "$sealer"
set_env STELLAR_SPONSOR_ADDRESS "$sponsor"

echo "Contrato de sellado desplegado en la red local: $contract_id"
echo "  selladora:     $sealer"
echo "  patrocinadora: $sponsor"
