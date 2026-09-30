# US-056-LEG — Descargar el expediente de una obra, con el derecho de petición y la denuncia pre-llenados

| Campo | Valor |
|---|---|
| Épica | EPIC-008 |
| Actor | Administrador de Organización |
| Origen | `proceso_actual` — revisión del proceso de control social (`docs/proceso-actual.md`, A3), decidida por el usuario el 2026-09-30 |
| Prioridad | **MVP v1** (it. 44b) |
| Necesita antes | US-026, US-029, US-036 |
| Reglas relacionadas | R-LEG-03, R-LEG-04, R-MNT-02 |

## Historia
**Como** Administrador de Organización, **quiero** descargar desde una obra un expediente con sus evidencias publicadas, sus sellos y las plantillas pre-llenadas del derecho de petición a la entidad y de la denuncia ante la Contraloría, **para** llevar la evidencia de GovTrace al proceso formal del control social sin rehacer el trabajo a mano.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ⚠️ el nombre "Prueba Pericial Criptográfica" lo fijó el usuario | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **Las veedurías ya hacen esto a mano.** Su vigilancia termina en derechos de petición y denuncias (Ley 850 de 2003, arts. 15 y 16), y cualquier veeduría o ciudadano puede denunciar ante los organismos de control fiscal (Ley 1757 de 2015, art. 69). Hoy GovTrace termina en el mapa.
- **Las plantillas se entregan en PDF** (decisión del usuario), con espacios en blanco para quien firma. GovTrace no las radica: las presenta la veeduría.
- **Cada archivo del expediente se cita como "Prueba Pericial Criptográfica"** (el nombre lo fijó el usuario).
  - El anclaje legal es el de los mensajes de datos: su integridad y su valor probatorio (Ley 527 de 1999, arts. 9 a 11). El peritaje consiste en recalcular su SHA-256 y comprobar su raíz sellada en Stellar.
  - ❓ Conviene que un abogado confirme la etiqueta: en sentido estricto, la "prueba pericial" es el dictamen de un perito.
- **Cada descarga va al log de auditoría.** Es la telemetría que decidió el usuario: con ella se valida en producción si la función se usa.

## Criterios de aceptación
`specs/criterios/US-056-LEG.yaml` · `features/US-056-LEG.feature`
