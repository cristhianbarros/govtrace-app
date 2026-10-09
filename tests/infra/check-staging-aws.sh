#!/usr/bin/env bash
# Iteración 42b — make staging-aws-check: lo de staging en AWS que se puede
# probar sin AWS ni internet (D14).
#
# A. El certificado comodín (deploy/issue-certificate.sh), contra Pebble, la CA
#    de prueba de Let's Encrypt, y un DuckDNS de mentira que guarda un solo TXT,
#    como el real (tests/infra/staging-aws-check.compose.yml). El servicio
#    certbot es el de docker-compose.prod.yml.
# B. Los secretos desde SSM Parameter Store (deploy/secrets-from-ssm.sh), con un
#    `aws` de mentira.
# C. La plantilla de CloudFormation (deploy/aws/staging.yml), con cfn-lint y lo
#    que no puede cambiar sin que se note; y los scripts de deploy/, con shellcheck.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

WORK=$(mktemp -d)
fail=0
pass() { echo "PASS  $1"; }
flunk() { echo "FAIL  $1"; fail=1; }

set -a
DOMAIN=govtrace.duckdns.org
COMPOSE_PROJECT_NAME=govtrace-staging-aws-check
DOCKER_NETWORK=govtrace_staging_aws_check_net
DOCKER_SUBNET=172.29.241.0/24
COMPOSE_EXTRA_FILES="-f tests/infra/staging-aws-check.compose.yml"
# docker-compose.prod.yml exige estas dos aunque aquí no se usen.
DB_PASSWORD=sin-uso
BACKUP_S3_BUCKET=sin-uso
ACME_SERVER=https://pebble:14000/dir
CERTBOT_EMAIL=staging-aws-check@govtrace.test
DUCKDNS_TOKEN="token-de-prueba-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')"
DUCKDNS_API_URL=http://duckdns:8080/update
DUCKDNS_PROPAGATION_SECONDS=0
PEBBLE_CA_FILE=$WORK/pebble-minica.pem
set +a

COMPOSE="docker compose -f docker-compose.prod.yml $COMPOSE_EXTRA_FILES"
cleanup() {
    $COMPOSE --profile '*' down -v --remove-orphans >/dev/null 2>&1
    rm -rf "$WORK"
}
trap cleanup EXIT

