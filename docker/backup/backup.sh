#!/usr/bin/env bash
# R-BCK-01..04 (it. 35): una copia de respaldo en $BACKUP_DIR/<fecha UTC>/:
#   postgres/<base>.dump  cada base de PostgreSQL (la central y la de cada
#                         organización), en el formato de pg_restore;
#   evidencias/           los archivos de evidencia, espejo del bucket. Lo
#                         que no cambió desde la copia anterior se enlaza
#                         (cp -al): cada copia ocupa solo lo nuevo;
#   manifest.json         qué se copió, y SHA256SUMS de los volcados.
# Se guardan BACKUP_RETENTION_DAYS días (30). Una copia a medias nunca
# queda con el nombre de una completa.
set -euo pipefail

root=${BACKUP_DIR:-/backups}
bucket=${BACKUP_S3_BUCKET:?Falta BACKUP_S3_BUCKET}
endpoint=${BACKUP_S3_ENDPOINT:-}
exclude=" ${BACKUP_EXCLUDE_DATABASES:-} "
started=$(date +%s)
stamp=$(date -u +%Y%m%dT%H%M%SZ)
work="$root/.$stamp.partial"

mkdir -p "$root"
find "$root" -mindepth 1 -maxdepth 1 -name '.*.partial' -exec rm -rf {} +
mkdir -p "$work/postgres" "$work/evidencias"

# 1. Las bases.
databases=()
for db in $(psql -d postgres -Atc "select datname from pg_database where not datistemplate and datname <> 'postgres' order by 1"); do
    [[ "$exclude" == *" $db "* ]] && continue
    pg_dump --format=custom --file="$work/postgres/$db.dump" "$db"
    databases+=("$db")
done

# 2. Los archivos de evidencia.
previous=$(find "$root" -mindepth 1 -maxdepth 1 -type d -name '2*Z' | sort | tail -1)
if [ -n "$previous" ] && [ -d "$previous/evidencias" ]; then
    rm -rf "$work/evidencias"
    cp -al "$previous/evidencias" "$work/evidencias"
fi
aws ${endpoint:+--endpoint-url "$endpoint"} s3 sync "s3://$bucket" "$work/evidencias" --delete --only-show-errors

# 3. Qué se copió.
( cd "$work/postgres" && sha256sum -- *.dump > ../SHA256SUMS )
files=$(find "$work/evidencias" -type f | wc -l)
printf '{"taken_at": "%s", "databases": [%s], "evidence_files": %d, "seconds": %d}\n' \
    "$(date -u -d "@$started" +%Y-%m-%dT%H:%M:%SZ)" \
    "$(printf '"%s",' "${databases[@]}" | sed 's/,$//')" \
    "$files" "$(( $(date +%s) - started ))" > "$work/manifest.json"

mv "$work" "$root/$stamp"

# 4. Retención (R-BCK-04).
find "$root" -mindepth 1 -maxdepth 1 -type d -name '2*Z' -mmin +$(( ${BACKUP_RETENTION_DAYS:-30} * 1440 )) -exec rm -rf {} +

echo "[backup] $stamp: ${#databases[@]} bases, $files archivos, $(du -sh "$root/$stamp" | cut -f1), $(( $(date +%s) - started )) s"
