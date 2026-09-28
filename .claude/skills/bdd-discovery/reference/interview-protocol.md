# Protocolo de Entrevista — BDD 2.0

Reglas de interacción que **todos** los skills de discovery siguen. Autocontenido.

## Regla fundamental: una pregunta a la vez
NINGÚN skill avanza sin completar este ciclo por cada pregunta:
1. **Preguntar** una sola cosa, con el formato universal.
2. **Esperar** la respuesta del usuario — espera bloqueante.
3. **Validar**: resume lo que entendiste y pide confirmación explícita.
4. **Guardar** la respuesta en `sessions/<slug>/SHARED-MEMORY.md`.
5. Solo entonces, pasar a la siguiente pregunta.

## Taxonomía de tipos de pregunta
- 📊 **Opción (el default)** — 3-5 opciones etiquetadas A/B/C/D generadas a partir del caso, **+ siempre** una línea `✍️ Otra: si ninguna encaja, descríbela con tus palabras.`
- 🔍 **Abierta** — da 2-3 ejemplos (`💡 por ejemplo: …`) y deja la respuesta libre. Añade `❓ ¿Esta pregunta está clara? 🤔`.
- 📋 **Cerrada** — sí/no que abre a un seguimiento + `✍️ Otra`.
- 🏆 **Ranking** — priorizar una lista.

## Formato de pregunta universal

```
🗂️ [FASE ACTUAL] ===
❓ PREGUNTA [X] DE [Y] — TIPO: [emoji] [TIPO]

[emoji de dominio] [la pregunta, concreta]

A) [opción candidata plausible]
B) [opción candidata plausible]
C) [opción candidata plausible]
✍️ Otra: si ninguna encaja, descríbela con tus palabras.

⏳ Esperando tu respuesta...
```

- **Paralelismo (clave para no sesgar):** las opciones deben tener **largo y nivel de detalle parejos**, y **ninguna debe venir "justificada"**.

## Validación tras cada respuesta

```
💡 Perfecto, déjame confirmar que entendí:
📋 [resumen de la respuesta en 1-2 líneas]
❓ ¿Lo interpreté bien?
⏳ [esperar confirmación antes de seguir]
```

## Checkpoint de transición (antes de pasar de fase)

```
🔍 CHECKPOINT DE [FASE ACTUAL]
📊 COMPLETITUD VALIDADA:
✅ [lista de lo logrado]
🚀 HANDOFF → [próxima fase]
❓ ¿Todo correcto antes de continuar?
```

## Modo de descubrimiento: `asesor`
Las reglas de negocio las decide el usuario. Nunca las inventes.
Como el modo es `asesor`, **después** de que el usuario respondió y confirmaste su respuesta, puedes añadir como máximo una nota breve `💡 Recomendación:` cuando veas un riesgo concreto (seguridad, integridad, operación) o una práctica estándar que su respuesta contradice. La nota explica el riesgo en una frase y pregunta si quiere mantener su decisión o ajustarla. La decisión final sigue siendo de él.
