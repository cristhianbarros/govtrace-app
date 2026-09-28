---
description: "Genera tests desde los escenarios Gherkin de la iteración actual — tests primero, antes del código."
argument-hint: "[número de iteración, opcional — por defecto la siguiente pendiente en specs/PLAN.md]"
---

Actúa como el agente `calidad` (ver `.claude/agents/calidad.md`).

Identifica la iteración actual en `specs/PLAN.md` (o usa `$ARGUMENTS` si se especificó una). Encuentra los escenarios Gherkin relevantes en `features/` para esa iteración y genera los tests correspondientes:

- Backend → `tests/`, un test (Pest PHP + Laravel Testing (RefreshDatabase, Tenancy Traits)) por `Scenario`, nombrado por el escenario.
- Frontend → junto al componente en `resources/js/Pages/`, un test (Vitest + Vue Test Utils) por `Scenario` relevante a esa pantalla.

Corre los tests y muéstrame que fallan (rojo) contra el código actual. Si algún test ya pasa sin implementación nueva, revisa si el escenario está mal traducido o si esa parte ya existía, y dilo. No implementes el código que los hace pasar — eso es `/iterate`.
