#!/usr/bin/env bash
# It. 42b — en la máquina de staging (D14), como root desde /opt/govtrace:
#   deploy/staging-up.sh              todo: lo corre make staging-deploy por SSM
#   deploy/staging-up.sh --cert-only  solo el certificado: el temporizador semanal
#
#   1. la configuración pública (.env.staging.example) y los secretos de SSM
#      (deploy/secrets-from-ssm.sh), solo en el entorno de este proceso;
#   2. DuckDNS apunta a la IP fija de esta máquina;
#   3. el certificado comodín, si le quedan menos de 30 días (deploy/issue-certificate.sh);
#   4. deploy/deploy.sh;
#   5. el temporizador de systemd que renueva el certificado.
set -euo pipefail
cd "$(dirname "$0")/.."
SSM_PATH=${SSM_PATH:-/govtrace/staging}

if [ "${GOVTRACE_SECRETS_LOADED:-}" != 1 ]; then
    set -a
    # shellcheck disable=SC1090
    . <(grep -E '^[A-Z0-9_]+=' .env.staging.example | sed -E 's/[[:space:]]+#.*$//')
    set +a
    GOVTRACE_SECRETS_LOADED=1 exec deploy/secrets-from-ssm.sh "$SSM_PATH" -- "$PWD/deploy/staging-up.sh" "$@"
fi

: "${DUCKDNS_TOKEN:?Falta $SSM_PATH/DUCKDNS_TOKEN en SSM: make staging-secret NAME=DUCKDNS_TOKEN}"

if [ "${1:-}" = --cert-only ]; then
    exec deploy/issue-certificate.sh --reload-proxy
fi

# La IP pública de esta máquina (su IP fija), por IMDSv2.
imds_token=$(curl -fsS --max-time 5 -X PUT http://169.254.169.254/latest/api/token -H 'X-aws-ec2-metadata-token-ttl-seconds: 60')
ip=$(curl -fsS --max-time 5 -H "X-aws-ec2-metadata-token: $imds_token" http://169.254.169.254/latest/meta-data/public-ipv4)

# La URL lleva el token: curl la lee de su entrada (-K -), no de los argumentos
# que cualquiera ve con ps.
answer=$(printf 'url = "https://www.duckdns.org/update?domains=%s&token=%s&ip=%s"\n' \
    "${DOMAIN%.duckdns.org}" "$DUCKDNS_TOKEN" "$ip" | curl -fsS --max-time 20 -K -)
[ "$answer" = OK ] || { echo "staging-up.sh: DuckDNS no aceptó la IP $ip (¿el token?)" >&2; exit 1; }
echo "DuckDNS: $DOMAIN y *.$DOMAIN apuntan a $ip"

deploy/issue-certificate.sh --reload-proxy
deploy/deploy.sh

install -m 0644 deploy/systemd/govtrace-certificate.service deploy/systemd/govtrace-certificate.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now govtrace-certificate.timer >/dev/null
echo "El certificado se revisa cada semana: systemctl list-timers govtrace-certificate.timer"
