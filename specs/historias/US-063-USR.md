# US-063-USR — Varios Super Administradores, y nunca ninguno

| Campo | Valor |
|---|---|
| Épica | EPIC-009 |
| Actor | Super Administrador |
| Origen | `pregunta_del_usuario`, 2026-10-02: "podría darse el caso que el sistema quede sin super administrador y que la aplicación tenga cierta dependencia de eso". Aprobado como it. 46a |
| Prioridad | **MVP v1** (it. 46a) |
| Necesita antes | US-031, US-039-USR, US-061-USR |
| Reglas relacionadas | R-AUD-04, R-USR-01, R-SEC-03 |

## Historia
**Como** plataforma, **quiero** tener más de un Super Administrador, **para** no depender de una sola persona para dar de alta veedurías, cambiar sus administradores y atender las alertas. **Como** Super Administrador, **quiero** invitar a otro desde el panel y desactivar al que se fue, sin que la plataforma quede nunca sin uno activo.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Hoy** un Super Administrador solo se crea desde la consola del servidor (`make admin`). Esa vía se queda, para recuperar el acceso si todos lo pierden.
- **Invitar:** la misma invitación por correo de los administradores (US-030), con un enlace de un solo uso que vence según `invitation_validity_hours`. Al activar su cuenta, autoriza el tratamiento de sus datos (US-058-LEG).
- **Desactivar y reactivar:** lo hace otro Super Administrador, nunca uno a sí mismo. La sesión del desactivado se cierra en su siguiente petición.
- **Nunca ninguno:** no se desactiva al último Super Administrador activo, ni aunque dos se desactiven el uno al otro al mismo tiempo. Una invitación pendiente no cuenta.
- **Un solo activo:** el panel lo avisa siempre, y al quedar uno solo llega una alerta por correo y al webhook.
- **Las alertas** (cola de sellado, saldo, organizaciones inactivas) les llegan solo a los Super Administradores activos.

## Criterios de aceptación
`specs/criterios/US-063-USR.yaml` · `features/US-063-USR.feature`
