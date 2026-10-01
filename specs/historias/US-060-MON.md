# US-060-MON — El resumen diario de las evidencias por revisar

| Campo | Valor |
|---|---|
| Épica | EPIC-004 |
| Actor | Administrador de Organización |
| Origen | `mapa_funcional`: V9 de `docs/mapa-funcional.md`, aprobado por el usuario el 2026-10-01 ("a daily digest email for pending evidence rather than alerting on every single submission to prevent alert fatigue") |
| Prioridad | **MVP v1** (it. 43i) |
| Necesita antes | US-036 |
| Reglas relacionadas | R-UX-06 |

## Historia
**Como** Administrador de Organización, **quiero** recibir una vez al día un correo con cuántas evidencias de mis veedores esperan mi revisión, **para** no tener que entrar a buscarlas, y que no se queden ocultas sin que nadie lo sepa.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Un resumen al día, no un correo por evidencia** (decisión del usuario): evita la fatiga de alertas.
- **Solo si hay algo que revisar:** las evidencias selladas que siguen ocultas, las mismas que cuenta la pestaña "Bandeja" (it. 40c).
- **A cada Administrador activo** de cada organización activa. A una suspendida no: sus administradores no pueden entrar.

## Criterios de aceptación
`specs/criterios/US-060-MON.yaml` · `features/US-060-MON.feature`