# Lo que ve el certbot de producción en su volumen.
in_certbot() { $COMPOSE --profile tls run --rm -T --entrypoint sh certbot -c "$1" 2>/dev/null; }
serial() { in_certbot "openssl x509 -in /etc/letsencrypt/live/$DOMAIN/fullchain.pem -noout -serial"; }
duckdns_state() { $COMPOSE exec -T duckdns wget -qO- http://127.0.0.1:8080/state; }

# ------------------------------------------------- A. El certificado comodín --
c=$(docker create ghcr.io/letsencrypt/pebble:2.7.0) && docker cp "$c":/test/certs/pebble.minica.pem - | tar xO > "$PEBBLE_CA_FILE"
docker rm "$c" >/dev/null
chmod a+r "$PEBBLE_CA_FILE"
$COMPOSE up -d --wait pebble challtestsrv duckdns >/dev/null 2>&1 \
    || { echo "FAIL  no arrancaron Pebble, challtestsrv y el DuckDNS de mentira"; exit 1; }
# Pebble tarda un instante en atender.
for _ in $(seq 1 20); do in_certbot 'python3 -c "import urllib.request; urllib.request.urlopen(\"https://pebble:14000/dir\", timeout=2)"' && break; sleep 1; done

# 1. La prueba reproduce el límite de DuckDNS: los dos nombres de una vez fallan,
#    porque el segundo TXT borra el primero. (Otro dominio, para no dejarle a
#    $DOMAIN una autorización validada.)
other=de-una-vez.duckdns.org
if at_once=$($COMPOSE --profile tls run --rm -T certbot certonly --non-interactive --agree-tos --register-unsafely-without-email \
        --server "$ACME_SERVER" --manual --preferred-challenges dns \
        --manual-auth-hook '/hooks/duckdns-hook.sh auth' --manual-cleanup-hook '/hooks/duckdns-hook.sh cleanup' \
        --cert-name "$other" -d "$other" -d "*.$other" 2>&1); then
    flunk "los dos nombres de una vez pasaron: la prueba no reproduce el límite de DuckDNS"
elif grep -qiE 'incorrect txt|correct value not found|unauthorized' <<< "$at_once"; then
    pass "pedir el dominio y su comodín de una vez falla, como con el DuckDNS real: el segundo TXT borra el primero"
else
    flunk "los dos nombres de una vez fallaron, pero no por el TXT: $(tail -3 <<< "$at_once")"
fi

# 2. En dos pasos, sí: el certificado cubre el dominio y su comodín.
if out=$(bash deploy/issue-certificate.sh 2>&1); then
    pass "deploy/issue-certificate.sh emitió el certificado"
else
    flunk "deploy/issue-certificate.sh falló"
    echo "$out" | tail -30
fi
sans=$(in_certbot "openssl x509 -in /etc/letsencrypt/live/$DOMAIN/fullchain.pem -noout -ext subjectAltName")
if grep -qF "DNS:$DOMAIN" <<< "$sans" && grep -qF "DNS:*.$DOMAIN" <<< "$sans"; then
    pass "el certificado de live/$DOMAIN cubre $DOMAIN y *.$DOMAIN"
else
    flunk "el certificado no cubre los dos nombres: $(echo $sans)"
fi
# Lo firmó la CA de Pebble: la raíz la da su API de administración.
if in_certbot "python3 -c \"import ssl, urllib.request; print(urllib.request.urlopen('https://pebble:15000/roots/0', context=ssl.create_default_context(cafile='/pebble/minica.pem')).read().decode())\" > /tmp/root.pem &&
        openssl verify -CAfile /tmp/root.pem -untrusted /etc/letsencrypt/live/$DOMAIN/chain.pem /etc/letsencrypt/live/$DOMAIN/cert.pem" | grep -q ': OK'; then
    pass "la cadena del certificado verifica contra la raíz de la CA"
else
    flunk "la cadena del certificado no verifica"
fi
leftovers=$(in_certbot "ls /etc/letsencrypt/live" | grep -vxE "$DOMAIN|$other|README" | xargs)
[ -z "$leftovers" ] && pass "el certificado del primer paso no queda" || flunk "quedaron certificados de más: $leftovers"

# 3. El TXT queda vacío, y el token no aparece ni en la salida ni en los archivos.
state=$(duckdns_state)
if python3 -I -c 'import json, sys; s = json.loads(sys.argv[1]); sys.exit(0 if s["txt"].get("govtrace") == "" else 1)' "$state"; then
    pass "el TXT de DuckDNS queda vacío al terminar"
else
    flunk "el TXT de DuckDNS no quedó vacío: $state"
fi
if grep -qF "$DUCKDNS_TOKEN" <<< "$out" || in_certbot "grep -rqF '$DUCKDNS_TOKEN' /etc/letsencrypt"; then
    flunk "el token de DuckDNS quedó en la salida o en /etc/letsencrypt"
else
    pass "el token de DuckDNS no sale en la salida ni queda en /etc/letsencrypt"
fi

# 4. Repetirlo con más de 30 días por delante no pide otro; con menos, lo renueva.
before=$(serial)
again=$(bash deploy/issue-certificate.sh 2>&1)
[ -n "$before" ] && [ "$(serial)" = "$before" ] && grep -q 'vigente' <<< "$again" \
    && pass "con más de 30 días por delante, no pide otro" || flunk "pidió otro certificado sin necesidad: $(grep -vE "Container" <<< "$again" | head -8)"
# Pebble los da por 90 días, como Let's Encrypt: con 91, toca renovar.
RENEW_BEFORE_DAYS=91 bash deploy/issue-certificate.sh >/dev/null 2>&1
after=$(serial)
[ -n "$after" ] && [ "$after" != "$before" ] && pass "con menos días de los pedidos, lo renueva" || flunk "no lo renovó"

# 5. Sin token, ni lo intenta.
if DUCKDNS_TOKEN='' bash deploy/issue-certificate.sh >/dev/null 2>&1; then
    flunk "corrió sin DUCKDNS_TOKEN"
else
    pass "sin DUCKDNS_TOKEN se niega a correr"
fi

# ------------------------------------------- B. Los secretos desde SSM --------
mkdir -p "$WORK/bin"
cat > "$WORK/bin/aws" <<'FAKE'
#!/usr/bin/env bash
# Un `aws` de mentira: lo que devolvería ssm get-parameters-by-path con --query 'Parameters[].[Name,Value]'.
[ "$1 $2" = "ssm get-parameters-by-path" ] || { echo "aws de mentira: no sé hacer $*" >&2; exit 1; }
[ -n "${FAKE_AWS_FAIL:-}" ] && { echo "An error occurred (AccessDeniedException)" >&2; exit 254; }
python3 -I -c '
import json, os
params = [
    ["/govtrace/staging/APP_KEY", "base64:Zm9vYmFyYmF6+/="],
    ["/govtrace/staging/MAIL_PASSWORD", "abcd efgh '"'"'ijkl'"'"' \"mn\" $HOME `id` \\ fin"],
    ["/govtrace/staging/MULTILINEA", "uno\ndos"],
]
if os.environ.get("FAKE_AWS_BAD"):
    params.append(["/govtrace/staging/no-es-variable", "x"])
print(json.dumps(params))
'
FAKE
chmod +x "$WORK/bin/aws"
expected_mail="abcd efgh 'ijkl' \"mn\" \$HOME \`id\` \\ fin"

got=$(PATH="$WORK/bin:$PATH" bash deploy/secrets-from-ssm.sh /govtrace/staging -- sh -c 'printf "%s|%s|%s" "$APP_KEY" "$MAIL_PASSWORD" "$MULTILINEA"' 2>&1)
[ "$got" = "base64:Zm9vYmFyYmF6+/=|$expected_mail|uno
dos" ] && pass "los valores con espacios, comillas, \$ y saltos de línea llegan intactos" || flunk "los valores llegaron cambiados: $got"

loader_out=$(PATH="$WORK/bin:$PATH" bash deploy/secrets-from-ssm.sh /govtrace/staging -- true 2>&1)
if grep -qE 'Zm9vYmFy|abcd efgh' <<< "$loader_out"; then
    flunk "el cargador muestra valores en su salida"
else
    pass "el cargador no muestra ningún valor"
fi

if bad=$(FAKE_AWS_BAD=1 PATH="$WORK/bin:$PATH" bash deploy/secrets-from-ssm.sh /govtrace/staging -- sh -c 'echo CORRIO' 2>&1); then
    flunk "aceptó un parámetro que no es un nombre de variable"
else
    grep -q CORRIO <<< "$bad" && flunk "corrió el comando con un parámetro inválido" \
        || pass "un parámetro que no es un nombre de variable se rechaza, y el comando no corre"
fi

if denied=$(FAKE_AWS_FAIL=1 PATH="$WORK/bin:$PATH" bash deploy/secrets-from-ssm.sh /govtrace/staging -- sh -c 'echo CORRIO' 2>&1); then
    flunk "siguió aunque SSM no respondió"
else
    grep -q CORRIO <<< "$denied" && flunk "corrió el comando sin los secretos" \
        || pass "si SSM no responde, el comando no corre"
fi

# ------------------------------------- C. La plantilla y los scripts ----------
if lint=$(docker run --rm -v "$PWD/deploy/aws:/plantilla:ro" python:3.13-alpine sh -c \
        'pip install -q --disable-pip-version-check --root-user-action=ignore cfn-lint==1.55.0 2>/dev/null && cfn-lint --regions us-east-2 -- /plantilla/staging.yml' 2>&1); then
    pass "cfn-lint no encuentra errores en deploy/aws/staging.yml"
else
    flunk "cfn-lint encontró errores: $(tail -8 <<< "$lint")"
fi

# Lo que la plantilla no puede perder: solo 80 y 443 abiertos, IMDSv2, los
# buckets privados y que sobreviven a la pila, y ningún permiso con comodín.
invariants=$(docker run --rm -v "$PWD/deploy/aws:/plantilla:ro" python:3.13-alpine sh -c \
    'pip install -q --disable-pip-version-check --root-user-action=ignore cfn-lint==1.55.0 2>/dev/null && python3 -I -c "
from cfnlint.decode import cfn_yaml
t = cfn_yaml.load(\"/plantilla/staging.yml\")[\"Resources\"]
problems = []
ports = {(r[\"FromPort\"], r[\"ToPort\"]) for r in t[\"WebSecurityGroup\"][\"Properties\"][\"SecurityGroupIngress\"]}
if ports != {(80, 80), (443, 443)}:
    problems.append(f\"puertos abiertos: {sorted(ports)}\")
if t[\"Instance\"][\"Properties\"][\"MetadataOptions\"].get(\"HttpTokens\") != \"required\":
    problems.append(\"la máquina acepta IMDSv1\")
for name in (\"EvidenceBucket\", \"BackupBucket\"):
    bucket = t[name]
    block = bucket[\"Properties\"][\"PublicAccessBlockConfiguration\"]
    if not all(block.get(k) is True for k in (\"BlockPublicAcls\", \"BlockPublicPolicy\", \"IgnorePublicAcls\", \"RestrictPublicBuckets\")):
        problems.append(f\"{name} no bloquea el acceso público\")
    if bucket.get(\"DeletionPolicy\") != \"Retain\":
        problems.append(f\"{name} se borraría con la pila\")
for policy in t[\"InstanceRole\"][\"Properties\"][\"Policies\"]:
    for statement in policy[\"PolicyDocument\"][\"Statement\"]:
        actions = statement[\"Action\"] if isinstance(statement[\"Action\"], list) else [statement[\"Action\"]]
        resources = statement[\"Resource\"] if isinstance(statement[\"Resource\"], list) else [statement[\"Resource\"]]
        if any(\"*\" in a for a in actions) or any(r == \"*\" for r in resources):
            problems.append(f\"un permiso con comodín: {statement.get(chr(83) + \"id\")}\")
print(\"; \".join(problems) or \"ok\")
"' 2>&1)
[ "$invariants" = ok ] && pass "la plantilla: solo 80 y 443, IMDSv2, buckets privados que sobreviven a la pila, permisos sin comodín" \
    || flunk "la plantilla: $invariants"

# En el servidor nadie define DOCKER_SUBNET (make staging-check sí, y lo
# tapaba): la app debe recibir la misma subred de la red, o Apache no arranca
# (RemoteIPInternalProxy, docker/app/vhost.conf).
subnets=$(env -u DOCKER_SUBNET -u COMPOSE_PROJECT_NAME -u DOCKER_NETWORK -u COMPOSE_EXTRA_FILES \
    DB_PASSWORD=sin-uso BACKUP_S3_BUCKET=sin-uso docker compose -f docker-compose.prod.yml config --format json 2>/dev/null \
    | python3 -I -c 'import json, sys; c = json.load(sys.stdin); print(c["services"]["app"]["environment"].get("DOCKER_SUBNET"), c["networks"]["govtrace"]["ipam"]["config"][0]["subnet"])')
read -r app_subnet network_subnet <<< "$subnets"
if [ -n "$network_subnet" ] && [ "$app_subnet" = "$network_subnet" ]; then
    pass "sin DOCKER_SUBNET en el entorno, la app recibe la subred de la red ($network_subnet)"
else
    flunk "sin DOCKER_SUBNET en el entorno, la app recibe '$app_subnet' y la red usa '$network_subnet': Apache no arrancaría"
fi

if sc=$(docker run --rm -v "$PWD:/mnt:ro" -w /mnt koalaman/shellcheck:v0.10.0 -S warning deploy/*.sh deploy/aws/*.sh 2>&1); then
    pass "shellcheck no encuentra problemas en los scripts de deploy/"
else
    flunk "shellcheck: $(head -12 <<< "$sc")"
fi

exit $fail
