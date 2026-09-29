#!/usr/bin/env bash
# It. 37a: trae una copia desde fuera del sitio, para restaurarla o para la
# restauración de prueba: sus volcados, su manifiesto y el espejo de los
# archivos de evidencia. Deja una copia igual a las de /backups en $2.
#   fetch-offsite.sh <fecha UTC de la copia> [destino]     (por defecto /tmp/offsite/<fecha>)
# El espejo de archivos es el de ahora, no el de esa hora: los archivos de
# evidencia no cambian nunca, así que trae los de la copia y, a lo sumo, más.
set -euo pipefail

stamp=${1:?Uso: fetch-offsite.sh <fecha UTC de la copia> [destino]}
dest=${2:-/tmp/offsite/$stamp}
remote="${BACKUP_OFFSITE_S3_URL:?Falta BACKUP_OFFSITE_S3_URL}"
remote="${remote%/}"

offsite() {
    AWS_ACCESS_KEY_ID=${BACKUP_OFFSITE_AWS_ACCESS_KEY_ID:-$AWS_ACCESS_KEY_ID} \
    AWS_SECRET_ACCESS_KEY=${BACKUP_OFFSITE_AWS_SECRET_ACCESS_KEY:-$AWS_SECRET_ACCESS_KEY} \
    aws ${BACKUP_OFFSITE_S3_ENDPOINT:+--endpoint-url "$BACKUP_OFFSITE_S3_ENDPOINT"} s3 "$@"
}

mkdir -p "$dest"
offsite sync "$remote/copias/$stamp" "$dest" --only-show-errors
offsite sync "$remote/evidencias" "$dest/evidencias" --only-show-errors
[ -f "$dest/manifest.json" ] || { echo "No hay una copia $stamp fuera del sitio."; exit 1; }
echo "Copia $stamp traída a $dest: $(ls "$dest"/postgres/*.dump | wc -l) volcados, $(find "$dest/evidencias" -type f | wc -l) archivos."
