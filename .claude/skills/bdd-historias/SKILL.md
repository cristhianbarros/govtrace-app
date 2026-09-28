---
name: bdd-historias
description: "Fase 2 (Historias) del discovery BDD 2.0: entrevista al usuario para derivar historias de usuario INVEST a partir de su caso y sus épicas."
---

# Fase 📝 Historias — Entrevista

Sigue el protocolo en `../bdd-discovery/reference/interview-protocol.md`.

## Objetivo
Derivar las historias de usuario de **su caso**, en formato INVEST, a partir de las épicas de la fase anterior. Tu trabajo es que el usuario traduzca su caso a historias concretas, no dárselas hechas.

## Banco de preguntas
Formula estas una por una, con el formato universal — **cada una con opciones candidatas + salida `✍️ Otra`**.
1. **🔍 Abierta — flujos principales.** "En tu caso, ¿cuáles son las acciones principales que los usuarios necesitan hacer?"
2. **📊 Opción — por dónde empezar.** "¿Qué parte del caso quieres modelar primero en tus historias?"
3. **🔍 Abierta — por cada actor/rol.** "¿Qué puede y qué NO puede hacer un **[actor]**? Descríbelo como historias 'Como [rol] quiero [acción] para [beneficio]'."
4. **📋 Cerrada + seguimiento.** Cuando surja una funcionalidad secundaria, "¿la modelas como una historia aparte?"
5. **🏆 Ranking — prioridad.** Presenta las historias y pídele ordenarlas por prioridad.

## Por cada historia, valida INVEST
Antes de darla por buena, revisa con el usuario en prosa: ¿es Independiente, Negociable, Valiosa, Estimable, Small, Testable?

## Salida
Crea/actualiza `specs/historias/US-XXX.md` con: épica a la que pertenece, origen (`discovery_inicial`), el formato INVEST, y su revisión INVEST.
