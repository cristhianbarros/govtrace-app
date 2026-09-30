# US-057-LEG — Declarar que no tengo impedimentos para ser veedor

| Campo | Valor |
|---|---|
| Épica | EPIC-006 |
| Actor | Veedor de Campo |
| Origen | `proceso_actual` — revisión del proceso de control social (`docs/proceso-actual.md`, A4), decidida por el usuario el 2026-09-30 |
| Prioridad | **MVP v1** (it. 44c) |
| Necesita antes | US-005, US-030, US-008 |
| Reglas relacionadas | R-LEG-05, R-AUD-04 |

## Historia
**Como** Veedor de Campo, **quiero** declarar, al activar mi cuenta, que no estoy en ninguno de los impedimentos que la ley fija para ser veedor, **para** que mis reportes no queden viciados por un conflicto de interés y mi veeduría pueda demostrarlo.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Qué dice la ley.** El artículo 19 de la Ley 850 de 2003 impide ser veedor a:
  - contratistas, interventores, proveedores o trabajadores de la obra, de hoy o del último año;
  - sus familiares cercanos;
  - servidores públicos relacionados con la obra, y ediles, concejales, diputados y congresistas;
  - quien tenga vínculos con una organización comprometida en ella;
  - quien haya sido condenado o destituido.
- **GovTrace no puede comprobarlo.** Registra la declaración del veedor, con su fecha, y la deja en el log de auditoría.
- **El veedor que ya tenía cuenta** la declara antes de su próximo reporte.
  - Mientras no lo haga, el servidor no recibe sus reportes. Responde 403, no 422: un reporte guardado en el celular sin conexión no se descarta, y se envía después de declarar.
- ❓ **El Administrador no la declara:** no reporta. Si el usuario decide que también es veedor en el sentido de la ley, se le pide igual.

## Criterios de aceptación
`specs/criterios/US-057-LEG.yaml` · `features/US-057-LEG.feature`
