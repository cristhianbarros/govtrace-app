#!/usr/bin/env bash
# It. 42b — make staging-secret NAME=<VARIABLE>: guarda un secreto de staging en
# SSM Parameter Store (SecureString). Lo pide sin mostrarlo, y no queda en el
# historial de la terminal ni en ningún archivo (D11). Si ya existía, lo reemplaza.
set -euo pipefail
SSM_PATH=${SSM_PATH:-/govtrace/staging}

name=${1:-}
if ! [[ $name =~ ^[A-Z][A-Z0-9_]*$ ]]; then
    echo "Uso: make staging-secret NAME=<VARIABLE>, por ejemplo NAME=MAIL_PASSWORD" >&2
    exit 2
fi
[ -t 0 ] || { echo "put-secret.sh: corre en tu terminal, para escribir el valor sin que se vea." >&2; exit 2; }

read -rsp "Valor de $name (no se muestra): " value
echo
[ -n "$value" ] || { echo "put-secret.sh: vacío, no se guardó nada." >&2; exit 1; }

aws ssm put-parameter --name "$SSM_PATH/$name" --type SecureString --value "$value" --overwrite >/dev/null
echo "$name guardado en $SSM_PATH. Toma efecto con el siguiente make staging-deploy."
