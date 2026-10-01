# US-062-ALT — Una veeduría pide su alta en GovTrace

| Campo | Valor |
|---|---|
| Épica | EPIC-009 |
| Actor | Veeduría interesada (sin cuenta), Super Administrador |
| Origen | `mapa_funcional`: V10 de `docs/mapa-funcional.md`, decidido por el usuario el 2026-10-01 ("a simple public intake form (Veeduría Name, Contact Email, Personería Resolution Number). This will go to a 'Pending Approvals' queue for the Super Admin") |
| Prioridad | **MVP v1** (it. 43k) |
| Necesita antes | US-001, US-002, US-058-LEG |
| Reglas relacionadas | R-LEG-06, R-LEG-07, R-AUD-04 |

## Historia
**Como** veeduría que quiere publicar en GovTrace, **quiero** pedir mi alta desde el Inicio, sin cuenta, **para** no tener que buscar a quién escribirle. **Como** Super Administrador, **quiero** ver las solicitudes pendientes y aprobarlas o rechazarlas con un motivo, **para** conservar el alta controlada (US-001): no hay autorregistro.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ⚠️ formulario público, cola y aprobación | ✅ | ✅ |

**Notas:**
- **Los campos:** el nombre de la veeduría, el correo de contacto y el número de la resolución de la Personería, como pidió el usuario. Se agrega **la Personería que la expidió**, porque la inscripción se identifica con el número y la entidad (R-LEG-06, it. 44d).
- **La autorización del tratamiento de datos** (Ley 1581, art. 9; R-LEG-07): el correo de contacto es un dato personal.
- **Contra el spam:** un límite por conexión y un campo oculto que solo llena un robot. No se le envía ningún correo a quien pide, para que el formulario no sirva para enviarle correos a terceros. La respuesta llega cuando el Super Administrador decide.
- **Aprobar** es dar el alta de siempre (US-001), con la Nueva organización precargada: el nombre, la inscripción y el correo de contacto como Administrador inicial (US-002). El Super Administrador elige el subdominio. Al registrarla, la solicitud queda aprobada.
- **Rechazar** pide un motivo, que le llega por correo al contacto.
- **Retención:** una solicitud decidida se borra a los 30 días, como los informes ciudadanos (45c).

## Criterios de aceptación
`specs/criterios/US-062-ALT.yaml` · `features/US-062-ALT.feature`
