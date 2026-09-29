#!/usr/bin/env sh
# It. 38 (make demo) — el contrato de sellado de la red local, solo si hace
# falta: si el que dice .env responde en esta red, se deja; si no (la red local
# empieza vacía cada vez que se recrea su contenedor, o es un .env nuevo), se
# despliega uno con deploy-local.sh, que deja su ID y las llaves en .env.
# Corre dentro del contenedor soroban.
set -eu

. "$(dirname "$0")/stellar-common.sh"

contract_id=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$ENV_FILE")
nobody_sealed=$(printf '0%.0s' $(seq 64))

if [ -n "$contract_id" ] && stellar keys address govtrace-treasury >/dev/null 2>&1 \
  && stellar contract invoke --id "$contract_id" --source-account govtrace-treasury \
    --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
    -- get_seal --root "$nobody_sealed" >/dev/null 2>&1; then
  echo "El contrato de sellado $contract_id responde en la red local: no se despliega otro."
else
  echo "El contrato de sellado no responde en la red local: se despliega uno nuevo."
  "$(dirname "$0")/deploy-local.sh"
fi
