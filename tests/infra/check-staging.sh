#!/usr/bin/env bash
# Iteración 42a — make staging-check: el stack de producción
# (docker-compose.prod.yml), de punta a punta en local, en un proyecto aislado
# de Docker, como lo despliega deploy/deploy.sh en el servidor:
#   - la plantilla .env.staging.example, con secretos de un solo uso;
#   - una CA de prueba y un certificado comodín para govtrace.localhost, donde
#     Let's Encrypt los deja (live/<dominio>/);
#   - versitygw haciendo de S3 (tests/infra/staging-check.compose.yml, it. 45d).
# Comprueba lo que solo se ve con nginx, Apache y PHP juntos: que por HTTPS
# la aplicación sabe que es HTTPS. Los tests de Pest no pasan por Apache.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

# Sus propios nombres: la plantilla trae HTTP_PORT y HTTPS_PORT, y los pisaría.
CHECK_HTTP_PORT=18082
CHECK_HTTPS_PORT=18443
HOST=govtrace.localhost
TENANT_HOST=veeduria-staging.govtrace.localhost
WORK=$(mktemp -d)
fail=0
pass() { echo "PASS  $1"; }
flunk() { echo "FAIL  $1"; fail=1; }

# El entorno: la plantilla de staging, con lo propio de esta prueba.
set -a
# shellcheck disable=SC1090
. <(grep -E '^[A-Z0-9_]+=' .env.staging.example | sed -E 's/[[:space:]]+#.*$//')
COMPOSE_PROJECT_NAME=govtrace-staging-check
DOCKER_NETWORK=govtrace_staging_check_net
DOCKER_SUBNET=172.29.240.0/24
COMPOSE_EXTRA_FILES="-f tests/infra/staging-check.compose.yml"
APP_IMAGE=govtrace-app:staging-check
BACKUP_IMAGE=govtrace-backup:staging-check
HTTP_PORT=$CHECK_HTTP_PORT
HTTPS_PORT=$CHECK_HTTPS_PORT
# Solo en este equipo: la prueba no se publica en la red.
PUBLISH_IP=127.0.0.1
DOMAIN=$HOST
APP_URL=https://$HOST:$HTTPS_PORT
TENANCY_CENTRAL_DOMAINS=$HOST
TENANCY_APEX_DOMAIN=$HOST
PUBLIC_PORT_SUFFIX=":$HTTPS_PORT"
LETSENCRYPT_DIR=$WORK/letsencrypt
DEPLOY_CURL_OPTS="--cacert $WORK/ca.pem"
# Con una VPN, los RUN de docker build solo tienen internet por la red del host (.env.docker).
DOCKER_BUILD_NETWORK=$(sed -n 's/^DOCKER_BUILD_NETWORK=//p' .env.docker 2>/dev/null | head -1)
DOCKER_BUILD_NETWORK=${DOCKER_BUILD_NETWORK:-default}
# Secretos de un solo uso: la prueba no toca los de nadie.
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
DB_PASSWORD=$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')
SEALING_PSEUDONYM_KEY=$(head -c 32 /dev/urandom | base64)
MAIL_MAILER=log
# Como en producción: el visitante no es de confianza. Aquí llega desde el gateway
# de Docker, con una IP privada que la lista por defecto sí cubriría; en internet
# llega con la suya, pública. Con esta lista, Laravel no le cree a nadie, y la
# visita por HTTPS la tiene que reconocer el stack mismo (nginx y Apache).
TRUSTED_PROXIES=10.255.255.0/24
# S3: versitygw, con credenciales de prueba (en AWS, el rol de la instancia).
EVIDENCE_AWS_ENDPOINT=http://storage:4566
EVIDENCE_AWS_USE_PATH_STYLE_ENDPOINT=true
EVIDENCE_AWS_ACCESS_KEY_ID=test
EVIDENCE_AWS_SECRET_ACCESS_KEY=test
BACKUP_S3_ENDPOINT=http://storage:4566
BACKUP_AWS_ACCESS_KEY_ID=test
BACKUP_AWS_SECRET_ACCESS_KEY=test
# Sin la réplica de la plantilla (el bucket de respaldos de AWS, it. 42b): la
# prueba make backup-check.
BACKUP_OFFSITE_S3_URL=
set +a

