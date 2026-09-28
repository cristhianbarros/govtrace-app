#!/usr/bin/env sh
# Testnet de Stellar (it. 14): cuentas de PRUEBA fondeadas por el friendbot de
# testnet, el contrato desplegado, y .env.testnet para la prueba de humo
# (make smoke-testnet). Corre dentro del contenedor "soroban" (make testnet-setup).
#
# D11: las llaves se inyectan como variables de entorno. En desarrollo quedan
# en .cache/ (identidades de Stellar CLI) y en .env.testnet, los dos fuera de
# git; en CI, Jenkins escribe .env.testnet desde sus credenciales. Los XLM de
# testnet no valen nada, así que aquí la patrocinadora no necesita el saldo
# bajo de la hot wallet de producción.
#
# D12: el despliegue y la vigencia del contrato los paga la tesorería, no la
# patrocinadora; su llave no va a .env.testnet, porque Laravel no la usa.
#
# SDF reinicia testnet cada tanto: si el contrato desaparece, se corre de nuevo.
set -eu

. "$(dirname "$0")/testnet.sh"
. "$(dirname "$0")/stellar-common.sh"

touch "$ENV_FILE"
chmod 600 "$ENV_FILE"

sealer=$(ensure_funded_account govtrace-testnet-sealer)
sponsor=$(ensure_funded_account govtrace-testnet-sponsor)
treasury=$(ensure_funded_account govtrace-testnet-treasury)

stellar contract build --quiet

contract_id=$(stellar contract deploy \
  --wasm "$WASM" \
  --source-account govtrace-testnet-treasury \
  --rpc-url "$STELLAR_RPC_URL" \
  --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
  -- --sealer "$sealer")

extend_contract "$contract_id" govtrace-testnet-treasury

set_env STELLAR_RPC_URL "$STELLAR_RPC_URL"
set_env STELLAR_NETWORK_PASSPHRASE "\"$STELLAR_NETWORK_PASSPHRASE\""
set_env STELLAR_SEALING_CONTRACT_ID "$contract_id"
set_env STELLAR_SEALER_ADDRESS "$sealer"
set_env STELLAR_SPONSOR_ADDRESS "$sponsor"
set_env STELLAR_SEALER_SECRET "$(stellar keys show govtrace-testnet-sealer)"
set_env STELLAR_SPONSOR_SECRET "$(stellar keys show govtrace-testnet-sponsor)"

echo "Contrato de sellado desplegado en testnet: $contract_id"
echo "  https://stellar.expert/explorer/testnet/contract/$contract_id"
echo "  selladora:     $sealer"
echo "  patrocinadora: $sponsor"
echo "  tesorería:     $treasury (no va al .env: Laravel no la usa)"
