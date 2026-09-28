#!/usr/bin/env bash
# PreToolUse hook sobre Bash: si el comando es un `git commit`, corre formato+tests antes de dejarlo pasar.
set -euo pipefail

INPUT=$(cat)

if ! printf '%s' "$INPUT" | grep -qE '"command"[[:space:]]*:[[:space:]]*"[^"]*git[[:space:]]+commit'; then
  exit 0
fi

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
cd "$REPO_ROOT"

FRONTEND_DIR="resources/js"
CONTRACTS_DIR="contracts"

# PHP del host si existe; si no, el contenedor `app` del docker-compose (make up).
if command -v php >/dev/null 2>&1; then
  PHP_RUN=()
else
  PHP_RUN=(docker compose --env-file .env.docker exec -T -u workspace app)
fi

echo "[pre-commit-check] Verificando formato y tests antes del commit..." >&2

if ! "${PHP_RUN[@]}" ./vendor/bin/pint --test >&2; then
  echo "[pre-commit-check] BLOQUEADO: hay archivos sin formatear. Corre './vendor/bin/pint' y vuelve a intentar." >&2
  exit 2
fi

if ! "${PHP_RUN[@]}" ./vendor/bin/pest >&2; then
  echo "[pre-commit-check] BLOQUEADO: hay tests de backend fallando." >&2
  exit 2
fi

if [ -n "$FRONTEND_DIR" ] && [ -d "$FRONTEND_DIR" ] && [ -n "$(git status --porcelain -- "$FRONTEND_DIR")" ]; then
  # En el contenedor node (make test-front): los node_modules los instala
  # node:22-alpine, y sus binarios nativos no tienen por qué correr en el host.
  if ! make test-front >&2; then
    echo "[pre-commit-check] BLOQUEADO: hay tests de frontend fallando." >&2
    exit 2
  fi
fi

# Smart Contract (it. 12): solo si cambió algo en contracts/. Corre en el
# contenedor de Rust + Stellar CLI; el host solo necesita Docker y make.
if [ -d "$CONTRACTS_DIR" ] && [ -n "$(git status --porcelain -- "$CONTRACTS_DIR")" ]; then
  if ! make contract-test >&2; then
    echo "[pre-commit-check] BLOQUEADO: el Smart Contract no pasa (rustfmt, clippy, cargo test o su interfaz)." >&2
    exit 2
  fi
fi

echo "[pre-commit-check] OK — tests en verde, commit permitido." >&2
exit 0
