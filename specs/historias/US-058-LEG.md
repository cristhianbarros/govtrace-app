# US-058-LEG — Conocer la política de tratamiento de datos y autorizar el tratamiento de los míos

| Campo | Valor |
|---|---|
| Épica | EPIC-006 |
| Actor | Veedor de Campo, Administrador de Organización y Verificador Público |
| Origen | `proceso_actual` — la Ley 1581 de 2012 era una decisión pendiente (`docs/estado-mvp.md`); el usuario pidió empezarla el 2026-09-30 |
| Prioridad | **MVP v1** (it. 44e). Bloquea la salida a producción y el canal del ciudadano (A2, it. 44f) |
| Necesita antes | US-030, US-057-LEG |
| Reglas relacionadas | R-LEG-07, R-LEG-08, R-PRIV-01 a 06 |

## Historia
**Como** persona cuyos datos trata GovTrace — un veedor, un Administrador o cualquiera que mire el mapa —, **quiero** conocer en lenguaje claro qué datos se tratan, para qué, quién responde por ellos y cómo ejercer mis derechos, y autorizar el tratamiento de los míos al crear mi cuenta, **para** que el tratamiento cumpla la Ley 1581 de 2012.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ⚠️ el texto es un borrador para revisión legal | ✅ | ✅ | ✅ | ✅ |

**Notas:**
- **El contenido mínimo de la política** (Decreto 1074 de 2015, art. 2.2.2.25.3.1, antes Decreto 1377 de 2013, art. 13):
  - el responsable: nombre o razón social, domicilio, dirección, correo y teléfono;
  - el tratamiento y su finalidad;
  - los derechos del titular;
  - el área que atiende las peticiones;
  - el procedimiento para conocer, actualizar, rectificar, suprimir y revocar;
  - la fecha de entrada en vigencia y el período de vigencia de la base.
- **Plazos:** consultas en 10 días hábiles (Ley 1581, art. 14); reclamos en 15 (art. 15).
- ❓ **Quién es el responsable del tratamiento** (D-V2-10). Por defecto, el operador de la plataforma, con sus datos en la configuración de producción. Mientras falten, la página lo dice y se ve como borrador. Si el usuario decide que cada veeduría es responsable y GovTrace su encargado, cambia el texto, no el mecanismo.
- **Autorización** previa, expresa e informada (Ley 1581, art. 9). Se pide al activar la cuenta y se guarda con su fecha y la versión de la política: es su prueba.
- **Los usuarios que ya existen** son de desarrollo y de la demostración: el MVP no ha salido a producción. Los reales entran todos por la activación. Los Super Administradores son personal del operador y se crean por consola.
- **El texto es un borrador.** Lo debe revisar un abogado antes de publicarlo.

## Criterios de aceptación
`specs/criterios/US-058-LEG.yaml` · `features/US-058-LEG.feature`
