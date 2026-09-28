#!/usr/bin/env sh
# R-SA-01 / R-BLK-03 (it. 12): el WASM compilado expone EXACTAMENTE estas
# funciones. Si alguien le agrega una para modificar o borrar sellos, o un
# upgrade, este chequeo falla antes de que el contrato llegue a desplegarse.
# Es la mitad del escenario "Nadie puede modificar ni borrar un sello
# registrado"; la otra mitad es su test en src/test.rs.
set -eu

WASM=target/wasm32v1-none/release/govtrace_sealing.wasm
expected='["__constructor","get_seal","seal"]'

actual=$(stellar contract info interface --wasm "$WASM" --output json 2>/dev/null \
  | jq -c '[.[] | .function_v0.name // empty] | sort')

if [ "$actual" != "$expected" ]; then
  echo "La interfaz del contrato cambió: se esperaba $expected y el WASM expone $actual." >&2
  exit 1
fi

echo "Interfaz del contrato: $actual"
