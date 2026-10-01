# US-061-USR — Varios administradores por organización

| Campo | Valor |
|---|---|
| Épica | EPIC-009 |
| Actor | Super Administrador, Administrador de Organización |
| Origen | `mapa_funcional`: V3 de `docs/mapa-funcional.md`, decidido por el usuario el 2026-10-01 ("Allow multiple administrators. A single admin is a single point of failure, e.g. if they lose access or leave the organization") |
| Prioridad | **MVP v1** (it. 43j) |
| Necesita antes | US-002, US-005, US-030 |
| Reglas relacionadas | R-AUD-04, R-USR-01, R-TA-03 |

## Historia
**Como** veeduría, **quiero** tener más de un Administrador, **para** que la organización no quede sin quién la gestione si uno pierde el acceso o se va. **Como** Super Administrador, **quiero** agregar administradores, y desactivar al que se fue, sin dejar nunca la organización sin uno activo. **Como** Administrador, **quiero** invitar a otro administrador desde mi panel, sin depender del Super Administrador.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Agregar:** el Super Administrador, desde el panel global, y cada Administrador, desde "Veedores". Es la misma invitación por correo del Administrador inicial (US-002, US-030).
- **Desactivar y reactivar a un Administrador:** solo el Super Administrador. Es una decisión de gobierno de la organización, como suspenderla (US-003a).
- **Nunca sin uno activo:** no se desactiva al único Administrador que ya activó su cuenta. Primero se agrega otro.
- **El mismo correo no se repite** en la organización (R-USR-01): ni como administrador ni como veedor.

## Criterios de aceptación
`specs/criterios/US-061-USR.yaml` · `features/US-061-USR.feature`
