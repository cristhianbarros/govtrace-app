#!/usr/bin/env sh
# Prueba de humo en la red local standalone (it. 12, R-TST-01): lo mismo que
# prueban los tests del contrato, pero con firmas y transacciones reales.
#   1. la cuenta selladora sella una raíz y el sello se lee de vuelta;
#   2. una cuenta externa no puede sellar;
#   3. la misma raíz no se puede sellar dos veces ("Hash ya registrado").
# Requiere make contract-deploy antes.
set -eu

. "$(dirname "$0")/stellar-common.sh"

STELLAR_SEALING_CONTRACT_ID=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=//p' "$ENV_FILE")
[ -n "$STELLAR_SEALING_CONTRACT_ID" ] || { echo "Falta STELLAR_SEALING_CONTRACT_ID en .env: corre make contract-deploy." >&2; exit 1; }

root=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
worksite=$(printf 'smoke:%s' "$root" | sha256sum | cut -c1-64)

echo "1. La selladora sella la raíz $root"
invoke_as govtrace-sealer seal --worksite "$worksite" --root "$root" >/dev/null
invoke_as govtrace-sealer get_seal --root "$root"

# Falla con un motivo concreto, o la prueba de humo falla: que "algo"
# salga mal no demuestra nada.
expect_rejection() {
  reason=$1
  shift
  if output=$("$@" 2>&1); then
    echo "FALLO: la red aceptó la invocación." >&2
    exit 1
  fi
  if ! printf '%s' "$output" | grep -qF "$reason"; then
    echo "FALLO: se rechazó por otro motivo, no por \"$reason\":" >&2
    printf '%s\n' "$output" >&2
    exit 1
  fi
}

echo "2. Una cuenta externa intenta sellar otra raíz"
ensure_funded_account govtrace-outsider >/dev/null
other_root=$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')
sealer=$(stellar keys address govtrace-sealer)
# La simulación de la red exige la firma de la selladora (require_auth) y
# la cuenta externa no la tiene.
expect_rejection "Missing signing key for account $sealer" \
  invoke_as govtrace-outsider seal --worksite "$worksite" --root "$other_root"
echo "   rechazada: la red exige la firma de la cuenta selladora"

echo "3. La selladora intenta sellar otra vez la misma raíz"
expect_rejection "Error(Contract, #1)" \
  invoke_as govtrace-sealer seal --worksite "$worksite" --root "$root"
echo "   rechazada: Error(Contract, #1) = Hash ya registrado"

echo "Prueba de humo en la red local: OK"
