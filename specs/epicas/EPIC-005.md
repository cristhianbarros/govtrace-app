# EPIC-005 — Verificación de integridad

- **Origen:** caso aportado, flujo 5 ("Verificación de Integridad")
- **Sesión:** `sessions/cristhian-barros/`

## Descripción (del caso)
Un Auditor puede descargar la foto de una obra e ingresarla en la herramienta pública de "Validación". El sistema recalcula el Hash en el navegador del usuario y lo compara con el registro en la blockchain, confirmando al instante si la foto es auténtica o si ha sido manipulada.

**Relaciones:** verifica contra los registros creados por EPIC-003.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 10 | 🔴 Los 3 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 7 | 🟠 Viable, con algunas incógnitas |
| 📏 Esfuerzo estimable | 7 | 🟠 Bastante acotada, con algún detalle por definir |
| 🔗 Dependencias | 5 | 🟡 Depende de externos u otras épicas, con cierto riesgo |
| **Suma / promedio** | **49 / 8.17** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP.
