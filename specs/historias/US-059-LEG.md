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
- **It. 46e (R-PRIV-05):** la foto opcional pasa por la misma revisión que la del veedor: los rostros se difuminan en el celular antes de enviarla.

## Criterios de aceptación
`specs/criterios/US-059-LEG.yaml` · `features/US-059-LEG.feature`
