---
name: bdd-epicas
description: "Fase 1 del discovery BDD 2.0: entrevista al usuario para identificar y puntuar con VUIFED (6 ejes, 1-10) las grandes funcionalidades (épicas) de SU caso."
---

# Fase 🎯 Épicas — Entrevista (VUIFED)

Sigue el protocolo en `../bdd-discovery/reference/interview-protocol.md`.

## Objetivo
Identificar, a partir del **caso que aportó el usuario** (`sessions/<slug>/caso.md`), las **grandes funcionalidades (épicas)** del sistema, y puntuar cada una con VUIFED.

## VUIFED — los 6 ejes (1-10 cada uno)
Por cada épica, puntúa con el usuario, una pregunta por eje (o agrupa de a dos si va rápido):
- **Valor de negocio** 💼 — ¿cuánto valor aporta al producto?
- **Usuarios** 👥 — ¿a cuántos tipos de usuario impacta?
- **Impacto** 🎯 — ¿qué tan crítica es para el objetivo del sistema?
- **Factibilidad técnica** 🔧 — ¿qué tan viable es con Laravel/Vue?
- **Esfuerzo estimable** 📏 — ¿qué tan acotada y estimable es? (10 = muy acotada)
- **Dependencias** 🔗 — ¿qué tan libre está de bloqueos externos? (10 = autónoma)

Formula cada eje con el formato universal **ofreciendo opciones + salida `✍️ Otra`** (ej. para Valor: `A) 🔴 Crítico · B) 🟠 Alto · C) 🟡 Medio · D) 🟢 Bajo · ✍️ Otra`), traduce la elección a un número 1-10, valídala.

## Salida
Crea `specs/epicas/EPIC-001.md` con:
- Nombre y descripción de la épica (tomados del caso).
- Los 6 scores VUIFED y su suma/promedio.
- `mvp_included: true/false` con una frase de justificación del usuario.
