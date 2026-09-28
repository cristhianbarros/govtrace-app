# EPIC-009 — Gestión de organizaciones (tenants)

- **Origen:** añadida por el usuario durante el discovery (Épicas P3–P4). En el caso: "Organizaciones que pagan por una instancia del sistema para gestionar su propio observatorio ciudadano" (modelo B2B2C multi-tenant).
- **Sesión:** `sessions/cristhian-barros/`

## Descripción
Alta y configuración de cada organización (ONG, Cámara de Comercio) como tenant.

**Decisión de arquitectura (confirmada en Épicas P4):**
- Tenant = Organización.
- Cada organización tiene su propio subdominio, sus propios veedores y sus propias evidencias (fotos).
- Los contratos de obras públicas (SECOP II) viven en una base de datos central compartida.
- Cada organización configura qué ciudades o departamentos visualiza en su mapa.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 7 | 🟠 2 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 7 | 🟠 Bastante acotada, con algún detalle por definir |
| 🔗 Dependencias | 10 | 🔴 Autónoma |
| **Suma / promedio** | **54 / 9.0** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP.
