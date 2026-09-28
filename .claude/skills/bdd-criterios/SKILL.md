---
name: bdd-criterios
description: "Fase 3 (Criterios) del discovery BDD 2.0: entrevista al usuario para escribir criterios de aceptación SMART en YAML por cada historia."
---

# Fase ⚡ Criterios — Entrevista

Sigue el protocolo en `../bdd-discovery/reference/interview-protocol.md`.

## Objetivo
Por cada historia de la fase anterior, escribir junto con el usuario los criterios de aceptación en **YAML** (lenguaje de negocio; el Gherkin viene después). Trabaja **historia por historia**.

## Formato de salida (un archivo por historia)
`specs/criterios/US-XXX.yaml`:

```yaml
story_id: "US-XXX"
titulo_historia: "Como [rol] quiero [acción] para [beneficio]"
criterios_aceptacion:
  escenarios_exito: []      # camino feliz
  validaciones_reglas: []   # reglas de negocio y validaciones de campo
  manejo_errores: []        # qué pasa cuando algo sale mal, con el mensaje esperado
  requisitos_ux: []         # comportamiento visible relevante
  casos_edge: []            # situaciones límite
validacion_smart:
  specific: false
  measurable: false
  achievable: false
  relevant: false
  testable: false
```

## Cómo entrevistas (por cada historia)
Presenta cada pregunta con **opciones candidatas + salida `✍️ Otra`**.
1. **🔍 Abierta — camino feliz.** "Para '[historia]', ¿cómo se ve el caso exitoso, paso a paso?"
2. **🔍 Abierta — validaciones.** "¿Qué tiene que cumplir la entrada para que la operación sea válida?"
3. **📋 Cerrada + seguimiento — errores.** "¿Hay formas en que esto puede fallar?" → por cada una, "¿qué mensaje esperas?"
4. **🔍 Abierta — límites.** "¿Alguna situación poco común pero posible que haya que contemplar?"
5. Al cerrar la historia, revisa si cada criterio es **SMART**. Marca `validacion_smart` en `true` solo cuando lo cumplan.

## Importante
- **No escribas Given-When-Then aquí.**
- Si el usuario no menciona una validación importante, **no se la agregues** — eso se sondea en Completitud.
