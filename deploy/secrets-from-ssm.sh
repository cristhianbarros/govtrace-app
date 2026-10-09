#!/usr/bin/env bash
# It. 42b — corre un comando con los secretos de SSM Parameter Store en su entorno:
#   deploy/secrets-from-ssm.sh /govtrace/staging -- deploy/deploy.sh
#
# Cada parámetro de la ruta (SecureString) llega como la variable del último
# tramo de su nombre: /govtrace/staging/APP_KEY es APP_KEY. Los valores no se
# escriben en ningún archivo ni en la salida (D11). En la máquina, las
# credenciales son las de su rol de IAM: ninguna llave.
set -euo pipefail

path=${1:?Uso: secrets-from-ssm.sh <ruta> -- <comando>}
shift
[ "${1:-}" = -- ] && shift
[ $# -gt 0 ] || { echo "secrets-from-ssm.sh: falta el comando que corre con los secretos." >&2; exit 2; }

parameters=$(aws ssm get-parameters-by-path --path "$path" --recursive --with-decryption \
    --query 'Parameters[].[Name,Value]' --output json)

# Python arma un `export NOMBRE='valor'` por parámetro, con las comillas a
# prueba de cualquier valor; si un nombre no sirve como variable, no arma nada.
exports=$(python3 -I -c '
import json, re, shlex, sys
lines = []
for name, value in json.load(sys.stdin) or []:
    variable = name.rsplit("/", 1)[-1]
    if not re.fullmatch(r"[A-Z][A-Z0-9_]*", variable):
        sys.exit(f"secrets-from-ssm.sh: el parámetro {name} no es un nombre de variable (A-Z, 0-9, _).")
    lines.append(f"export {variable}={shlex.quote(value)}")
print("\n".join(lines))
' <<< "$parameters")

eval "$exports"
unset parameters exports
exec "$@"
