---
description: "Auditoría de implementación: compara el código actual contra specs/SPEC.md y reporta cobertura funcional y técnica."
---

Actúa como el agente `calidad` (ver `.claude/agents/calidad.md`) y produce un reporte de auditoría:

**Cobertura funcional** — recorre cada regla/criterio de `specs/SPEC.md` y confirma si hay código + test que la valide (incluido su escenario negativo). Sé específico por regla.

**Cobertura técnica:**
- `./vendor/bin/pest` en verde (con conteo)
- `npm run test` en verde
- `make up` levanta todos los servicios sin error (todos en `healthy` según `make ps`)
- Historial de commits (un commit identificable por iteración)
- Stubs, fakes o `TODO` en código de producción

**Reporte final:** lista de gaps concretos, clasificados por severidad, y una recomendación honesta de si esto está listo para PR. Propón cerrar los gaps significativos como iteraciones nuevas en `specs/PLAN.md` y los que no bloquean ningún criterio como **Deuda técnica aceptada**.
