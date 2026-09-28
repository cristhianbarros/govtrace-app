# EPIC-006 — Gestión de cuentas y roles

- **Origen:** añadida por el usuario durante el discovery (Épicas P2). El caso nombra los actores pero no describe cómo se gestionan sus cuentas.
- **Sesión:** `sessions/cristhian-barros/`

## Descripción
Creación, identificación y gestión de las cuentas de ciudadanos/veedores y administradores de organización, y definición de lo que cada rol puede y no puede hacer. El usuario pidió iterar esta épica a fondo.

**Decisión de arquitectura asociada:** cada organización (tenant) tiene sus propios veedores.

**Ideas por explorar (supuestos del usuario, aún no son reglas):**
- Alcance geográfico: un veedor solo adjunta evidencia de obras dentro de su geografía.
- Un ciudadano que colabora con más de una organización.
- Relación entre el territorio configurado por la organización y el alcance del veedor.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 7 | 🟠 2 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 5 | 🟡 Parcialmente acotada, con varias incógnitas — ajustado de 10 a 5 tras recomendación del modo asesor |
| 🔗 Dependencias | 10 | 🔴 Autónoma |
| **Suma / promedio** | **52 / 8.67** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP.
