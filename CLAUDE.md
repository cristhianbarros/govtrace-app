# CLAUDE.md

Instrucciones de proyecto para Claude Code en `govtrace-app`. Plataforma Open Source y Mobile-First para la recolección inmutable de evidencia ciudadana.

Este repo trabaja con el framework **AI-First**: partiendo de un **caso de negocio que aporta el usuario**, se construye el módulo ejecutando el flujo completo **discovery (BDD 2.0, 5 etapas) → auditoría del discovery → looping engineering (TDD) → auditoría de implementación**.

**No asumas ni inventes un dominio.** El caso llega del usuario (un documento que carga en el chat o una descripción al iniciar `/discovery`) y queda guardado en `sessions/<slug>/caso.md`. Trabaja siempre sobre ese caso. Es deliberadamente incompleto: descubrir las validaciones, edge cases y reglas que faltan es parte del trabajo, no adivinarlas de una sola leída.

## El loop

```
/discovery → entrevista BDD 2.0, 5 etapas, una pregunta a la vez → specs/ y features/
/plan      → specs/PLAN.md (iteraciones con "Done-when")
/test      → tests desde los Gherkin (tests primero)
/iterate   → implementa hasta que pasen los tests de la iteración, commitea
/audit     → cobertura funcional y técnica: el código vs la spec propia
```

`/discovery` (o su alias `/spec`) **no genera la spec de una pasada** — entrevista por las 5 etapas de BDD 2.0. Al arrancar pregunta el nombre del usuario y crea `sessions/<slug>/`, donde documenta la sesión y guarda el estado (`SHARED-MEMORY.md`) para reanudar con `/discovery resume`. La mecánica vive en `.claude/skills/bdd-discovery/`.

Repite `/test` → `/iterate` por cada iteración del plan. El commit de cada iteración pasa por un hook de pre-commit (`.claude/hooks/pre-commit-check.sh`) que revisa **solo lo que cambió** respecto del último commit: Pint sobre los PHP modificados; Pest, los tests que cambiaron y los que nombran una clase modificada; Vitest, los tests relacionados con lo modificado en `resources/js/` o `tools/`; y el contrato si cambió `contracts/`. Si algo falla, el commit se bloquea. La suite completa no corre en el hook (tarda ~16 min): córrela con `make test-all` antes de fusionar una iteración; el pipeline (GitHub Actions, `.github/workflows/ci.yml`, it. 49) la corre en partes y en paralelo en cada PR, y `main` no acepta un PR con "CI completo" en rojo.

## Stack y comandos

| Qué | Valor |
|---|---|
| Backend | Laravel 13.x (PHP 8.4) + Tenancy for Laravel (Stancl) + PostgreSQL — DDD adaptado a Laravel (app/Domain, app/Application, app/Infrastructure). Multi-tenant por base de datos o esquema. |
| Frontend | Vue 3 + Inertia.js + Tailwind CSS v4 (Strict Mobile-First) en `resources/js/` |
| Blockchain | **Stellar**, Smart Contract en **Soroban (Rust)** en `contracts/sealing/` (pivote del 2026-09-28, ver `specs/PLAN.md`) |
| Tests del contrato | `cargo test` con el entorno de pruebas de Soroban → `make contract-test` (además rustfmt, clippy y la interfaz exacta del WASM) |
| Build / test / formato | `composer install && php artisan key:generate` · `./vendor/bin/pest` · `npm run test` · `./vendor/bin/pint` |
| Levantar todo | `make up` → `http://govtrace.localhost:8080/up` responde ok (primera vez: `make setup`) |

### Entorno Docker (Makefile + docker-compose)

