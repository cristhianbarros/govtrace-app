#!/usr/bin/env bash
# R-BCK-01..04 (it. 35): una copia de respaldo en $BACKUP_DIR/<fecha UTC>/:
#   postgres/<base>.dump  cada base de PostgreSQL (la central y la de cada
#                         organización), en el formato de pg_restore;
#   evidencias/           los archivos de evidencia, espejo del bucket. Lo
#                         que no cambió desde la copia anterior se enlaza
#                         (cp -al): cada copia ocupa solo lo nuevo;
#   manifest.json         qué se copió, y SHA256SUMS de los volcados.
# Con BACKUP_OFFSITE_S3_URL, además, cada copia se replica fuera del sitio (it. 37a).
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

# 5. Fuera del sitio (it. 37a): una copia en el mismo disco no sobrevive a él.
#    Los volcados van por copia, a copias/<fecha>/, y se borran a los 30 días
#    por su fecha. Los archivos van a un espejo, evidencias/. En producción,
#    ese bucket tiene versionado, y una regla guarda 30 días las versiones
#    anteriores: así un borrado se puede deshacer (docs/restore.md).
if [ -n "${BACKUP_OFFSITE_S3_URL:-}" ]; then
    offsite() {
        AWS_ACCESS_KEY_ID=${BACKUP_OFFSITE_AWS_ACCESS_KEY_ID:-$AWS_ACCESS_KEY_ID} \
        AWS_SECRET_ACCESS_KEY=${BACKUP_OFFSITE_AWS_SECRET_ACCESS_KEY:-$AWS_SECRET_ACCESS_KEY} \
        aws ${BACKUP_OFFSITE_S3_ENDPOINT:+--endpoint-url "$BACKUP_OFFSITE_S3_ENDPOINT"} s3 "$@"
    }
    remote="${BACKUP_OFFSITE_S3_URL%/}"
    offsite sync "$root/$stamp/postgres" "$remote/copias/$stamp/postgres" --only-show-errors
    offsite cp "$root/$stamp/SHA256SUMS" "$remote/copias/$stamp/SHA256SUMS" --only-show-errors
    offsite cp "$root/$stamp/manifest.json" "$remote/copias/$stamp/manifest.json" --only-show-errors
    offsite sync "$root/$stamp/evidencias" "$remote/evidencias" --delete --only-show-errors

    cutoff=$(date -u -d "-${BACKUP_RETENTION_DAYS:-30} days" +%Y%m%dT%H%M%SZ)
    for old in $(offsite ls "$remote/copias/" | awk '$1 == "PRE" {sub("/", "", $2); print $2}'); do
        if [[ "$old" < "$cutoff" ]]; then
            offsite rm "$remote/copias/$old" --recursive --only-show-errors
        fi
    done

    # Para el healthcheck: la última réplica que terminó.
    date -u +%Y-%m-%dT%H:%M:%SZ > "$root/.offsite"
fi

echo "[backup] $stamp: ${#databases[@]} bases, $files archivos, $(du -sh "$root/$stamp" | cut -f1), $(( $(date +%s) - started )) s"
