#!/usr/bin/env bash
# The minimum that must happen before the main process starts.
# No recursive chown/chmod over the tree: permissions are right because the
# container UID matches the host UID, not because they get rewritten.
set -euo pipefail

# 1. Re-map UID/GID at runtime if the host differs from the build values.
#    Only possible (and only needed) when the container starts as root.
if [ "$(id -u)" = "0" ] && [ -n "${PUID:-}" ] && [ "$(id -u "${APP_USER}")" != "${PUID}" ]; then
    usermod  -u "${PUID}" "${APP_USER}"
    groupmod -g "${PGID:-$PUID}" "${APP_USER}"
    chown -R "${APP_USER}:${APP_USER}" "/home/${APP_USER}" /tmp/composer
fi

# 2. Storage skeleton on a fresh clone. Every level is listed on purpose:
#    "install -d a/b" creates parent "a" as root even with -o (GNU install).
#    Only creates what is missing; never touches existing permissions.
for d in \
    storage/app \
    storage/app/public \
    storage/app/private \
    storage/framework \
    storage/framework/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
do
    if [ ! -d "$d" ]; then
        if [ "$(id -u)" = "0" ]; then
            install -d -o "${APP_USER}" -g "${APP_USER}" -m 775 "$d"
        else
            install -d -m 775 "$d"
        fi
    fi
done

# exec: the main process becomes PID 1 and receives SIGTERM directly.
exec "$@"
