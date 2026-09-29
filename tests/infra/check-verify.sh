#!/usr/bin/env bash
# US-026 (it. 23): el verificador independiente (tools/verify) contra la red
# de verdad, con una evidencia que GovTrace selló, publicó y dejó descargar:
# make verify-check. Requiere make stellar-up && make contract-deploy.
#
# 1. Pest (grupo stellar) sella un reporte en la red local, lo publica y deja
#    su archivo y su prueba, tal como se descargan, en storage/framework/testing/verify.
# 2. tools/verify los comprueba en un contenedor de Node que solo ve su propia
#    carpeta y esos dos archivos, de solo lectura, en una red de Docker aislada
#    donde no hay más que el nodo de Stellar: ni la app, ni la base, ni internet.
#    Con dos contratos, como tras desplegar una versión nueva: el verificador
#    pregunta por los dos y la encuentra en el anterior (R-MNT-01).
# 3. Una copia con un byte de más: NO COINCIDE.
# 4. Sin --contract: la red local no tiene contratos oficiales (contracts.json),
#    y el verificador no se cree el contrato que nombra la prueba: NO COINCIDE.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
OUT=storage/framework/testing/verify
ISOLATED=govtrace-verify-check
RPC=http://stellar:8000/rpc
# Una "versión 2" del contrato, que nunca selló esta raíz (una dirección válida, sin desplegar).
NEWER_CONTRACT=CALFTQY2X3YTEHFZORY7FQJUPXB2BXEGBCCHQVWTKB3WAVA65QHMTSFA
fail=0

contract=$(sed -n 's/^STELLAR_SEALING_CONTRACT_ID=\(C[A-Z2-7]\{55\}\).*/\1/p' .env | head -1)
[ -n "$contract" ] || { echo "Falta STELLAR_SEALING_CONTRACT_ID en .env: corra make contract-deploy."; exit 1; }
stellar=$($COMPOSE ps -q stellar)
[ -n "$stellar" ] || { echo "La red local no está arriba: corra make stellar-up."; exit 1; }

echo "== 1. GovTrace sella, publica y deja descargar una evidencia"
rm -rf "$OUT"
if ! $COMPOSE exec -T -u workspace app ./vendor/bin/pest --group=stellar --filter='independent verifier'; then
  echo "✘ No se pudo sellar y publicar la evidencia en la red local."; exit 1
fi
proof=$(ls "$OUT"/*.prueba.json 2>/dev/null | head -1)
file=${proof%.prueba.json}.jpg
[ -f "$file" ] || { echo "✘ Pest no dejó el archivo y su prueba en $OUT."; exit 1; }

docker network create --internal "$ISOLATED" >/dev/null || exit 1
cleanup() {
  docker network disconnect "$ISOLATED" "$stellar" >/dev/null 2>&1
  docker network rm "$ISOLATED" >/dev/null 2>&1
}
trap cleanup EXIT
docker network connect --alias stellar "$ISOLATED" "$stellar" || exit 1

# expect CODE WORDS FILE [ARGS...]: el verificador, aislado, y lo que tiene que decir.
expect() {
  local want=$1 words=$2 out code; shift 2
  out=$(docker run --rm --network "$ISOLATED" --read-only \
          -v "$PWD/tools/verify:/verify:ro" -v "$PWD/$OUT:/evidence:ro" \
          node:22-alpine node /verify/verify.mjs "$@" 2>&1)
  code=$?
  echo "$out" | sed 's/^/   /'
  if [ "$code" -eq "$want" ] && grep -q "$words" <<<"$out"; then
    echo "✔ $words (código $code)"
  else
    echo "✘ esperaba «$words» con código $want, y terminó con $code"; fail=1
  fi
}

echo "== 2. tools/verify, aislado: solo su carpeta y el nodo de Stellar; con una versión nueva del contrato"
expect 0 "AUTÉNTICO" "/evidence/$(basename "$file")" "/evidence/$(basename "$proof")" --rpc "$RPC" --contract "$NEWER_CONTRACT" --contract "$contract"

echo "== 3. Una copia con un byte de más"
cp "$file" "$OUT/alterada.jpg" && printf 'x' >> "$OUT/alterada.jpg"
expect 1 "NO COINCIDE" /evidence/alterada.jpg "/evidence/$(basename "$proof")" --rpc "$RPC" --contract "$contract"

echo "== 4. Sin --contract: el verificador no se cree el contrato que nombra la prueba"
expect 1 "No hay contratos de GovTrace conocidos" "/evidence/$(basename "$file")" "/evidence/$(basename "$proof")" --rpc "$RPC"

exit $fail
