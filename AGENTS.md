# AGENTS.md

Las instrucciones de este repositorio para agentes de IA viven en [`CLAUDE.md`](CLAUDE.md) — léelo primero. Resumen:

- Framework **AI-First**: `/discovery` (BDD 2.0) → `/plan` → `/test` → `/iterate` → `/audit`. No inventes dominio: sale del caso que aporta el usuario en `sessions/<slug>/caso.md`.
- Stack: Laravel 13 (PHP 8.4) + stancl/tenancy + PostgreSQL 16; Vue 3 + Inertia + Tailwind v4 (Mobile-First) en `resources/js/`.
- Entorno Docker vía `Makefile` (`make setup`, `make up`, `make test`, `make lint`, `make test-front`): el host solo necesita Docker y make. Laravel Boost es opcional y no está instalado.
- TDD puro (Pest / Vitest), formato PSR-12 con Pint, un commit por iteración con tests en verde.
