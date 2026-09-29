#!/usr/bin/env bash
# Iteración 35 — respaldos (R-BCK-01..05): make backup-check.
# Lo que la restauración de prueba no puede probar sobre sí misma:
#   - una copia trae cada base y cada archivo, y lo que no cambió se enlaza
#     con la copia anterior en vez de ocupar espacio otra vez;
#   - una copia de más de 30 días se borra;
#   - la restauración de prueba falla si un archivo no coincide con su
#     SHA-256, o si falta la base de una organización.
# Y al final, la restauración de prueba de verdad.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
in_backup() { $COMPOSE exec -T backup bash -c "$1"; }
fail=0
pass() { echo "PASS  $1"; }
flunk() { echo "FAIL  $1"; fail=1; }

$COMPOSE up -d --wait backup >/dev/null || { echo "FAIL  el servicio backup no arrancó"; exit 1; }

# Una copia "de hace 31 días", que la próxima debe borrar.
in_backup 'mkdir -p /backups/20200101T000000Z && touch -d "31 days ago" /backups/20200101T000000Z'

in_backup backup.sh >/dev/null || { echo "FAIL  la primera copia falló"; exit 1; }
in_backup backup.sh >/dev/null || { echo "FAIL  la segunda copia falló"; exit 1; }
read -r first second < <(in_backup 'ls -1d /backups/2*Z | tail -2 | xargs')

if in_backup "test ! -e /backups/20200101T000000Z"; then pass "la copia de hace 31 días se borró (R-BCK-04)"; else flunk "la copia de hace 31 días sigue ahí"; fi

central=$(sed -n 's/^DB_DATABASE=//p' .env.docker | head -1); central=${central:-govtrace}
if in_backup "test -s $second/postgres/$central.dump && test -s $second/manifest.json && cd $second/postgres && sha256sum --quiet -c ../SHA256SUMS"; then
    pass "la copia trae la base central, su manifiesto y el SHA-256 de cada volcado"
else
    flunk "a la copia le falta la base central, el manifiesto o un SHA-256"
fi

files=$(in_backup "find $second/evidencias -type f | wc -l")
shared=$(in_backup "cd $second/evidencias && find . -type f | while read -r f; do [ \"\$(stat -c %i \"\$f\")\" = \"\$(stat -c %i \"$first/evidencias/\$f\" 2>/dev/null)\" ] && echo x; done | wc -l")
if [ "$files" -gt 0 ] && [ "$shared" -eq "$files" ]; then
    pass "los $files archivos que no cambiaron se enlazan con la copia anterior: no ocupan espacio otra vez"
else
    flunk "de $files archivos, $shared se enlazan con la copia anterior"
fi

# La restauración de prueba, sobre copias alteradas: tiene que fallar.
$COMPOSE --profile restore up -d --wait restore-pg >/dev/null || { echo "FAIL  el PostgreSQL de prueba no arrancó"; exit 1; }
drill_on() { # $1: la copia; limpia la base de prueba antes
    in_backup "psql -h restore-pg -d postgres -Atc \"select datname from pg_database where not datistemplate and datname <> 'postgres'\" | xargs -r -n1 dropdb -h restore-pg"
    $COMPOSE exec -T -e RESTORE_PGHOST=restore-pg backup restore-drill.sh "$1"
}

altered=/backups/20300101T000000Z
# Una organización registrada, y un archivo suyo: son los que la restauración verifica.
tenant=$(in_backup "psql -Atc 'select id from tenants order by id limit 1'")
evidence=$(in_backup "psql -d tenant$tenant -Atc 'select storage_path from evidences order by id limit 1'" 2>/dev/null)

if [ -n "$evidence" ]; then
    in_backup "rm -rf $altered && cp -al $second $altered"
    # Se copia antes de escribir: el enlace duro alteraría también la copia buena.
    in_backup "cp '$altered/evidencias/$evidence' /tmp/v && echo alterado >> /tmp/v && mv /tmp/v '$altered/evidencias/$evidence'"
    out=$(drill_on "$altered" 2>&1)
    if grep -q "no coincide con su SHA-256" <<< "$out"; then pass "detecta un archivo que no coincide con su SHA-256"; else flunk "no detectó un archivo alterado"; fi
else
    flunk "no hay una organización con evidencias para probar la detección (make e2e crea una)"
fi

in_backup "rm -rf $altered && cp -al $second $altered && rm -f $altered/postgres/tenant$tenant.dump && cd $altered/postgres && sha256sum -- *.dump > ../SHA256SUMS"
out=$(drill_on "$altered" 2>&1)
if grep -q "no tiene su base en la copia" <<< "$out"; then pass "detecta una organización sin su base"; else flunk "no detectó la base que faltaba"; fi
in_backup "rm -rf $altered"
$COMPOSE --profile restore rm -sf restore-pg >/dev/null 2>&1

# Y la restauración de prueba de verdad.
bash tests/infra/restore-drill.sh || fail=1

exit $fail