COMPOSE="docker compose -f docker-compose.prod.yml $COMPOSE_EXTRA_FILES"
CURL=(curl -sS --max-time 20 --cacert "$WORK/ca.pem" --resolve "$HOST:$HTTPS_PORT:127.0.0.1" --resolve "$TENANT_HOST:$HTTPS_PORT:127.0.0.1")
cleanup() {
    $COMPOSE --profile '*' down -v --remove-orphans >/dev/null 2>&1
    rm -rf "$WORK"
}
trap cleanup EXIT

# 0. Cada variable de la plantilla llega a su servicio: el compose la nombra. (Los
#    puertos y las imágenes los usa el propio compose, no un servicio, y el
#    correo de Let's Encrypt, deploy/issue-certificate.sh.)
missing=$(grep -oE '^[A-Z0-9_]+' .env.staging.example | grep -vxE 'PUBLISH_IP|HTTP_PORT|HTTPS_PORT|APP_IMAGE|BACKUP_IMAGE|CERTBOT_EMAIL' \
    | while read -r name; do grep -qE "^\s+- $name$|\\\$\{$name[:?}-]" docker-compose.prod.yml || echo "$name"; done)
[ -z "$missing" ] && pass "el compose de producción pasa a la aplicación cada variable de la plantilla" || flunk "el compose no pasa: $(echo $missing)"

# 1. La imagen inmutable, y con ella una CA de prueba y el certificado comodín.
docker build -q --network "$DOCKER_BUILD_NETWORK" -f docker/app/Dockerfile --target qa -t "$APP_IMAGE" . >/dev/null \
    || { echo "FAIL  la imagen qa no se construyó"; exit 1; }
docker run --rm --network none --user "$(id -u):$(id -g)" -v "$WORK:/w" --entrypoint sh "$APP_IMAGE" -c "
    cd /w && mkdir -p letsencrypt/live/$HOST &&
    openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj '/CN=GovTrace CA de prueba' -keyout ca.key -out ca.pem 2>/dev/null &&
    openssl req -newkey rsa:2048 -nodes -subj '/CN=$HOST' -keyout letsencrypt/live/$HOST/privkey.pem -out server.csr 2>/dev/null &&
    printf 'subjectAltName=DNS:$HOST,DNS:*.$HOST\n' > san.ext &&
    openssl x509 -req -in server.csr -CA ca.pem -CAkey ca.key -CAcreateserial -days 1 -extfile san.ext -out letsencrypt/live/$HOST/fullchain.pem 2>/dev/null
" || { echo "FAIL  no se pudo crear el certificado de prueba"; exit 1; }
chmod -R a+rX "$WORK/letsencrypt"

# La imagen de producción: sin herramientas de desarrollo ni errores a la vista.
if docker run --rm --entrypoint sh "$APP_IMAGE" -c '! command -v composer && ! php -m | grep -qi xdebug && php -r "exit(ini_get(\"display_errors\") ? 1 : 0);"'; then
    pass "la imagen inmutable no trae composer ni xdebug, y no muestra errores"
else
    flunk "la imagen inmutable trae herramientas de desarrollo o muestra errores"
fi

# 2. El despliegue, como en el servidor.
if out=$(bash deploy/deploy.sh 2>&1); then
    pass "deploy/deploy.sh levantó el stack de producción"
else
    flunk "deploy/deploy.sh falló"
    echo "$out" | tail -40
    exit 1
fi

# 3. HTTPS, con el certificado de la CA de prueba (verificado, no -k).
code=$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$HOST:$HTTPS_PORT/up")
[ "$code" = 200 ] && pass "HTTPS responde con el certificado de la CA de prueba" || flunk "HTTPS respondió $code"

tls11=$(docker run --rm --network host --entrypoint sh "$APP_IMAGE" -c "openssl s_client -connect 127.0.0.1:$HTTPS_PORT -servername $HOST -tls1_1 -cipher 'DEFAULT@SECLEVEL=0' </dev/null 2>&1")
tls12=$(docker run --rm --network host --entrypoint sh "$APP_IMAGE" -c "openssl s_client -connect 127.0.0.1:$HTTPS_PORT -servername $HOST -tls1_2 </dev/null 2>&1")
if grep -q 'alert protocol version' <<< "$tls11" && grep -q 'Protocol *: TLSv1.2' <<< "$tls12"; then
    pass "acepta TLS 1.2 y rechaza TLS 1.1"
else
    flunk "TLS 1.1 no se rechazó, o TLS 1.2 no se aceptó"
