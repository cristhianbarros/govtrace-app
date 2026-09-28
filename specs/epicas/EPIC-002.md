# EPIC-002 — Recolección de evidencia

- **Origen:** caso aportado, flujo 2 ("Recolección de Evidencia")
- **Sesión:** `sessions/cristhian-barros/`

## Descripción (del caso)
Un Veedor Ciudadano abre la aplicación en su celular, selecciona una obra cercana, toma una foto (evidencia) y añade un comentario (ej. "Obra abandonada hace 2 meses"). La interfaz debe ser extremadamente fácil de usar en pantallas pequeñas, con botones grandes y tolerante a malas conexiones móviles.

**Relaciones:** depende de EPIC-001 (las obras seleccionables vienen de SECOP II) y de EPIC-006 (identidad del veedor). Las evidencias pertenecen a la organización (tenant) del veedor.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 10 | 🔴 Los 3 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 10 | 🔴 Muy acotada |
| 🔗 Dependencias | 5 | 🟡 Depende de otras épicas (EPIC-001, EPIC-006) — ajustado de 10 a 5 tras recomendación del modo asesor |
| **Suma / promedio** | **55 / 9.17** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP (suma 55).
