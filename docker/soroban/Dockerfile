# Herramientas del Smart Contract de sellado (it. 12, D4): Rust para
# compilar y probar el contrato Soroban, y Stellar CLI para desplegarlo en
# la red local standalone. En el host siguen bastando Docker y make.
#
# Versiones fijas (2026-09-28): la red principal de Stellar corre el
# Protocolo 27, que corresponde a soroban-sdk 27.0.6 y Stellar CLI 27.1.0.

# Stellar CLI sale de su imagen oficial en Docker Hub, no de un release de
# GitHub: algunas VPN corporativas desvían IPs de GitHub a un túnel que no
# responde, y el build fallaba de forma intermitente.
FROM stellar/stellar-cli:27.1.0 AS stellar-cli

FROM rust:1.98.1-slim-trixie

COPY --from=stellar-cli /usr/local/bin/stellar /usr/local/bin/stellar

# curl + jq: el script de despliegue fondea cuentas con friendbot y lee la
# interfaz del contrato. libssl/libdbus/libudev: las enlaza el binario de la CLI.
RUN apt-get update \
 && apt-get install -y --no-install-recommends curl ca-certificates jq libssl3t64 libdbus-1-3 libudev1 \
 && rm -rf /var/lib/apt/lists/* \
 && stellar --version \
 && rustup target add wasm32v1-none \
 && rustup component add rustfmt clippy \
 # El contenedor corre con el usuario del host (PUID): el caché de crates
 # (volumen) tiene que poder escribirse sin ser root.
 && mkdir -p /usr/local/cargo/registry /usr/local/cargo/git \
 && chmod 1777 /usr/local/cargo/registry /usr/local/cargo/git

WORKDIR /workspace/contracts/sealing
