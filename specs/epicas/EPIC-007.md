# EPIC-007 — Moderación de reportes

- **Origen:** añadida por el usuario durante el discovery (Épicas P3). En el caso aparece en el rol del Administrador de ONG/Veeduría: "aprueban o moderan los reportes antes de hacerlos públicos".
- **Sesión:** `sessions/cristhian-barros/`

## Descripción
El administrador de cada organización (tenant) aprueba o modera los reportes de sus veedores antes de hacerlos públicos.

**Relaciones:** modera los reportes producidos por EPIC-002; el rol de administrador viene de EPIC-006.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 7 | 🟠 Alto |
| 👥 Usuarios | 7 | 🟠 2 tipos de usuario |
| 🎯 Impacto | 7 | 🟠 Muy importante, pero hay alternativas temporales |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 10 | 🔴 Muy acotada |
| 🔗 Dependencias | 5 | 🟡 Depende de otras épicas (EPIC-002, EPIC-006) — ajustado de 10 a 5 tras recomendación del modo asesor |
| **Suma / promedio** | **46 / 7.67** | |

## MVP
`mvp_included: false`

**Justificación del usuario:** "Al iniciar con un grupo cerrado de veedores confiables o administradores de tenant, la moderación automatizada o de múltiples niveles no es necesaria el Día 1."

**Decisión asociada (tras recomendación del modo asesor):** el retiro de contenido el Día 1 **no** se hace borrando en base de datos (rompería la trazabilidad y la promesa de inmutabilidad del caso). Se hará mediante un mecanismo de **revocación lógica** (soft delete / estado de revocación) que conserva el registro y su sello. Su detalle (quién revoca, qué se muestra, qué queda registrado) se explora en Historias / Completitud.
