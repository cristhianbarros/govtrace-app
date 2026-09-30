# ---------------------------------------------------------------------------
# Dockerised development environment for govtrace-app.
# Only Docker (with the compose plugin) and make are needed on the host.
# ---------------------------------------------------------------------------
COMPOSE  ?= docker compose --env-file .env.docker
# -u workspace everywhere: "docker compose exec" enters as root by default,
# and that is what leaves vendor/ owned by root on the host.
EXEC     ?= $(COMPOSE) exec -u workspace app
RUN      ?= $(COMPOSE) run --rm --no-deps -u workspace app
NODE     ?= $(COMPOSE) --profile frontend run --rm node
# Rust + Stellar CLI for the sealing Smart Contract (profile stellar, it. 12).
SOROBAN  ?= $(COMPOSE) --profile stellar run --rm -T soroban
LOCAL_IP ?= $(shell sed -n 's/^LOCAL_IP=//p' .env.docker 2>/dev/null | head -1)
HTTP_PORT ?= $(shell sed -n 's/^HTTP_PORT=\([0-9]*\).*/\1/p' .env.docker 2>/dev/null | head -1)

.DEFAULT_GOAL := help

.PHONY: help setup up up-tools up-frontend up-async down stop restart logs ps \
        shell composer artisan migrate psql test test-front test-all lint fmt \
        npm-install npm-build npm-watch xdebug-on xdebug-off hosts image-qa teardown \
        stellar-up contract-test contract-deploy contract-smoke doctor test-stellar \
        contract-extend testnet-setup testnet-extend smoke-testnet secrets-check monitoring-check verify-check e2e \
        backup-now backup-list restore-drill backup-check trace-check network-deploy network-extend network-deploy-check \
        admin invites demo audit staging-check

help: ## List available commands
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n",$$1,$$2}'

# Created only when missing. Re-running make is safe: never overwrites.
.env.docker:
	@cp .env.docker.example .env.docker
	@echo "Created .env.docker from .env.docker.example"
.env:
	@cp .env.example .env
	@echo "Created .env from .env.example"
# Mounted as a file by compose: if missing, Docker would create a DIRECTORY.
docker/app/xdebug.ini:
	@: > docker/app/xdebug.ini

setup: .env.docker .env docker/app/xdebug.ini ## Full bootstrap from scratch (build, deps, key, migrations)
	@mkdir -p .cache/npm
	@$(COMPOSE) build
	@$(RUN) composer install --no-interaction
	@# Primero la app y su base, y las migraciones: el worker y el calendario
	@# usan las tablas de la cola y la caché, y en una base vacía no arrancan.
	@$(COMPOSE) up -d --wait app
	@grep -q '^APP_KEY=base64:' .env || $(EXEC) php artisan key:generate
	@$(EXEC) php artisan migrate --force
	@$(EXEC) php artisan tenants:migrate --force
	@# La DIVIPOLA: sin territorios no se configura ninguna organización.
	@$(EXEC) php artisan db:seed --class=DivipolaSeeder --force
	@$(COMPOSE) up -d --wait
	@$(NODE) npm ci
	@$(NODE) npm run build
	@echo ""
	@echo "  Ready -> http://govtrace.localhost:$(HTTP_PORT)  (health: /up)"
	@echo ""

up: .env.docker docker/app/xdebug.ini ## Start the base services
	@$(COMPOSE) up -d
up-tools: .env.docker docker/app/xdebug.ini ## Also start adminer (adminer.govtrace.localhost)
	@$(COMPOSE) --profile tools up -d
up-frontend: .env.docker docker/app/xdebug.ini ## Also start the node container
	@$(COMPOSE) --profile frontend up -d
up-async: .env.docker docker/app/xdebug.ini ## Also start redis + queue worker
	@$(COMPOSE) --profile async up -d
down: ## Stop and remove containers (data is kept)
	@$(COMPOSE) --profile '*' down
stop: ## Stop without removing
	@$(COMPOSE) stop
restart: ## Restart the services
	@$(COMPOSE) restart
logs: ## Follow logs. Usage: make logs S=app
	@$(COMPOSE) logs -f $(S)
ps: ## Service status
	@$(COMPOSE) ps

shell: ## Shell in the app container as user workspace
	@$(EXEC) bash
composer: ## Composer in the container. Usage: make composer CMD="require x/y"
	@$(EXEC) composer $(CMD)
artisan: ## Artisan in the container. Usage: make artisan CMD="route:list"
	@$(EXEC) php artisan $(CMD)
migrate: ## Central + tenant migrations
	@$(EXEC) php artisan migrate
	@$(EXEC) php artisan tenants:migrate
