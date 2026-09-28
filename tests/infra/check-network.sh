#!/usr/bin/env bash
# Diagnóstico de red para desarrollar con una VPN activa: make doctor.
#
# Una VPN corporativa puede romper el entorno sin un mensaje claro. Esto
# revisa, en orden:
#   1. que la subred del proyecto (DOCKER_SUBNET) salga por su bridge;
#   2. si la VPN tapa la subred de docker0, que los builds usen la red del
#      host (DOCKER_BUILD_NETWORK=host), porque sin eso los RUN se quedan
#      sin DNS ni internet;
#   3. que los contenedores del proyecto salgan a internet (packagist, npm,
#      crates.io, Docker Hub);
#   4. GitHub: si la VPN desvía alguna de sus IPs a un túnel que no responde,
#      git, gh y composer fallan de forma intermitente. Solo se avisa: es
#      del sistema, no del proyecto.
# Solo usa bash, ip, curl y Docker, que el proyecto ya exige en el host.
set -uo pipefail
cd "$(git rev-parse --show-toplevel)"

COMPOSE="docker compose --env-file .env.docker"
env_value() { sed -n "s/^$1=\([^ #]*\).*/\1/p" .env.docker 2>/dev/null | head -1; }
NETWORK=$(env_value DOCKER_NETWORK); NETWORK=${NETWORK:-govtrace_net}
BUILD_NET=$(env_value DOCKER_BUILD_NETWORK); BUILD_NET=${BUILD_NET:-host}
fail=0

route_dev() { ip route get "$1" 2>/dev/null | head -1 | sed -n 's/.* dev \([^ ]*\).*/\1/p'; }
subnet_probe_ip() { local net=${1%/*}; echo "${net%.*}.2"; }
ip_to_int() { local IFS=.; read -r a b c d <<<"$1"; echo $(( (a << 24) + (b << 16) + (c << 8) + d )); }
in_cidr() { # in_cidr IP CIDR
  local ip net bits mask
  ip=$(ip_to_int "$1"); net=$(ip_to_int "${2%/*}"); bits=${2#*/}
  mask=$(( bits == 0 ? 0 : (0xFFFFFFFF << (32 - bits)) & 0xFFFFFFFF ))
  (( (ip & mask) == (net & mask) ))
}

DEFAULT_DEV=$(ip route show default | head -1 | sed -n 's/.* dev \([^ ]*\).*/\1/p')
DEFAULT_GW=$(ip route show default | head -1 | sed -n 's/.* via \([^ ]*\).*/\1/p')

echo "== 1. Subred del proyecto"
subnet=$(docker network inspect "$NETWORK" --format '{{(index .IPAM.Config 0).Subnet}}' 2>/dev/null)
if [ -z "$subnet" ]; then
  echo "INFO  la red $NETWORK todavía no existe: corre make up y repite."
else
  dev=$(route_dev "$(subnet_probe_ip "$subnet")")
  case "$dev" in
    br-*|docker*) echo "PASS  $subnet sale por su bridge ($dev)";;
    *) echo "FAIL  $subnet sale por $dev: la VPN la tapa. Cambia DOCKER_SUBNET en .env.docker y recrea: make down && make up"; fail=1;;
  esac
fi

echo "== 2. Red por defecto de Docker (la que usan los RUN de docker build)"
bridge_subnet=$(docker network inspect bridge --format '{{(index .IPAM.Config 0).Subnet}}' 2>/dev/null)
dev=$(route_dev "$(subnet_probe_ip "${bridge_subnet:-172.17.0.0/16}")")
if [ "$dev" = docker0 ]; then
  echo "PASS  ${bridge_subnet} no choca con ninguna ruta"
elif [ "$BUILD_NET" = host ]; then
  echo "PASS  ${bridge_subnet} choca con $dev, pero los builds usan la red del host (DOCKER_BUILD_NETWORK=host)"
else
  echo "FAIL  ${bridge_subnet} choca con $dev y DOCKER_BUILD_NETWORK=$BUILD_NET: los builds se quedarán sin DNS. Pon DOCKER_BUILD_NETWORK=host en .env.docker"
  fail=1
fi

echo "== 3. Internet desde los contenedores del proyecto"
if ! $COMPOSE run --rm --no-deps -T app sh -c '
  for url in https://repo.packagist.org/packages.json https://registry.npmjs.org/ https://index.crates.io/config.json https://registry-1.docker.io/v2/; do
    code=$(curl -s -o /dev/null -m 10 -w "%{http_code}" "$url")
    case "$code" in 2*|3*|401) echo "PASS  $url ($code)";; *) echo "FAIL  $url ($code)"; bad=1;; esac
  done
  exit ${bad:-0}'; then
  fail=1
fi

echo "== 4. GitHub (git, gh, composer)"
# Rangos publicados por GitHub en https://api.github.com/meta (web, api, git).
github_ranges="140.82.112.0/20 143.55.64.0/20 185.199.108.0/22 192.30.252.0/22"
stuck=()
while read -r target _ dev rest; do
  [ -n "$dev" ] || continue
  ip=${target%/*}
  for range in $github_ranges; do
    if in_cidr "$ip" "$range" && [ "$dev" != "$DEFAULT_DEV" ]; then
      if ! curl -s -o /dev/null -m 6 --resolve "github.com:443:$ip" https://github.com/; then
        stuck+=("$ip ($dev)")
      fi
    fi
  done
done < <(ip -4 route show | awk '{print $1, $2, $3}' | awk '$2 == "dev"')

if [ ${#stuck[@]} -eq 0 ]; then
  echo "PASS  ninguna IP de GitHub va a un túnel que no responde"
else
  echo "WARN  la VPN desvía estas IPs de GitHub a un túnel que no responde: ${stuck[*]}"
  echo "      git push, gh y composer fallarán de forma intermitente (el DNS de GitHub rota entre IPs)."
  echo "      Lo correcto es pedirle a TI que lo arregle. Si tu política lo permite, puedes sacarlas del"
  echo "      túnel hasta la próxima reconexión:"
  for entry in "${stuck[@]}"; do
    echo "        sudo ip route replace ${entry%% *}/32 via $DEFAULT_GW dev $DEFAULT_DEV"
  done
fi

exit $fail