- En el host solo hacen falta Docker y `make`; PHP 8.4, Composer y Node corren en contenedores. `make help` lista todo.
- Servicios base: `app` (PHP 8.4 + Apache, **sin puerto publicado**), `proxy` (nginx, única entrada: `LOCAL_IP:HTTP_PORT`), `scheduler`, `worker` (it. 64: los correos salen por la cola `mail`, que atiende primero; en producción, su propio `mail-worker`), `pgsql` (PostgreSQL 16), `storage` (S3 de desarrollo con versitygw, que guarda las evidencias en disco: it. 45d) y `backup` (una copia de todas las bases y de los archivos de evidencia al arrancar y cada hora, 30 días). Perfiles opcionales: `tools` (adminer), `frontend` (node), `async` (redis + worker), `stellar` (red local *standalone* de Stellar con RPC y friendbot, y el contenedor `soroban` con Rust + Stellar CLI).
- Los comandos del marco se traducen así: `./vendor/bin/pest` → `make test`, `./vendor/bin/pint --test` → `make lint`, `./vendor/bin/pint` → `make fmt`, `npm run test` → `make test-front`, migraciones → `make migrate`, `composer …` → `make composer CMD="…"`, `php artisan …` → `make artisan CMD="…"`.
- Si el host no tiene PHP, el hook de pre-commit ejecuta Pint y Pest dentro del contenedor `app` (requiere `make up`). Si cambió algo en `contracts/`, además corre `make contract-test`.
- Smart Contract y sellado (explicado para quien no conoce blockchain en `docs/sellado-en-stellar.md`): `make stellar-up` (red local), `make contract-test`, `make test-stellar` (tests de Laravel contra la red local, grupo `stellar`, excluidos de `make test`), `make contract-deploy` (despliega en la red local y deja su ID en `.env`), `make contract-smoke` (sella, rechaza a una cuenta externa y un duplicado, con transacciones reales), `make verify-check` (el verificador independiente `tools/verify` comprueba una evidencia publicada, aislado con el nodo de Stellar solo).
- Sellado por turnos (it. 39): Stellar admite una sola transacción pendiente por cuenta, así que la selladora sella una por ledger. El turno vive en la base central (`sealer_turns`, `App\Infrastructure\Stellar\SealerTurn`), el mismo para todas las organizaciones y workers; un sello que lo encuentra tomado vuelve en 3 s sin gastar intentos. No uses candados de la caché para algo global: dentro de una organización, la caché lleva su prefijo.
- Extremo a extremo (R-TST-03): `make e2e` corre Playwright en un Chromium de verdad contra la app de `make up` (requiere `make npm-build`); prepara su organización `veeduria-e2e` en la base de desarrollo (`tests/e2e/fixture.php`: Super Administrador, Administradora, veedor y reportes sellados en la red en memoria). Incluye el flujo de cada rol de punta a punta (`tests/e2e/flujos/`, it. 40a): cada vacío de `docs/mapa-funcional.md` es un `test.fixme('V…')` hasta que una iteración lo cierra. `make faces-bench` (it. 48) mide cuánto tarda la revisión de rostros de una foto, con la CPU frenada como en un celular de gama media. `make photo-memory` (it. 62b) mide la memoria que toma adjuntar una foto de 12 y de 50 megapíxeles: el pico y lo que queda tomado. `make photo-time` (it. 62f) mide cuánto tarda "Tomar la foto" hasta la revisión y "Usar esta foto" hasta quedar adjunta.
- Checkpoint de la interfaz (it. 40a): `make ux-check` recorre cada pantalla de cada rol, en celular y escritorio, guarda capturas (`storage/framework/testing/ux/shots/`) y falla si una empeora respecto de `tests/ux/baseline.json` (axe WCAG 2.2 AA, tamaño de letra y de botones). Tras una mejora de UX, `make ux-baseline` reescribe la línea base y el diff se revisa en el PR.
- Operación fuera de lo local —producción y staging (it. 42a, 42b), red principal (it. 37a), demostración en vivo (it. 38), respaldos (it. 35) y testnet de Stellar—: en el skill `operar-entornos` (`.claude/skills/operar-entornos/SKILL.md`).
- Dependencias (it. 41): `make audit` busca vulnerabilidades conocidas (`composer audit` y `npm audit` de producción, nivel alto o más); corre en el pipeline.
- Trazabilidad: `make trace-check` comprueba que cada escenario de `features/` tiene un test con su nombre (corre en el pipeline).
- Con una VPN activa que publique rutas dentro de `172.17.0.0/16`, los `RUN` de `docker build` se quedan sin DNS ni internet: entonces los builds usan la red del host (`DOCKER_BUILD_NETWORK=host` en `.env.docker`; por defecto, `default`). Docker Compose v5 construye con *bake*, que pide permiso para la red del host: `make` se lo da (it. 49), así que construye con `make setup` o `make soroban-image`, no con `docker compose build`. Si algo de red falla con la VPN, `make doctor` dice qué: subred del proyecto, red de los builds, salida a internet de los contenedores e IPs de GitHub que la VPN desvía a un túnel que no responde.
- Configuración: `.env.docker` (variables de Docker, no versionado, se crea desde `.env.docker.example`) y `.env` (Laravel). Las credenciales `DB_*` deben coincidir en ambos.
- Tenancy por dominio: el dominio central es `govtrace.localhost` (`TENANCY_CENTRAL_DOMAINS`); cada tenant vive en `<tenant>.govtrace.localhost`.
- Laravel Boost (`laravel/boost`) es opcional y no está instalado; si se instala, sus guías complementan este archivo, no lo reemplazan.

## Estructura del repo

- Backend — DDD adaptado a Laravel (app/Domain, app/Application, app/Infrastructure). Multi-tenant por base de datos o esquema. Las entidades, invariantes y reglas se construyen a partir de la spec, no antes. El modelo `Tenant` (plomería de `stancl/tenancy`) vive en `app/Infrastructure/Tenancy/`; las migraciones por tenant en `database/migrations/tenant/`.
- `sessions/` — documentación de cada sesión de discovery: `sessions/<slug>/` con `caso.md`, `SHARED-MEMORY.md` (estado), `project-context.md`, `discovery-log.md` (bitácora) y `dqs-lite.md`.
- `features/` — Gherkin. `ejemplo_formato.feature` es solo referencia de formato.

## Convenciones y Arquitectura

- Reglas de negocio, criterios y specs en español; código, nombres de clases/variables en inglés.
- Los criterios de aceptación se escriben primero en YAML y el **Gherkin se genera después, en la etapa 5**. El `.feature` **sí** es el entregable final del discovery.
- **Trazabilidad:** toda historia tiene su `.feature`; todo `Scenario` tiene un test nombrado por el escenario; toda iteración de `PLAN.md` dice qué historias cubre.
- **TDD puro:** en cada iteración el test se escribe y se ve fallar **antes** de implementar.
- **GitFlow puro:** una rama por feature desde `main`, **un commit por iteración** con tests en verde. Mensajes: `feat: iteración N — <resumen> (US-XXX)`.
- **ADN Open Source:** Este proyecto es de código abierto. El código debe ser hiper-legible, documentado donde sea estrictamente necesario (KISS) y preparado para que contribuyentes externos puedan clonar, levantar Docker (`make setup`) y correr la suite de TDD/BDD sin configuraciones arcanas. Todo PR pasa por el pipeline de GitHub Actions; el `Jenkinsfile` llama a los mismos `make`, para quien use Jenkins.
- **Modo de descubrimiento activo: `asesor`**.
