---
name: bdd-completitud
description: "Fase 4 (clave) del discovery BDD 2.0: autocrítica sistemática recorriendo las 10 áreas del ciclo de vida para que el usuario DESCUBRA lo que su caso no dice. Genera historias de gap US-XXX-AREA clasificadas 🔥/⚡/💡."
---

# Fase 🔍 Completitud — Autocrítica por las 10 Áreas del Ciclo de Vida (fase clave)

Sigue el protocolo en `../bdd-discovery/reference/interview-protocol.md`. **La regla de "pregunta, no dictes" aplica al máximo aquí:** haces preguntas que llevan al usuario a darse cuenta de lo que falta — **nunca** enuncias la regla tú.

**Opciones sin sesgar (crítico en esta fase):**
- **Alternativas plausibles y balanceadas**, ninguna justificada.
- Encabezado **sin denominador** (`❓ PREGUNTA X`, no "X DE Y"): el número de huecos es desconocido.
- Patrón (para un límite): `A) lo permite normalmente · B) lo permite pero avisa · C) el sistema lo impide · ✍️ Otra`.

## Objetivo
El caso cubre lo obvio. Recorre las **10 áreas** buscando lo que falta. Por cada hueco que el usuario descubra:
1. Si es de una historia existente → agrégala al YAML de esa historia.
2. Si es nueva funcionalidad → crea una **historia de gap** `specs/historias/US-XXX-<AREA>.md` (ej. `US-001-SEC`).
3. **Clasifica cada gap:** 🔥 Crítico · ⚡ Importante · 💡 Mejora.

## Las 10 áreas (sondas genéricas — adáptalas al caso)
### CFG · ⚙️ Configuración
- "¿Hay parámetros o reglas de tu caso que deberían ser **configurables** en vez de fijos en el código?"
### USR · 👥 Usuarios / acceso
- "¿Tu sistema distingue **quién** puede hacer qué? ¿Qué pasa si un usuario se queda sin permisos?"
### SEC · 🔒 Seguridad / autorización
- "Para cada acción que **cambia datos**, ¿quién debería poder ejecutarla?"
- "¿Alguna entrada necesita **reglas de validación fuertes**?"
### AUD · 🗃️ Auditoría / integridad
- "Al **eliminar** algo, ¿qué debería pasar con lo que dependía de ello?"
- "¿Hay **datos de referencia** en tu caso que no deberían poder modificarse?"
### MON · 📈 Monitoreo
- "¿Qué de tu sistema querrías poder **observar** en producción?"
### INT · 🔗 Integraciones
- "¿Tu caso necesita hablar con algo **externo** (otro servicio, mapa, proveedor)? ¿Qué debería pasar si falla?"
### MNT · 🔧 Mantenimiento
- "Con el tiempo, ¿hay **datos que limpiar o migrar**?"
### RPT · 📊 Reportes
- "¿Qué **información agregada o reportes** tendrían sentido para tu caso?"
### BCK · 💾 Backup
- "Si se **pierde o corrompe** la base, ¿qué necesitas para recuperar?"
### TST · 🧪 Testing (cierre)
- "Por cada regla que descubriste, ¿tienes un escenario que la **viole a propósito**?"

No enuncies las reglas tú. No menciones un número total de reglas ("faltan N"). Sondea con preguntas abiertas + opciones balanceadas.
