# Herramientas del Smart Contract de sellado (it. 12, D4): Rust para
# compilar y probar el contrato Soroban, y Stellar CLI para desplegarlo en
# la red local standalone. En el host siguen bastando Docker y make.
#
# Versiones fijas (2026-09-28): la red principal de Stellar corre el
# Protocolo 27, que corresponde a soroban-sdk 27.0.6 y Stellar CLI 27.1.0.
FROM rust:1.98.1-slim-trixie

ARG STELLAR_CLI_VERSION=27.1.0
ARG TARGETARCH

# curl + jq: el script de despliegue fondea cuentas con friendbot y lee la
# interfaz del contrato. libdbus/libudev: dependencias del binario de la CLI.
RUN apt-get update \
 && apt-get install -y --no-install-recommends curl ca-certificates jq libdbus-1-3 libudev1 \
 && rm -rf /var/lib/apt/lists/* \
 && case "${TARGETARCH:-amd64}" in \
      amd64) cli_arch=x86_64-unknown-linux-gnu ;; \
      arm64) cli_arch=aarch64-unknown-linux-gnu ;; \
      *) echo "Arquitectura no soportada: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
 && curl -fsSL "https://github.com/stellar/stellar-cli/releases/download/v${STELLAR_CLI_VERSION}/stellar-cli-${STELLAR_CLI_VERSION}-${cli_arch}.tar.gz" \
    | tar -xz -C /usr/local/bin stellar \
 && stellar --version \
 && rustup target add wasm32v1-none \
 && rustup component add rustfmt clippy \
 # El contenedor corre con el usuario del host (PUID): el caché de crates
 # (volumen) tiene que poder escribirse sin ser root.
 && mkdir -p /usr/local/cargo/registry /usr/local/cargo/git \
 && chmod 1777 /usr/local/cargo/registry /usr/local/cargo/git

WORKDIR /workspace/contracts/sealing
