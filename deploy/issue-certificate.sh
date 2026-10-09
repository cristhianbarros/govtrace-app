#!/usr/bin/env bash
# It. 42b — el certificado comodín de Let's Encrypt para DOMAIN y *.DOMAIN
# (cada organización es un subdominio), validado por DNS en DuckDNS (D14). Lo
# deja en live/DOMAIN/ del volumen que lee el proxy.
#
# DuckDNS guarda un solo TXT. Un certificado de DOMAIN y *.DOMAIN pide dos
# valores en el mismo nombre (_acme-challenge.DOMAIN) a la vez, y el segundo
# borraría el primero. Por eso, dos pasos:
#   1. un certificado solo de DOMAIN, que deja validada su autorización;
#   2. el de los dos nombres: Let's Encrypt reutiliza esa autorización y solo
#      pide la de *.DOMAIN, un TXT. El del paso 1 se borra.
#
# Se puede repetir: mientras al certificado le queden más de RENEW_BEFORE_DAYS
# días (30), no pide otro. Con --reload-proxy, si lo renovó y el proxy corre,
# nginx lo recarga. DUCKDNS_TOKEN llega por el entorno (deploy/staging-up.sh,
# desde SSM).
set -euo pipefail
cd "$(dirname "$0")/.."

: "${DOMAIN:?Falta DOMAIN: el dominio del certificado comodín}"
: "${DUCKDNS_TOKEN:?Falta DUCKDNS_TOKEN (gestor de secretos)}"
case $DOMAIN in
    *.duckdns.org) ;;
    *) echo "issue-certificate.sh: DOMAIN debe ser un subdominio de duckdns.org (D14)." >&2; exit 2 ;;
esac
RENEW_BEFORE_DAYS=${RENEW_BEFORE_DAYS:-30}
ACME_SERVER=${ACME_SERVER:-https://acme-v02.api.letsencrypt.org/directory}

# shellcheck disable=SC2086 # COMPOSE_EXTRA_FILES son varios -f a propósito
COMPOSE="docker compose -f docker-compose.prod.yml ${COMPOSE_EXTRA_FILES:-}"
CERTBOT="$COMPOSE --profile tls run --rm -T certbot"

# ¿Sirve el que hay? Cubre los dos nombres y le quedan más de RENEW_BEFORE_DAYS días.
live=/etc/letsencrypt/live/$DOMAIN/fullchain.pem
if $COMPOSE --profile tls run --rm -T --entrypoint sh certbot -c "
        test -f $live &&
        openssl x509 -in $live -noout -checkend $((RENEW_BEFORE_DAYS * 86400)) >/dev/null &&
        openssl x509 -in $live -noout -ext subjectAltName | grep -qF 'DNS:*.$DOMAIN'" 2>/dev/null; then
    echo "El certificado de $DOMAIN sigue vigente: le quedan más de $RENEW_BEFORE_DAYS días."
    exit 0
fi

certonly() { # $1: el nombre del certificado; después, sus dominios
    local name=$1 args=()
    shift
    for domain in "$@"; do args+=(-d "$domain"); done
    if [ -n "${CERTBOT_EMAIL:-}" ]; then args+=(-m "$CERTBOT_EMAIL"); else args+=(--register-unsafely-without-email); fi
    # shellcheck disable=SC2086 # $CERTBOT es el comando de Compose, con sus palabras
    $CERTBOT certonly --non-interactive --agree-tos --server "$ACME_SERVER" \
        --manual --preferred-challenges dns \
        --manual-auth-hook '/hooks/duckdns-hook.sh auth' \
        --manual-cleanup-hook '/hooks/duckdns-hook.sh cleanup' \
        --force-renewal --cert-name "$name" "${args[@]}"
}

echo "Paso 1 de 2: la autorización de $DOMAIN"
certonly "$DOMAIN-paso1" "$DOMAIN"
echo "Paso 2 de 2: el certificado de $DOMAIN y *.$DOMAIN"
certonly "$DOMAIN" "$DOMAIN" "*.$DOMAIN"
# shellcheck disable=SC2086
$CERTBOT delete --non-interactive --cert-name "$DOMAIN-paso1" >/dev/null

if [ "${1:-}" = --reload-proxy ] && $COMPOSE ps --status running --services 2>/dev/null | grep -qx proxy; then
    $COMPOSE exec -T proxy nginx -s reload
    echo "El proxy cargó el certificado nuevo."
fi
echo "Certificado de $DOMAIN y *.$DOMAIN emitido."
