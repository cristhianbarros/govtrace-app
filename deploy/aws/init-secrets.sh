#!/usr/bin/env bash
# It. 42b — los secretos de staging en SSM Parameter Store (SecureString), sin
# que nadie los vea ni los escriba (D11):
#   - APP_KEY, DB_PASSWORD y SEALING_PSEUDONYM_KEY se generan una vez y nunca se
#     reemplazan (cambiar SEALING_PSEUDONYM_KEY rompería los seudónimos);
#   - las llaves de testnet se copian de .env.testnet (fuera de git), si existe;
#   - los que pone la persona se cargan con make staging-secret: aquí solo se avisa si faltan.
set -euo pipefail
cd "$(dirname "$0")/../.."
SSM_PATH=${SSM_PATH:-/govtrace/staging}

exists() { aws ssm get-parameter --name "$SSM_PATH/$1" --query Parameter.Name --output text >/dev/null 2>&1; }
create_once() { # $1: el nombre; $2: el valor, que no se muestra
    if exists "$1"; then
        echo "  $1: ya estaba, no se toca"
    else
        aws ssm put-parameter --name "$SSM_PATH/$1" --type SecureString --value "$2" >/dev/null
        echo "  $1: guardado"
    fi
}
random_base64() { head -c "$1" /dev/urandom | base64 | tr -d '\n'; }

echo "Secretos en $SSM_PATH:"
create_once APP_KEY "base64:$(random_base64 32)"
create_once DB_PASSWORD "$(random_base64 48 | tr -dc 'A-Za-z0-9' | head -c 32)"
create_once SEALING_PSEUDONYM_KEY "$(random_base64 32)"

# Las llaves de testnet (D11 a): las de las cuentas que dice la plantilla.
if [ -f .env.testnet ]; then
    for name in STELLAR_SEALER_ADDRESS STELLAR_SPONSOR_ADDRESS STELLAR_SEALING_CONTRACT_ID; do
        local_value=$(sed -n "s/^$name=//p" .env.testnet | head -1)
        template_value=$(sed -n "s/^$name=//p" .env.staging.example | sed -E 's/[[:space:]]+#.*$//' | head -1)
        if [ "$local_value" != "$template_value" ]; then
            echo "  ⚠ $name de .env.testnet no es el de .env.staging.example: las llaves no serían de esas cuentas." >&2
        fi
    done
    for name in STELLAR_SEALER_SECRET STELLAR_SPONSOR_SECRET; do
        value=$(sed -n "s/^$name=//p" .env.testnet | head -1)
        if [ -n "$value" ]; then create_once "$name" "$value"; else echo "  $name: vacía en .env.testnet"; fi
    done
else
    echo "  Sin .env.testnet: STELLAR_SEALER_SECRET y STELLAR_SPONSOR_SECRET, con make staging-secret."
fi

missing=()
for name in DUCKDNS_TOKEN MAIL_PASSWORD STELLAR_SEALER_SECRET STELLAR_SPONSOR_SECRET; do
    exists "$name" || missing+=("$name")
done
if [ ${#missing[@]} -gt 0 ]; then
    echo "Faltan, y los pones tú (no se muestran):"
    for name in "${missing[@]}"; do echo "  make staging-secret NAME=$name"; done
fi
