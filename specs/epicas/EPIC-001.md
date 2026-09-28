# EPIC-001 — Sincronización de contratos (SECOP II)

- **Origen:** caso aportado, flujo 1 ("Sincronización de Contratos")
- **Sesión:** `sessions/cristhian-barros/`

## Descripción (del caso)
El sistema se conecta periódicamente a la API de Datos Abiertos de Colombia (SECOP II) para descargar los contratos de obras públicas de una región específica y publicarlos en la plataforma.

**Decisión de arquitectura asociada:** los contratos viven en la base de datos **central**, compartida por todas las organizaciones (tenants); cada organización configura qué ciudades o departamentos visualiza.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 10 | 🔴 Los 3 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 10 | 🔴 Muy acotada |
| 🔗 Dependencias | 7 | 🟠 Depende de un servicio externo estable (API SECOP II) |
| **Suma / promedio** | **57 / 9.5** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP (suma 57, la más alta).
