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
