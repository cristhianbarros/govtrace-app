# US-064-SEC — Identificadores públicos que no se pueden recorrer

| Campo | Valor |
|---|---|
| Épica | EPIC-004 (y todo lo que tiene una URL: EPIC-003, EPIC-005, EPIC-006, EPIC-007, EPIC-009) |
| Actor | Ciudadano, veedor, Administrador de Organización, Super Administrador |
| Origen | `pregunta_del_usuario`, 2026-10-02: "los índices de los maestros que son numéricos y no sé si se pueda prestar para hackeos. Podríamos pensar en un id uuid o algo que sea difícil de descifrar en cuanto a autoincremental". Aprobado como it. 46c |
| Prioridad | **MVP v1** (it. 46c) |
| Necesita antes | US-023, US-026, US-027, US-029, US-052-RPT |
| Reglas relacionadas | R-SEC-08, R-BLK-02 |

## Historia
**Como** veeduría que publica en GovTrace, **quiero** que las URL y el API no lleven el número consecutivo de mis obras, reportes, evidencias, informes ciudadanos y usuarios, **para** no revelar cuántos tengo ni dejar que alguien recorra lo público en orden.

## Revisión INVEST
| I | N | V | E | S | T |
|---|---|---|---|---|---|
| ✅ | ✅ | ✅ | ⚠️ toca todas las rutas y migra datos en cada organización | ✅ | ✅ |

**Notas:**
- **No es un hueco de autorización:** cada ruta ya revisa permisos (una base por organización, los roles, solo lo publicado en lo público, el token de la invitación). El número revela el volumen y deja recorrer lo público en orden.
- **El identificador público** es un ULID: 26 caracteres, ordenable por fecha, cómodo en una URL. Los registros que ya existen lo reciben con la fecha en que se crearon.
- **Las llaves numéricas no cambian:** la referencia de la obra sellada en Stellar es el SHA-256 de «organización:ficha» (R-BLK-02). Cambiar ese número rompería la verificación de lo ya sellado. La prueba de inclusión (`.prueba.json`) no lleva ningún número y sigue igual.
- **Los enlaces viejos con número no abren nada** (it. 46i). La 46c los redirigía (301) al nuevo, por si alguno se había compartido, solo si lo que nombraban ya era público y con el token en una invitación. Se quitaron antes de la red principal: nada con número se había compartido, y cada ruta que queda es superficie. Las rutas internas, que nadie comparte, tampoco aceptan el número.
- **Sin conexión:** un reporte que el veedor guardó en el celular antes del cambio se recibe igual: nombra la obra por su contrato de SECOP, no por un número.
- **El informe de un ciudadano** se nombra, en pantalla y en sus correos, con una referencia corta tomada de la parte aleatoria de su identificador público (por ejemplo `7KQ3-M9XD`), no con su número consecutivo (US-059-LEG): el número decía cuántos informes había recibido la veeduría.
- **Fuera:** el número de una entrada del log de auditoría (solo la ve quien administra, y el log no se recorre desde afuera) y el de los contratos, que es el de SECOP.

## Criterios de aceptación
`specs/criterios/US-064-SEC.yaml` · `features/US-064-SEC.feature`
