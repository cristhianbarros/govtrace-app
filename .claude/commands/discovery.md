---
description: "Arranca (o reanuda) la entrevista de discovery BDD 2.0: convierte el caso que aportas en tu spec, una pregunta a la vez, a través del skill bdd-discovery."
argument-hint: "[resume — opcional, para retomar desde el punto guardado]"
---

Invoca el skill **`bdd-discovery`** (`.claude/skills/bdd-discovery/SKILL.md`) para conducir el discovery sobre el caso que aporta el usuario.

- Sin argumentos → si aún no hay sesión, pregunta el nombre del usuario y su caso de negocio y crea `sessions/<slug>/`; si ya existe, lee `sessions/<slug>/SHARED-MEMORY.md` y reanuda según `current_phase`.
- Con `$ARGUMENTS` = `resume` → fuerza la reanudación desde el punto exacto guardado.

Contrato: es una **entrevista** por las 5 etapas (Épicas → Historias → Criterios → Completitud → Gherkin), una pregunta a la vez. No generes la spec de una sola pasada. No asumas el dominio: trabaja sobre el caso que aporta el usuario.
