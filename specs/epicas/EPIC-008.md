# EPIC-008 — Radicación de denuncias

- **Origen:** añadida por el usuario durante el discovery (Épicas P2–P3). No aparece en el caso original.
- **Sesión:** `sessions/cristhian-barros/`

## Descripción
El ciudadano/veedor puede formalizar ("radicar") una denuncia **ante la Contraloría** a partir de una o varias evidencias. El alcance exacto (destinatario, formato, estados) se define en la fase de Historias.

## VUIFED

| Eje | Puntaje | Respuesta |
|---|---|---|
| 💼 Valor de negocio | 10 | 🔴 Crítico |
| 👥 Usuarios | 7 | 🟠 2 tipos de usuario |
| 🎯 Impacto | 10 | 🔴 Sin ella el sistema no cumple su propósito |
| 🔧 Factibilidad técnica | 10 | 🔴 Muy viable, tecnología conocida |
| 📏 Esfuerzo estimable | 7 | 🟠 Bastante acotada, con algún detalle por definir |
| 🔗 Dependencias | 5 | 🟡 Depende de externos u otras épicas, con cierto riesgo |
| **Suma / promedio** | **49 / 8.17** | |

## MVP
`mvp_included: true` desde el 2026-09-30, para el **MVP v1** (antes, `false`).

**Decisión del usuario (2026-09-30).** La revisión del proceso actual (`docs/proceso-actual.md`) mostró que GovTrace terminaba en el mapa, un paso antes del proceso formal: peticiones, recomendaciones y denuncias. Entran:
- A1: el estado de una obra como alerta, y los canales de la Contraloría (US-055-LEG, it. 44a);
- A3: el expediente de una obra, con las plantillas pre-llenadas de derecho de petición y de denuncia (it. 44b).

La radicación ante la Contraloría sigue fuera: la hace la veeduría, o el ciudadano, por los canales oficiales.

Decisión original, del discovery:

**Justificación del usuario:** "Aunque es una idea brillante, requiere maquetar documentos legales complejos en PDF y flujos de envío externo que consumen tiempo valioso que es mejor invertir en pulir el sellado blockchain y el mapa."
