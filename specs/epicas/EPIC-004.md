# EPIC-004 — Visualización geoespacial (mapa)

- **Origen:** caso aportado, flujo 4 ("Visualización Geoespacial")
- **Sesión:** `sessions/cristhian-barros/`

## Descripción (del caso)
La plataforma cuenta con un mapa interactivo público donde las obras se representan como pines. Los pines cambian de color según el estado de la obra o si tienen alertas ciudadanas recientes.

**Decisión de arquitectura asociada:** cada organización (tenant) configura qué ciudades o departamentos visualiza en su mapa.
**Relaciones:** los pines son obras de EPIC-001; las alertas que colorean los pines provienen de EPIC-002.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 7 | 🟠 Alto |
| 👥 Usuarios | 10 | 🔴 Los 3 tipos de usuario |
| 🎯 Impacto | 7 | 🟠 Muy importante, pero hay alternativas temporales |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 10 | 🔴 Muy acotada |
| 🔗 Dependencias | 5 | 🟡 Depende de otras épicas (EPIC-001, EPIC-002) — ajustado de 7 a 5 tras recomendación del modo asesor |
| **Suma / promedio** | **49 / 8.17** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP.
