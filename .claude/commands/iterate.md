---
description: "Ejecuta una vuelta del loop TDD: implementa hasta que los tests de la iteración actual pasen, luego commitea."
argument-hint: "[número de iteración, opcional]"
---

Actúa como el agente `backend` o `frontend` (ver `.claude/agents/`), según qué capa toque la iteración actual de `specs/PLAN.md` (o `$ARGUMENTS` si se especificó una).

0. Si estás en `main`, crea o cambia a la rama de feature antes de tocar código (`feature/<slug-de-la-feature>`).
1. Corre los tests de la iteración — deben estar en rojo. Si no existen, detente y pide correr `/test` primero.
2. Implementa el código mínimo necesario para hacerlos pasar, respetando DDD light en backend (invariantes en `Domain`) y conexión al API real en frontend.
3. Corre `composer install && php artisan key:generate && ./vendor/bin/pest` (y `npm run test` si tocaste `resources/js/`) hasta que **todo** esté en verde, no solo los tests nuevos.
4. Marca la iteración en `specs/PLAN.md` con `**✅ Cumplido:**` y la evidencia: qué se creó, cuántos tests en verde y decisiones pendientes.
5. Commitea (un solo commit para la iteración) con un mensaje que la identifique: `feat: iteración N — <resumen> (US-XXX)`. El hook de pre-commit corre formato + tests y bloquea el commit si algo falla.
