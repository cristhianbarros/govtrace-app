#!/usr/bin/env bash
# R-BCK-05 (it. 35): la restauración de prueba, sin tocar lo que está en
# uso. Toma la copia más reciente (o la que se indique) y:
#   1. restaura cada base en un PostgreSQL vacío (RESTORE_PGHOST);
#   2. sube los archivos a un bucket de prueba y los cuenta;
#   3. comprueba que cada organización tiene su base, y que cada evidencia
#      de cada una tiene su archivo con el mismo SHA-256 — salvo las de una
#      organización cuyos archivos se borraron por retención (US-003b).
# Imprime cuántos datos se habrían perdido (desde la copia hasta ahora,
# R-BCK-01: menos de 1 h) y cuánto tardó (R-BCK-02: menos de 4 h, o
# RESTORE_RTO_SECONDS); falla si pasa de cualquiera de los dos.
set -euo pipefail

root=${BACKUP_DIR:-/backups}
snapshot=${1:-$(find "$root" -mindepth 1 -maxdepth 1 -type d -name '2*Z' | sort | tail -1)}
target=${RESTORE_PGHOST:?Falta RESTORE_PGHOST: el PostgreSQL vacío donde restaurar}
central=${PGDATABASE:?Falta PGDATABASE: la base central}
bucket="${BACKUP_S3_BUCKET:?Falta BACKUP_S3_BUCKET}-restore-drill"
endpoint=${BACKUP_S3_ENDPOINT:-}
s3() { aws ${endpoint:+--endpoint-url "$endpoint"} s3 "$@"; }
elapsed() { echo $(( $(date +%s) - $1 )); }

[ -n "$snapshot" ] && [ -f "$snapshot/manifest.json" ] || { echo "FAIL  no hay una copia completa en $root"; exit 1; }

incident=$(date +%s)
taken_at=$(sed -n 's/.*"taken_at": "\([^"]*\)".*/\1/p' "$snapshot/manifest.json")
lost=$(( incident - $(date -u -d "$taken_at" +%s) ))
echo "Copia: $(basename "$snapshot") (tomada $taken_at). Incidente simulado: $(date -u -d "@$incident" +%Y-%m-%dT%H:%M:%SZ)."

# 1. Las bases, verificadas contra su SHA-256 antes de restaurarlas.
started=$(date +%s)
( cd "$snapshot/postgres" && sha256sum --quiet -c ../SHA256SUMS )
restored=0
for dump in "$snapshot"/postgres/*.dump; do
    db=$(basename "$dump" .dump)
    createdb -h "$target" "$db"
    pg_restore -h "$target" -d "$db" --no-owner --exit-on-error "$dump"
    restored=$(( restored + 1 ))
done
echo "PASS  $restored bases restauradas en $(elapsed "$started") s"

# 2. Los archivos, en un bucket de prueba.
started=$(date +%s)
s3 mb "s3://$bucket" >/dev/null
s3 sync "$snapshot/evidencias" "s3://$bucket" --only-show-errors
uploaded=$(s3 ls "s3://$bucket" --recursive | wc -l)
s3 rb "s3://$bucket" --force >/dev/null
expected=$(find "$snapshot/evidencias" -type f | wc -l)
[ "$uploaded" -eq "$expected" ] || { echo "FAIL  $uploaded de $expected archivos subidos"; exit 1; }
echo "PASS  $uploaded archivos restaurados en un bucket de prueba en $(elapsed "$started") s"

# 3. Cada organización con su base, y cada evidencia con su archivo intacto.
#    Una consulta que falla es una restauración que falla: nada se da por bueno en silencio.
started=$(date +%s)
fail=0
organizations=0
verified=0
# to_jsonb: una copia anterior a la migración de la it. 33 no tiene la columna; tras
# restaurarla, make migrate la agrega (docs/restore.md).
tenants=$(psql -h "$target" -d "$central" -Atc "select id, coalesce(to_jsonb(t)->>'evidence_files_purged_at', '') <> '' from tenants t order by id") \
    || { echo "FAIL  la base central $central no se puede leer en la copia"; exit 1; }
while IFS='|' read -r tenant purged; do
    [ -n "$tenant" ] || continue
    organizations=$(( organizations + 1 ))
    db="tenant$tenant"
    if ! evidences=$(psql -h "$target" -d "$db" -Atc 'select sha256, storage_path from evidences order by id' 2>/dev/null); then
        echo "FAIL  la organización $tenant no tiene su base en la copia"; fail=1; continue
    fi
    [ "$purged" = "t" ] && continue # US-003b: sus archivos se borraron a los 5 años de la baja
    while IFS='|' read -r sha256 path; do
        [ -n "$sha256" ] || continue
        file="$snapshot/evidencias/$path"
        if [ ! -f "$file" ] || [ "$(sha256sum "$file" | cut -d' ' -f1)" != "$sha256" ]; then
            echo "FAIL  $db: la evidencia $path no está o no coincide con su SHA-256"; fail=1
        else
            verified=$(( verified + 1 ))
        fi
    done <<< "$evidences"
done <<< "$tenants"
[ "$fail" -eq 0 ] || exit 1
echo "PASS  $organizations organizaciones con su base; $verified evidencias con su archivo y el mismo SHA-256 ($(elapsed "$started") s)"

recovery=$(elapsed "$incident")
limit=${RESTORE_RTO_SECONDS:-14400}
echo "Datos perdidos: $(( lost / 60 )) min $(( lost % 60 )) s (desde la copia; R-BCK-01: menos de 1 h)"
echo "Recuperación: $recovery s (R-BCK-02: menos de 4 h)"
[ "$lost" -lt 3600 ] || { echo "FAIL  la copia tiene más de 1 hora"; exit 1; }
[ "$recovery" -lt "$limit" ] || { echo "FAIL  la recuperación tardó más de $limit s (R-BCK-02)"; exit 1; }