psql: ## psql console on the central database
	@$(COMPOSE) exec pgsql sh -c 'psql -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'

test: ## Backend tests (Pest)
	@$(EXEC) ./vendor/bin/pest $(ARGS)
test-stellar: ## Sealing tests against the local Stellar network (needs make stellar-up && make contract-deploy)
	@$(EXEC) ./vendor/bin/pest --group=stellar $(ARGS)
verify-check: ## Independent verifier (tools/verify) on a published evidence, isolated with the Stellar node alone
	@bash tests/infra/check-verify.sh
e2e: .env.docker ## End-to-end in a real Chromium against make up: each role's journey, gaps as fixme (it. 40a), and US-018 offline (needs make up and make npm-build)
	@bash tests/infra/run-e2e.sh
ux-check: .env.docker ## UX checkpoint (it. 40a): every screen of every role, phone and computer, with screenshots, axe (WCAG 2.2 AA) and text/target sizes, against tests/ux/baseline.json
	@bash tests/infra/run-ux-check.sh
ux-baseline: .env.docker ## Rewrite tests/ux/baseline.json after a UX improvement (review its diff)
	@UPDATE_BASELINE=1 bash tests/infra/run-ux-check.sh
admin: .env.docker ## The first Super Administrador of a new environment. Usage: make admin EMAIL=ana@x.co [NAME="Ana"] (asks for the password, or generates one)
	@test -n "$(EMAIL)" || { echo "Uso: make admin EMAIL=ana@correo.co [NAME=\"Ana Directora\"]"; exit 1; }
	@$(EXEC) php artisan admin:create "$(EMAIL)" $(if $(NAME),--name="$(NAME)")
invites: .env.docker ## The links of the latest mails (mail goes to a log in development): invitations, password recovery
	@$(EXEC) php artisan invitations:latest $(if $(LIMIT),--limit=$(LIMIT))
demo: .env.docker .env docker/app/xdebug.ini ## ONE command for a live demo: the app, the Stellar network and a demo organization with sealed reports (LUGAR="lat,lng": an example worksite where the presentation is; TERRITORIO=medellin: the Comuna 13)
	@LUGAR="$(LUGAR)" TERRITORIO="$(TERRITORIO)" bash docker/demo/demo.sh
test-front: .env.docker ## Frontend tests (Vitest)
	@$(NODE) npm run test
test-all: lint test test-front ## Pint + Pest + Vitest (same as CI)
lint: ## Check style with Pint (does not modify)
	@$(EXEC) ./vendor/bin/pint --test
fmt: ## Fix style with Pint
	@$(EXEC) ./vendor/bin/pint

stellar-up: .env.docker ## Start the local Stellar standalone network (RPC + friendbot)
	@$(COMPOSE) --profile stellar up -d --wait stellar
contract-test: .env.docker ## Smart Contract: rustfmt, clippy, cargo test and the compiled WASM's interface
	@$(SOROBAN) sh -c 'cargo fmt --check && cargo clippy --locked --all-targets -- -D warnings && cargo test --locked && stellar contract build && ./scripts/check-interface.sh'
contract-deploy: .env.docker .env stellar-up ## Deploy the sealing contract to the local network; writes its ID to .env
	@$(SOROBAN) ./scripts/deploy-local.sh
contract-smoke: .env.docker stellar-up ## Seal, reject an outsider and a duplicate, on the local network
	@$(SOROBAN) ./scripts/smoke-local.sh
contract-extend: .env.docker stellar-up ## D12: the treasury extends the local contract's instance and code to the network's max TTL
	@$(SOROBAN) ./scripts/extend-contract.sh local
testnet-setup: .env.docker ## Testnet: funded test accounts + the contract deployed; writes .env.testnet (never versioned)
	@$(SOROBAN) ./scripts/deploy-testnet.sh
testnet-extend: .env.docker ## D12: the treasury extends the testnet contract's instance and code (run before they expire)
	@$(SOROBAN) ./scripts/extend-contract.sh testnet
network-deploy: .env.docker ## Deploy on NETWORK=testnet|mainnet: the funded treasury (STELLAR_TREASURY_SECRET) creates the accounts, deploys and extends (D12, D13)
	@$(SOROBAN) env STELLAR_TREASURY_SECRET="$$STELLAR_TREASURY_SECRET" STELLAR_SEALER_ADDRESS="$$STELLAR_SEALER_ADDRESS" STELLAR_SPONSOR_ADDRESS="$$STELLAR_SPONSOR_ADDRESS" STELLAR_MAINNET_RPC_URL="$$STELLAR_MAINNET_RPC_URL" CONFIRM_MAINNET="$$CONFIRM_MAINNET" SPONSOR_STARTING_XLM="$${SPONSOR_STARTING_XLM:-100}" ./scripts/deploy-network.sh $(NETWORK)
