#!/bin/sh
# It. 42b — el hook de certbot para validar por DNS en DuckDNS (D14). Corre
# dentro del contenedor certbot (docker-compose.prod.yml, perfil tls):
#   duckdns-hook.sh auth     pone el TXT que pide Let's Encrypt (CERTBOT_VALIDATION)
#   duckdns-hook.sh cleanup  lo borra
#
# DuckDNS guarda un solo TXT por dominio, para el dominio y todos sus
# subdominios: por eso deploy/issue-certificate.sh pide los nombres en dos pasos.
# El token llega por el entorno (DUCKDNS_TOKEN) y no se escribe en ninguna salida.
set -eu

mode=${1:?Uso: duckdns-hook.sh auth|cleanup}
: "${DUCKDNS_TOKEN:?Falta DUCKDNS_TOKEN}"
: "${CERTBOT_DOMAIN:?Lo llama certbot, que pone CERTBOT_DOMAIN}"

# *.govtrace.duckdns.org y govtrace.duckdns.org son el mismo dominio de DuckDNS: govtrace.
domain=${CERTBOT_DOMAIN#\*.}
domain=${domain%.duckdns.org}

case $mode in
    auth) change="txt=${CERTBOT_VALIDATION:?}" ;;
    cleanup) change="txt=&clear=true" ;;
    *) echo "duckdns-hook.sh: modo desconocido: $mode" >&2; exit 2 ;;
esac

# Sin -S ni la URL en ningún mensaje: lleva el token.
if ! answer=$(wget -qO- "${DUCKDNS_API_URL:-https://www.duckdns.org/update}?domains=$domain&token=$DUCKDNS_TOKEN&$change"); then
    echo "duckdns-hook.sh: DuckDNS no respondió ($mode del TXT de $domain)" >&2
    exit 1
fi
if [ "$answer" != OK ]; then
    echo "duckdns-hook.sh: DuckDNS rechazó el $mode del TXT de $domain (¿el token?)" >&2
    exit 1
fi

# Le da tiempo a los servidores de DuckDNS antes de que Let's Encrypt pregunte.
if [ "$mode" = auth ]; then
    sleep "${DUCKDNS_PROPAGATION_SECONDS:-60}"
fi