fi

# 4. HTTP a HTTPS, con el mismo host.
redirect=$(curl -sS --max-time 20 --resolve "$HOST:$HTTP_PORT:127.0.0.1" -o /dev/null -w '%{http_code} %{redirect_url}' "http://$HOST:$HTTP_PORT/up")
[ "$redirect" = "301 https://$HOST:$HTTPS_PORT/up" ] && pass "HTTP redirige a HTTPS" || flunk "HTTP respondió: $redirect"

# 5. Por HTTPS, la aplicación sabe que es HTTPS (lo que se rompía con mod_remoteip).
login_headers=$("${CURL[@]}" -D - -o "$WORK/login.html" "https://$HOST:$HTTPS_PORT/login")
grep -qi '^strict-transport-security: max-age=31536000; includeSubDomains' <<< "$login_headers" \
    && pass "por HTTPS va HSTS: la aplicación sabe que la visita llegó por HTTPS" || flunk "sin HSTS: la aplicación no ve el HTTPS"
grep -qi '^content-security-policy:.*upgrade-insecure-requests' <<< "$login_headers" \
    && pass "la CSP pide mejorar las peticiones inseguras" || flunk "la CSP no trae upgrade-insecure-requests"
location=$("${CURL[@]}" -o /dev/null -w '%{redirect_url}' "https://$HOST:$HTTPS_PORT/dashboard")
[ "$location" = "https://$HOST:$HTTPS_PORT/login" ] && pass "las redirecciones salen en https" || flunk "la redirección fue a: $location"
if grep -qE '(src|href)="https://'"$HOST:$HTTPS_PORT"'/build/' "$WORK/login.html" && ! grep -qE '(src|href)="http://' "$WORK/login.html"; then
    pass "los recursos de la página salen en https: nada de contenido mixto"
else
    flunk "la página enlaza recursos por http: $(grep -oE '(src|href)="http://[^"]*' "$WORK/login.html" | head -2)"
fi

# 6. Cookies seguras; sin depuración a la vista; el host del visitante, ignorado.
cookies=$(grep -i '^set-cookie:' <<< "$login_headers")
if [ -n "$cookies" ] && ! grep -viq 'secure' <<< "$cookies" && grep -i 'session' <<< "$cookies" | grep -qi 'httponly'; then
    pass "las cookies son Secure, y la de la sesión HttpOnly"
else
    flunk "cookies sin Secure o sesión sin HttpOnly"
fi
missing_page=$("${CURL[@]}" "https://$HOST:$HTTPS_PORT/no-existe")
if grep -q 'No encontrado' <<< "$missing_page" && ! grep -qiE 'whoops|stack trace|Illuminate\\|vendor/laravel' <<< "$missing_page"; then
    pass "una página de error no muestra depuración, y está en español"
else
    flunk "la página de error muestra depuración o no está en español"
fi
spoofed=$("${CURL[@]}" -H 'X-Forwarded-Host: atacante.example' -H 'X-Forwarded-Port: 6666' -o /dev/null -w '%{redirect_url}' "https://$HOST:$HTTPS_PORT/dashboard")
[ "$spoofed" = "https://$HOST:$HTTPS_PORT/login" ] && pass "el host y el puerto que manda el visitante se ignoran" || flunk "se creyó el host del visitante: $spoofed"

# 7. Una organización, en su subdominio, por HTTPS (el certificado comodín).
$COMPOSE exec -T -u workspace app php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    (new App\Application\Organization\RegisterOrganization)->handle("900123456-8", "Veeduría de Staging", "veeduria-staging");
' >/dev/null 2>&1
code=$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$TENANT_HOST:$HTTPS_PORT/")
[ "$code" = 200 ] && pass "una organización responde en su subdominio por HTTPS" || flunk "el subdominio de la organización respondió $code"

# 8. El worker, el calendario y los respaldos, sanos; la configuración, en caché.
unhealthy=$($COMPOSE ps --format '{{.Service}} {{.Health}}' | awk '$2 != "healthy" {print $1}' | xargs)
[ -z "$unhealthy" ] && pass "todos los servicios sanos: app, proxy, pgsql, worker, scheduler, backup" || flunk "servicios sin salud: $unhealthy"
$COMPOSE exec -T app test -f bootstrap/cache/config.php && pass "la configuración está en caché" || flunk "la configuración no está en caché"
refusal=$($COMPOSE exec -T -u workspace app php artisan demo:prepare 2>&1)
grep -q 'no corre en producción' <<< "$refusal" && pass "make demo se niega a correr en producción" || flunk "la demostración corrió en producción"

