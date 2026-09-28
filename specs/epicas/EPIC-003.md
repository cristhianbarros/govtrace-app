# EPIC-003 — Sellado criptográfico (blockchain invisible)

- **Origen:** caso aportado, flujo 3 ("Sellado Criptográfico")
- **Sesión:** `sessions/cristhian-barros/`

## Descripción (del caso)
Cuando la evidencia es recibida, el backend calcula un Hash (huella digital) de la foto y los metadatos. El sistema usa su propia billetera (Relayer) para pagar el costo de la red y guarda ese Hash en un Smart Contract en la blockchain pública (Polygon).

**Restricciones del caso:** cero fricción Web3 (nadie crea billeteras, guarda semillas ni compra gas); nunca se guardan datos personales en la blockchain, solo hashes SHA-256 irreversibles.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 10 | 🔴 Los 3 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 7 | 🟠 Viable, con algunas incógnitas |
| 📏 Esfuerzo estimable | 5 | 🟡 Parcialmente acotada, con varias incógnitas |
| 🔗 Dependencias | 5 | 🟡 Depende de externos u otras épicas, con cierto riesgo |
| **Suma / promedio** | **47 / 7.83** | |

## MVP
`mvp_included: true`

Sí — la selección D del usuario la incluye en el MVP; es el núcleo de la promesa de inmutabilidad del caso.