network-extend: .env.docker ## D12 on NETWORK=testnet|mainnet: the treasury (STELLAR_TREASURY_SECRET) extends STELLAR_SEALING_CONTRACT_ID's instance and code
	@$(SOROBAN) env STELLAR_TREASURY_SECRET="$$STELLAR_TREASURY_SECRET" STELLAR_SEALING_CONTRACT_ID="$$STELLAR_SEALING_CONTRACT_ID" STELLAR_MAINNET_RPC_URL="$$STELLAR_MAINNET_RPC_URL" ./scripts/extend-contract.sh $(NETWORK)
network-deploy-check: .env.docker ## It. 37a: the main-network deploy, end to end on testnet (throwaway accounts and contract, a report up to "Sellada")
	@bash tests/infra/check-network-deploy.sh
smoke-testnet: ## Smoke test on the Stellar testnet: a report up to "Sellada", fee paid by the sponsor (needs .env.testnet)
	@test -f .env.testnet || { echo "Falta .env.testnet: corre make testnet-setup (en CI lo escribe Jenkins)."; exit 1; }
	@$(EXEC) sh -c 'set -a; . ./.env.testnet; set +a; ./vendor/bin/pest --group=testnet'
audit: .env.docker ## It. 41: known vulnerabilities in the dependencies (composer audit, and npm audit of production at high or above)
	@$(EXEC) composer audit --no-interaction
	@$(NODE) npm audit --omit=dev --audit-level=high
staging-check: .env.docker ## It. 42a: the production stack (docker-compose.prod.yml) end to end, locally, with TLS from a test CA
	@bash tests/infra/check-staging.sh
trace-check: ## Traceability: every Gherkin scenario has a test named after it (CLAUDE.md, R-TST-04)
	@$(EXEC) php tests/infra/check-traceability.php
secrets-check: ## R-BLK-04: no Stellar secret key in the repository, its history or the env examples
	@bash tests/infra/check-secrets.sh
monitoring-check: ## US-044-MON: the external monitor (Gatus) alerts by email and webhook after >5 min down, not for short blips
	@bash tests/infra/check-monitoring.sh

backup-now: ## R-BCK: take a backup now (the backup service also takes one every hour)
	@$(COMPOSE) exec -T backup backup.sh
backup-list: ## R-BCK: the backups kept (30 days) and what each one holds
	@$(COMPOSE) exec -T backup sh -c 'for d in $$(ls -1d /backups/2*Z 2>/dev/null); do echo "$$(basename $$d)  $$(cat $$d/manifest.json)"; done'
restore-drill: ## R-BCK-05: restore the latest backup into an empty database and a test bucket, and verify every evidence file
	@bash tests/infra/restore-drill.sh
backup-check: ## R-BCK: backups link unchanged files, expire at 30 days, and the restore drill catches a bad file or a missing database
	@bash tests/infra/check-backup.sh

npm-install: .env.docker ## Install frontend dependencies
	@mkdir -p .cache/npm
	@$(NODE) npm ci
npm-build: .env.docker ## Build the frontend once
	@$(NODE) npm run build
npm-watch: .env.docker ## Build the frontend and keep watching
	@$(NODE) npm run build -- --watch

xdebug-on: ## Enable Xdebug and restart app
	@cp docker/app/xdebug.ini.disabled docker/app/xdebug.ini
	@$(COMPOSE) restart app
	@echo "Xdebug ENABLED (IDE must listen on port 9003)"
xdebug-off: ## Disable Xdebug and restart app
	@: > docker/app/xdebug.ini
	@$(COMPOSE) restart app
	@echo "Xdebug disabled"

doctor: .env.docker ## Diagnose networking (VPN vs Docker subnets, internet from containers, GitHub)
	@bash tests/infra/check-network.sh

hosts: ## Print the /etc/hosts block (only needed if LOCAL_IP is not 127.0.0.1)
	@echo "Add these lines to /etc/hosts with sudo:"
	@echo ""
	@echo "$(LOCAL_IP)  govtrace.localhost"
	@echo "$(LOCAL_IP)  adminer.govtrace.localhost"

image-qa: ## Build the immutable qa image (govtrace-app:qa)
	@docker build -f docker/app/Dockerfile --target qa -t govtrace-app:qa .

teardown: ## DESTRUCTIVE: remove containers AND volumes (database)
	@printf "DESTRUCTIVE: volumes (database) will be deleted. Type 'yes': " \
	  && read r && [ "$$r" = "yes" ] || exit 1
	@$(COMPOSE) --profile '*' down -v
