# US-059-LEG — Informar a la veeduría de lo que vi en una obra

| Campo | Valor |
|---|---|
| Épica | EPIC-008 |
| Actor | Verificador Público (el ciudadano), Administrador de Organización |
| Origen | `proceso_actual` — `docs/proceso-actual.md`, A2; luz verde del usuario el 2026-09-30, con correo verificado |
| Prioridad | **MVP v1** (it. 44f) |
| Necesita antes | US-029, US-058-LEG |
| Reglas relacionadas | R-LEG-09, R-LEG-10, R-LEG-07 |

## Historia
**Como** ciudadano que vio algo en una obra, **quiero** informárselo a la veeduría que la vigila, con mi correo verificado y sin crear una cuenta, **para** que la veeduría lo tenga en cuenta y me responda. **Como** Administrador, **quiero** recibir esos informes sin ver el correo de quien los envía, y responderle o descartarlos.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ⚠️ grande: canal público, código por correo, foto y panel | ✅ | ✅ |

**Notas:**
- **La base legal.** Toda veeduría tiene el deber de "recibir los informes, observaciones y sugerencias que presenten los particulares" (Ley 850 de 2003, arts. 15 e) y 18 a)).
- **No se sella ni se publica.** No gasta XLM (V2-CIUDADANO: la evidencia ciudadana sellada queda para la V2). La veeduría decide si manda un veedor.
- **Correo verificado con un código de 6 dígitos** (decisión del usuario: "Magic Link u OTP"). Es la barrera contra el spam y el canal de respuesta.
  - La veeduría no ve el correo: le responde desde GovTrace.
  - El correo se guarda cifrado; su huella sirve para contar los límites.
- **Límites:** 3 informes por correo al día y 1 por obra; códigos por correo y por IP; 5 intentos por código; 10 minutos de vigencia.
- **La foto** es opcional, JPEG o PNG hasta 10 MB y sin metadatos. El navegador la limpia como la del veedor.
- **La autorización** del tratamiento de datos (Ley 1581, art. 9) se da al pedir el código, con la misma política.
- **It. 46h:** de 1 a 3 fotos opcionales, con las mismas opciones del veedor (la cámara trasera y la galería, sin PDF). Una foto de contexto y otra de detalle suelen hacer falta para que se entienda. Las fotos del ciudadano nunca se publican: solo las ve la veeduría. Si una foto se rechaza, el informe no guarda ninguna; al borrar un informe descartado (30 días), se borran todas.
- **It. 46e (R-PRIV-05):** cada foto pasa por la misma revisión que la del veedor: los rostros se difuminan en el celular antes de enviarla.
- **It. 58 (2026-10-14):** el ciudadano informa sobre cualquier obra del territorio, no solo sobre las que un veedor ya visitó.
  - La Lista del mapa suma "Obras sin ubicación todavía": los contratos que se pueden reportar (los mismos del veedor) y que aún no están en el mapa, con la búsqueda por nombre y el filtro de municipio, de a 20.
  - Si el contrato no tenía ficha, el informe la crea sin ubicación, como el primer reporte de un veedor.
  - En una obra sin ubicación, el ciudadano puede marcar en un mapa dónde queda. Es el lugar de la obra, no el suyo: no sale de la foto ni del GPS del teléfono (R-PRIV-01). No pone la obra en el mapa: la confirma el Administrador (US-035).
  - Se descartó usar la ubicación de la foto del ciudadano: diría dónde estaba la persona, y cualquiera podría mover el pin, porque el ciudadano no tiene cuenta.

## Criterios de aceptación
`specs/criterios/US-059-LEG.yaml` · `features/US-059-LEG.feature`
