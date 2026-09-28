---
name: bdd-gherkin
description: "Fase 5 (final) del discovery BDD 2.0: genera automáticamente features/*.feature (Given-When-Then) desde los criterios YAML validados, y cierra con el reporte DQS-lite de cobertura."
---

# Fase 🔧 Gherkin + DQS-lite

A diferencia de las fases anteriores, esta **no entrevista** — transforma. Tu insumo son los criterios YAML ya validados (`specs/criterios/US-XXX.yaml`) y las reglas descubiertas en Completitud.

> **Este es el entregable final del discovery.** Los `features/*.feature` son la **especificación ejecutable** del módulo, y el artefacto que arranca el desarrollo: de estos escenarios salen los tests del TDD (`/test`).

## Paso 1 — Generar Gherkin
Por cada historia, genera `features/US-XXX.feature` (o agrupa por área si tiene sentido) siguiendo el formato de `features/ejemplo_formato.feature` (`# language: es`):

- Tags obligatorios: `@story_id:US-XXX`, `@origin:discovery_inicial` o `@origin:analisis_completitud`, `@priority:N` a nivel de feature; `@complexity:low|medium|high` por escenario; `@negative` en los escenarios que violan una regla y `@edge` en los casos límite.
- Un `Scenario` por criterio de éxito, y **un `Scenario` negativo por cada regla/validación** (el que la viola a propósito). Un `.feature` que solo tiene caminos felices está incompleto.
- Pasos en lenguaje de negocio y con valores concretos (mensajes, cantidades).

Una vez generados los `.feature` reales, borra `features/ejemplo_formato.feature`.

## Paso 2 — Reporte DQS-lite (auditoría del discovery)
Escribe el reporte en `sessions/<slug>/dqs-lite.md`. En prosa, cubre:
- **Cobertura por área de completitud:** para cada una de las 10 áreas, ¿quedó explorada, con gaps, o floja?
- **Balance camino-feliz / camino-negativo:** ¿cada regla tiene su escenario que la viola?

**No pongas un número objetivo** tipo "cubriste X de N reglas".

## Cierre del discovery
1. Consolida `specs/SPEC.md` a partir de `specs/SPEC.template.md`: historias + reglas descubiertas + endpoints/pantallas esbozados + referencia a `specs/criterios/`.
2. Marca `gherkin_phase.ready_for_handoff: true` y `project_state.current_phase: completed` en `SHARED-MEMORY.md`.
3. Avisa que el discovery está completo, que la sesión quedó documentada en `sessions/<slug>/`, y que el siguiente paso es `/plan`.
