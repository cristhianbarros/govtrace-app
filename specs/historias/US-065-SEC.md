# US-065-SEC — Verificación en dos pasos del Super Administrador

| Campo | Valor |
|---|---|
| Épica | EPIC-006 |
| Actor | Super Administrador |
| Origen | `pregunta_del_usuario`, 2026-10-05: "aplícalo solo para el super admin, pero déjalo inactivo por el momento", tras revisar los costos (TOTP: 0 USD al mes). Aprobado como it. 46g |
| Prioridad | **MVP v1** (it. 46g), **inactiva** hasta que el operador la active |
| Necesita antes | US-031, US-039-USR, US-063-USR |
| Reglas relacionadas | R-SEC-09, R-SEC-03, R-AUD-04 |

## Historia
**Como** plataforma, **quiero** que el Super Administrador entre con su contraseña y con un código de su app autenticadora, **para** que una contraseña robada no baste para gobernar todas las organizaciones.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Inactiva por ahora.** La activa el operador en el servidor (`SUPER_ADMIN_TWO_FACTOR=true`), no un Super Administrador desde el panel: si se pudiera apagar desde el panel, una sesión robada la apagaría para todos.
- **Solo el Super Administrador.** Los Administradores de las veedurías y los veedores entran como hoy.
- **TOTP (RFC 6238):** cualquier app autenticadora (Google o Microsoft Authenticator, Authy, el gestor de contraseñas). Sin SMS ni proveedor: funciona sin señal y no cuesta nada.
- **Obligatoria mientras está activa.** El que aún no la tiene la configura al entrar: escanea un código QR, escribe un código para confirmar y guarda 8 códigos de recuperación, que se muestran una sola vez.
- **Sin el teléfono:** un código de recuperación, de un solo uso. Sin teléfono ni códigos, el operador la restablece desde la consola del servidor (`make admin-2fa-reset EMAIL=…`) y la persona la vuelve a configurar al entrar.
- **La contraseña sola no abre nada:** después de la contraseña, el panel sigue cerrado hasta el código, con 10 minutos para escribirlo. 5 códigos equivocados bloquean 15 minutos, como la contraseña (R-SEC-03).
- **Al activarla**, una sesión abierta antes, sin el segundo paso, se cierra en su siguiente petición.

## Criterios de aceptación
`specs/criterios/US-065-SEC.yaml` · `features/US-065-SEC.feature`
