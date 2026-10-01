#!/usr/bin/env bash
# Iteración 45d — make storage-check: el S3 de desarrollo guarda en disco. Un
# archivo que la app escribe en el disco "evidencias" sigue ahí después de
# reiniciar el servicio storage y después de recrear su contenedor. Con
# LocalStack gratuito no pasaba: se perdían las fotos con cada reinicio.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
fail=0
pass() { echo "PASS  $1"; }
flunk() { echo "FAIL  $1"; fail=1; }
# Lo que la app lee del disco de evidencias, por Laravel (config/filesystems.php).
read_back() { $COMPOSE exec -T app php artisan tinker --execute="echo Storage::disk('evidencias')->get('$1') ?? 'nada';" 2>/dev/null | tail -1; }

$COMPOSE up -d --wait storage >/dev/null 2>&1 || { echo "FAIL  el servicio storage no arrancó"; exit 1; }

key="storage-check/$(date +%s).txt"
$COMPOSE exec -T app php artisan tinker --execute="Storage::disk('evidencias')->put('$key', 'evidencia');" >/dev/null 2>&1
if [ "$(read_back "$key")" = evidencia ]; then pass "la app escribe y lee en el S3 de desarrollo"; else flunk "la app no pudo escribir en el S3 de desarrollo"; fi

$COMPOSE restart storage >/dev/null 2>&1 && $COMPOSE up -d --wait storage >/dev/null 2>&1
if [ "$(read_back "$key")" = evidencia ]; then pass "el archivo sigue ahí tras reiniciar el servicio"; else flunk "el archivo se perdió al reiniciar el servicio"; fi

$COMPOSE up -d --wait --force-recreate storage >/dev/null 2>&1
if [ "$(read_back "$key")" = evidencia ]; then pass "el archivo sigue ahí tras recrear el contenedor"; else flunk "el archivo se perdió al recrear el contenedor"; fi

$COMPOSE exec -T app php artisan tinker --execute="Storage::disk('evidencias')->delete('$key');" >/dev/null 2>&1
exit $fail
