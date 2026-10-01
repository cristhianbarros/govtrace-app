#!/usr/bin/env bash
# Iteración 35 — respaldos (R-BCK-01..05): make backup-check.
# Lo que la restauración de prueba no puede probar sobre sí misma:
#   - una copia trae cada base y cada archivo, y lo que no cambió se enlaza
#     con la copia anterior en vez de ocupar espacio otra vez;
#   - una copia de más de 30 días se borra;
#   - la restauración de prueba falla si un archivo no coincide con su
#     SHA-256, o si falta la base de una organización.
#   - (it. 37a) cada copia se replica fuera del sitio, se borra allá a los 30
#     días y se restaura desde allá; y la restauración de prueba falla si
#     tarda más que el límite de recuperación (R-BCK-02).
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

# It. 45a: una base que desaparece entre la lista y su volcado (en desarrollo,
# las organizaciones que crean y borran los tests) se omite, y la copia termina.
# Un error de pg_dump con la base todavía ahí sigue siendo una falla. Un
# pg_dump de mentira, delante en el PATH, borra la base justo antes de volcarla.
real_pg_dump=$(in_backup 'command -v pg_dump' | tr -d '\r')
in_backup 'dropdb --if-exists backup_check_vanishing; dropdb --if-exists backup_check_broken; createdb backup_check_vanishing && mkdir -p /tmp/shim'
printf '%s\n' '#!/usr/bin/env bash' \
    'for arg; do' \
    '    [ "$arg" = backup_check_vanishing ] && dropdb backup_check_vanishing' \
    '    [ "$arg" = backup_check_broken ] && { echo "pg_dump: error de prueba" >&2; exit 1; }' \
    'done' \
    "exec $real_pg_dump \"\$@\"" | in_backup 'cat > /tmp/shim/pg_dump && chmod +x /tmp/shim/pg_dump'
if out=$(in_backup 'PATH=/tmp/shim:$PATH backup.sh' 2>&1) && grep -q "backup_check_vanishing: se borró durante la copia" <<< "$out" \
    && in_backup 'last=$(ls -1d /backups/2*Z | tail -1) && ! grep -q backup_check_vanishing $last/manifest.json && test ! -e $last/postgres/backup_check_vanishing.dump'; then
    pass "una base que se borra durante la copia se omite, y la copia termina"
else
    flunk "una base que se borra durante la copia la hace fallar"; echo "$out" | tail -3
fi
in_backup 'createdb backup_check_broken'
count=$(in_backup 'ls -1d /backups/2*Z | wc -l')
if ! in_backup 'PATH=/tmp/shim:$PATH backup.sh' >/dev/null 2>&1 && [ "$(in_backup 'ls -1d /backups/2*Z | wc -l')" = "$count" ]; then
    pass "un error de pg_dump con la base todavía ahí sigue fallando, sin dejar una copia a medias"
else
    flunk "un error de pg_dump con la base todavía ahí no hizo fallar la copia"
fi
in_backup 'dropdb --if-exists backup_check_broken; dropdb --if-exists backup_check_vanishing; rm -rf /tmp/shim'
read -r first second < <(in_backup 'ls -1d /backups/2*Z | tail -2 | xargs')

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

# It. 37a — fuera del sitio: cada copia se replica a otro bucket (aquí, uno
# del S3 de desarrollo), la réplica se borra allá a los 30 días, y desde allá se
# restaura. Y la restauración de prueba falla si tarda más de 4 h (R-BCK-02).
offsite=s3://evidencias-offsite
off() { in_backup "aws --endpoint-url \$BACKUP_S3_ENDPOINT s3 $1"; }
off "mb $offsite >/dev/null 2>&1; true"
off "cp $second/manifest.json $offsite/copias/20200101T000000Z/manifest.json --only-show-errors"
if $COMPOSE exec -T -e BACKUP_OFFSITE_S3_URL=$offsite -e BACKUP_OFFSITE_S3_ENDPOINT=http://storage:4566 backup backup.sh >/dev/null; then
    replica=$(in_backup "ls -1d /backups/2*Z | tail -1")
    stamp=$(basename "$replica")
    remote_dumps=$(off "ls $offsite/copias/$stamp/postgres/" | grep -c '\.dump$')
    local_dumps=$(in_backup "ls $replica/postgres/*.dump | wc -l")
    remote_files=$(off "ls $offsite/evidencias/ --recursive" | wc -l)
    local_files=$(in_backup "find $replica/evidencias -type f | wc -l")
    if [ "$remote_dumps" -eq "$local_dumps" ] && [ "$remote_files" -eq "$local_files" ] && off "ls $offsite/copias/$stamp/manifest.json" >/dev/null; then
        pass "la copia se replicó fuera del sitio: $remote_dumps volcados, su manifiesto y $remote_files archivos"
    else
        flunk "la réplica fuera del sitio está incompleta ($remote_dumps de $local_dumps volcados, $remote_files de $local_files archivos)"
    fi
    if off "ls $offsite/copias/" | grep -q 20200101T000000Z; then flunk "la réplica de hace años sigue fuera del sitio"; else pass "la réplica de más de 30 días se borró fuera del sitio (R-BCK-04)"; fi
    if in_backup "find /backups/.offsite -mmin -5 | grep -q ."; then pass "anota la última réplica, para que el servicio avise si se atrasa"; else flunk "no anotó la última réplica"; fi

    in_backup "rm -rf /tmp/offsite-check && BACKUP_OFFSITE_S3_URL=$offsite BACKUP_OFFSITE_S3_ENDPOINT=http://storage:4566 fetch-offsite.sh $stamp /tmp/offsite-check/$stamp >/dev/null"
    out=$(drill_on "/tmp/offsite-check/$stamp" 2>&1)
    if grep -q "evidencias con su archivo y el mismo SHA-256" <<< "$out" && ! grep -q "^FAIL" <<< "$out"; then
        pass "se restaura desde la réplica fuera del sitio"
    else
        flunk "no se pudo restaurar desde la réplica fuera del sitio"; echo "$out" | tail -3
    fi

    out=$(in_backup "psql -h restore-pg -d postgres -Atc \"select datname from pg_database where not datistemplate and datname <> 'postgres'\" | xargs -r -n1 dropdb -h restore-pg"; $COMPOSE exec -T -e RESTORE_PGHOST=restore-pg -e RESTORE_RTO_SECONDS=0 backup restore-drill.sh "/tmp/offsite-check/$stamp" 2>&1)
    if grep -q "FAIL  la recuperación tardó más de" <<< "$out"; then pass "la restauración de prueba falla si tarda más que el límite (R-BCK-02)"; else flunk "no falló al pasar del límite de recuperación"; fi
else
    flunk "la copia con réplica fuera del sitio falló"
fi
off "rb $offsite --force >/dev/null 2>&1; true"
in_backup "rm -rf /tmp/offsite-check /backups/.offsite"
$COMPOSE --profile restore rm -sf restore-pg >/dev/null 2>&1

# Y la restauración de prueba de verdad.
bash tests/infra/restore-drill.sh || fail=1

exit $fail
