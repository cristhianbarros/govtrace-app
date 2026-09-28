#!/usr/bin/env sh
# Funciones compartidas por los scripts de Stellar: la red local (it. 12) y
# testnet (it. 14). Se incluye con ". scripts/stellar-common.sh"; no se
# ejecuta sola. Cada script fija antes la red (RPC, passphrase, friendbot) y
# ENV_FILE, el .env donde escribe; si no, valen los de la red local.

STELLAR_RPC_URL="${STELLAR_RPC_URL:-http://stellar:8000/rpc}"
STELLAR_NETWORK_PASSPHRASE="${STELLAR_NETWORK_PASSPHRASE:-Standalone Network ; February 2017}"
STELLAR_FRIENDBOT_URL="${STELLAR_FRIENDBOT_URL:-http://stellar:8000/friendbot}"
WASM=target/wasm32v1-none/release/govtrace_sealing.wasm
ENV_FILE="${ENV_FILE:-/workspace/.env}"

# Crea la identidad si no existe y la fondea con friendbot si todavía no
# está en esta red (la red local se reinicia con su contenedor; testnet, cada
# tanto). Imprime su dirección pública.
ensure_funded_account() {
  name=$1
  stellar keys address "$name" >/dev/null 2>&1 || stellar keys generate "$name" >/dev/null 2>&1
  address=$(stellar keys address "$name")
  curl -fsS "$STELLAR_FRIENDBOT_URL?addr=$address" >/dev/null 2>&1 || true
  echo "$address"
}

# D12: la tesorería extiende la vigencia de la instancia del contrato y de
# su código hasta el máximo que permite la red, y paga ella esa renta. Así la
# cuenta patrocinadora — la hot wallet, con saldo bajo — solo paga cada sello.
# Se corre al desplegar y cada tanto (make contract-extend / testnet-extend),
# antes de que venzan: con la instancia archivada, sellar falla.
# (sh no tiene variables locales: los nombres extend_* evitan pisar las del script.)
extend_contract() {
  extend_id=$1
  extend_payer=$2
  max_entry_ttl=$(stellar network settings --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
    | jq '.updated_entry[] | .state_archival // empty | .max_entry_ttl')
  deployed_wasm=$(mktemp)
  stellar contract fetch --id "$extend_id" --out-file "$deployed_wasm" \
    --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE"

  instance_until=$(stellar contract extend --id "$extend_id" \
    --ledgers-to-extend $((max_entry_ttl - 1)) --ttl-ledger-only --source-account "$extend_payer" \
    --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE")
  code_until=$(stellar contract extend --wasm "$deployed_wasm" \
    --ledgers-to-extend $((max_entry_ttl - 1)) --ttl-ledger-only --source-account "$extend_payer" \
    --rpc-url "$STELLAR_RPC_URL" --network-passphrase "$STELLAR_NETWORK_PASSPHRASE")
  rm -f "$deployed_wasm"

  echo "Vigencia extendida por la tesorería: instancia hasta el ledger $instance_until, código hasta el $code_until."
}

# Escribe o reemplaza CLAVE=valor en el .env de Laravel.
set_env() {
  key=$1
  value=$2
  if grep -q "^$key=" "$ENV_FILE"; then
    sed -i "s|^$key=.*|$key=$value|" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

# stellar contract invoke contra la red local, con la cuenta $1.
invoke_as() {
  account=$1
  shift
  stellar contract invoke \
    --id "$STELLAR_SEALING_CONTRACT_ID" \
    --source-account "$account" \
    --rpc-url "$STELLAR_RPC_URL" \
    --network-passphrase "$STELLAR_NETWORK_PASSPHRASE" \
    -- "$@"
}
