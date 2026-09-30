#!/usr/bin/env bash
# R-BLK-04 / D11 (it. 14): ninguna llave secreta de Stellar en el repositorio,
# ni en su historial, ni en los .env.*.example (desarrollo, testnet y producción). Las
# llaves se inyectan como variables de entorno al arrancar; si alguna llegara
# a un commit, habría que darla por comprometida y rotarla — quitarla del
# último commit no alcanza, sigue en el historial.
#
# Una llave secreta de Stellar: "S" + 55 caracteres base32 (56 en total).
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

pattern='\bS[A-Z2-7]{55}\b'
fail=0

in_tree=$(git ls-files -z | xargs -0 grep -IlE "$pattern" 2>/dev/null || true)
if [ -n "$in_tree" ]; then
  echo "FAIL  llaves secretas de Stellar en archivos versionados:"; echo "$in_tree" | sed 's/^/        /'; fail=1
else
  echo "PASS  ningún archivo versionado contiene una llave secreta de Stellar"
fi

in_history=$(git log --all -p --no-color 2>/dev/null | grep -E '^\+' | grep -oE "$pattern" | sort -u | wc -l)
if [ "$in_history" -gt 0 ]; then
  echo "FAIL  el historial de git contiene $in_history llave(s) secreta(s) de Stellar: rótalas, siguen expuestas"; fail=1
else
  echo "PASS  el historial de git no contiene llaves secretas de Stellar"
fi

for example in .env.example .env.testnet.example .env.production.example .env.staging.example; do
  if [ ! -f "$example" ]; then
    echo "FAIL  falta $example"; fail=1
  elif grep -qE '^STELLAR_(SEALER|SPONSOR|TREASURY)_SECRET=.+' "$example"; then
    echo "FAIL  $example trae un valor en STELLAR_*_SECRET: debe ir vacío"; fail=1
  else
    echo "PASS  $example deja vacías las llaves secretas"
  fi
done

exit $fail