# Los logs: los tres contenedores de la aplicación escriben en su volumen, como su usuario.
unwritable=$(for service in app worker scheduler; do
    $COMPOSE exec -T -u workspace "$service" php -r 'exit(file_put_contents("storage/logs/staging-check.log", getenv("HOSTNAME")."\n", FILE_APPEND) ? 0 : 1);' >/dev/null 2>&1 || echo "$service"
done | xargs)
[ -z "$unwritable" ] && pass "app, worker y scheduler escriben en el volumen de logs" || flunk "no pueden escribir sus logs: $unwritable"

# 8b. It. 42c: mientras un despliegue reemplaza la app, nginx no tiene a quién
#     pasarle la visita. En vez de un 502, un 503 en español que dice que
#     vuelva en un minuto, y en JSON a la app del veedor (su bandeja de salida
#     guarda el reporte y lo reintenta). Al volver la app, todo sigue igual.
$COMPOSE stop app >/dev/null 2>&1
maintenance_headers=$("${CURL[@]}" -D - -o "$WORK/maintenance.html" "https://$HOST:$HTTPS_PORT/login")
if grep -qE '^HTTP/[0-9.]+ 503' <<< "$maintenance_headers" && grep -qi '^retry-after: 60' <<< "$maintenance_headers" \
        && grep -q 'Estamos actualizando GovTrace' "$WORK/maintenance.html"; then
    pass "sin la app, HTTPS responde 503 en español, con Retry-After"
else
    flunk "sin la app no hay página de mantenimiento: $(head -1 <<< "$maintenance_headers")"
fi
tenant_code=$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$TENANT_HOST:$HTTPS_PORT/")
[ "$tenant_code" = 503 ] && pass "también en el subdominio de una organización" || flunk "el subdominio respondió $tenant_code sin la app"
json=$("${CURL[@]}" -H 'Accept: application/json' -D - "https://$TENANT_HOST:$HTTPS_PORT/me/reports")
if grep -qE '^HTTP/[0-9.]+ 503' <<< "$json" && grep -qi '^content-type: application/json' <<< "$json" \
        && grep -q '"message": *"Estamos actualizando GovTrace' <<< "$json"; then
    pass "a la app del veedor, el 503 le llega en JSON"
else
    flunk "a una petición JSON no le llega el 503 en JSON: $(grep -i '^content-type' <<< "$json")"
fi
$COMPOSE start app >/dev/null 2>&1
for _ in $(seq 1 30); do
    [ "$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$HOST:$HTTPS_PORT/up")" = 200 ] && break
    sleep 2
done
[ "$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$HOST:$HTTPS_PORT/up")" = 200 ] \
    && pass "al volver la app, responde 200" || flunk "la app no volvió a responder"

# 9. Desplegar otra vez no rompe nada, y los logs sobreviven.
if bash deploy/deploy.sh >/dev/null 2>&1 && [ "$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$TENANT_HOST:$HTTPS_PORT/")" = 200 ]; then
    pass "desplegar dos veces no rompe nada: la organización sigue ahí"
else
    flunk "el segundo despliegue rompió algo"
fi
# It. 42c: con MAINTENANCE=1 (una migración que rompe la versión anterior), el
# despliegue apaga la app antes de migrar, y al final todo vuelve.
if out=$(MAINTENANCE=1 bash deploy/deploy.sh 2>&1) && grep -q 'Modo mantenimiento' <<< "$out" \
        && [ "$("${CURL[@]}" -o /dev/null -w '%{http_code}' "https://$TENANT_HOST:$HTTPS_PORT/")" = 200 ]; then
    pass "con MAINTENANCE=1 el despliegue apaga la app para migrar, y todo vuelve"
else
    flunk "el despliegue con MAINTENANCE=1 falló: $(tail -3 <<< "$out")"
fi
lines=$($COMPOSE exec -T app sh -c 'wc -l < storage/logs/staging-check.log' 2>/dev/null | tr -d ' ')
[ "${lines:-0}" -ge 3 ] && pass "los logs sobreviven al despliegue: el volumen no se pierde" || flunk "los logs se perdieron al volver a desplegar"

exit $fail
