---
description: "Genera specs/PLAN.md: iteraciones numeradas con Done-when, a partir de specs/SPEC.md y features/*.feature."
---

La planificación es transversal, así que este comando no delega en un agente específico — lo ejecutas directamente.

Lee `specs/SPEC.md` y todos los `features/*.feature` generados por `/discovery`. Genera `specs/PLAN.md` usando `specs/PLAN.template.md` como formato:

- **Trazabilidad primero:** si una historia del `SPEC.md` no tiene ningún escenario Gherkin, señálalo antes de planificar.
- Divide el trabajo en iteraciones pequeñas y verificables — cada una con **Entregable**, **Done-when concreto** ("tests de X pasando", no "backend listo") y **Cubre** (qué historias / reglas).
- **La primera iteración puede ser la infraestructura:** `make up` levanta todos los servicios y `http://govtrace.localhost:8080/up` responde ok.
- Orden: backend primero (datos de referencia/semilla → modelo e invariantes → casos de uso y endpoints → autorización → auditoría/reportes), frontend después (la pantalla más central primero).
- Cada iteración termina en un commit (el hook de pre-commit exige tests en verde).
