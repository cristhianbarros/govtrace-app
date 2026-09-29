#!/usr/bin/env bash
# PreToolUse hook sobre Bash: antes de un `git commit`, revisa solo lo que
# cambió respecto del último commit (modificado, nuevo o borrado):
#   - Pint, sobre los archivos PHP que cambiaron;
#   - Pest, los tests que cambiaron y los que nombran una clase que cambió;
#   - Vitest, los tests relacionados con lo que cambió en resources/js/ o tools/;
#   - el Smart Contract, si cambió algo en contracts/.
# La suite completa no corre aquí: la corren `make test-all` y el pipeline
# (Jenkins) en cada PR. Si algo falla, el commit se bloquea.
set -euo pipefail

INPUT=$(cat)

# Solo un `git commit` de verdad, no un comando que apenas lo menciona (un grep, un pkill…).
if command -v python3 >/dev/null 2>&1; then
  is_commit=$(printf '%s' "$INPUT" | python3 -c '
import json, re, sys
command = json.load(sys.stdin).get("tool_input", {}).get("command", "")
print(1 if re.search(r"(^|[;&|(\n])\s*(\w+=\S*\s+)*git\s+commit\b", command) else 0)
' 2>/dev/null || echo 0)
else
  is_commit=$(printf '%s' "$INPUT" | grep -qE '"command"[[:space:]]*:[[:space:]]*"[^"]*git[[:space:]]+commit' && echo 1 || echo 0)
fi
[ "$is_commit" = 1 ] || exit 0

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
cd "$REPO_ROOT"

# PHP del host si existe; si no, el contenedor `app` del docker-compose (make up).
if command -v php >/dev/null 2>&1; then
  PHP_RUN=()
else
  PHP_RUN=(docker compose --env-file .env.docker exec -T -u workspace app)
fi

# Lo que cambió: el hook corre antes del comando, así que un `git add -A && git commit`
# todavía no preparó nada. Por eso, el árbol de trabajo entero contra el último commit.
mapfile -t CHANGED < <(git status --porcelain --untracked-files=all | sed -E 's/^.{3}//; s/^.* -> //; s/^"(.*)"$/\1/')
if [ ${#CHANGED[@]} -eq 0 ]; then
  exit 0
fi

PHP_FILES=()
TESTS=()
JS_FILES=()
CONTRACT_CHANGED=0
for file in "${CHANGED[@]}"; do
  case "$file" in
    *.php) [ -f "$file" ] && PHP_FILES+=("$file") ;;
  esac
  case "$file" in
    tests/*Test.php) [ -f "$file" ] && TESTS+=("$file") ;;
  esac
  # Una clase que cambió (o se borró): los tests que la nombran.
  case "$file" in
    app/*.php | database/seeders/*.php | database/factories/*.php | tests/Support/*.php)
      class=$(basename "$file" .php)
      while IFS= read -r test; do TESTS+=("$test"); done < <(grep -rlw --include='*Test.php' -- "$class" tests || true)
      ;;
  esac
  case "$file" in
    resources/js/* | tools/*) [ -f "$file" ] && JS_FILES+=("$file") ;;
    contracts/*) CONTRACT_CHANGED=1 ;;
  esac
done

echo "[pre-commit-check] Revisando solo lo que cambió (${#CHANGED[@]} archivos)..." >&2

if [ ${#PHP_FILES[@]} -gt 0 ]; then
  if ! "${PHP_RUN[@]}" ./vendor/bin/pint --test "${PHP_FILES[@]}" >&2; then
    echo "[pre-commit-check] BLOQUEADO: hay archivos sin formatear. Corre 'make fmt' y vuelve a intentar." >&2
    exit 2
  fi
fi

if [ ${#TESTS[@]} -gt 0 ]; then
  mapfile -t TESTS < <(printf '%s\n' "${TESTS[@]}" | sort -u)
  echo "[pre-commit-check] Pest: ${#TESTS[@]} archivos de test relacionados." >&2
  # --group=… no hace falta: los grupos stellar y testnet siguen excluidos (phpunit.xml).
  if ! "${PHP_RUN[@]}" ./vendor/bin/pest "${TESTS[@]}" >&2; then
    echo "[pre-commit-check] BLOQUEADO: hay tests de backend fallando." >&2
    exit 2
  fi
fi

if [ ${#JS_FILES[@]} -gt 0 ]; then
  # En el contenedor node (como make test-front): los node_modules los instala
  # node:22-alpine, y sus binarios nativos no tienen por qué correr en el host.
  if ! docker compose --env-file .env.docker --profile frontend run --rm -T node npx vitest related --run --passWithNoTests "${JS_FILES[@]}" >&2; then
    echo "[pre-commit-check] BLOQUEADO: hay tests de frontend fallando." >&2
    exit 2
  fi
fi

# Smart Contract (it. 12): corre en el contenedor de Rust + Stellar CLI.
if [ "$CONTRACT_CHANGED" = 1 ]; then
  if ! make contract-test >&2; then
    echo "[pre-commit-check] BLOQUEADO: el Smart Contract no pasa (rustfmt, clippy, cargo test o su interfaz)." >&2
    exit 2
  fi
fi

echo "[pre-commit-check] OK — lo que cambió está en verde; la suite completa, en make test-all y el pipeline." >&2
exit 0
