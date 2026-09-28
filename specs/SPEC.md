# SPEC — GovTrace: Veeduría Ciudadana Inmutable

> Generado por `/discovery` a partir del caso aportado por Cristhian Barros (`sessions/cristhian-barros/caso.md`). Especificación completa: historias, criterios de aceptación y reglas de negocio, validados con el usuario durante la entrevista BDD 2.0 (5 fases). Cierre: 2026-09-27.
>
> **Fuentes de verdad:** historias en `specs/historias/`, criterios en `specs/criterios/*.yaml`, especificación ejecutable en `features/*.feature`. Este documento las consolida; ante una diferencia, mandan los `.feature`.

## Resumen del producto

Plataforma Open Source y Mobile-First de veeduría ciudadana, multi-tenant (B2B2C). Cada **organización** (ONG, Cámara de Comercio, veeduría) es un tenant, con subdominio, veedores y evidencias propios. Los **contratos de obra pública** se sincronizan desde SECOP II a una base central compartida. Los veedores capturan fotos o PDF geolocalizados. Cada reporte se **sella en Stellar** (una raíz de Merkle por reporte) en un Smart Contract de Soroban; GovTrace paga las comisiones de red (fee bump), de forma invisible para el usuario. Cualquier persona puede **verificar** una evidencia en su navegador contra la blockchain, sin depender de GovTrace.

### Decisiones de arquitectura confirmadas

| Tema | Decisión |
|---|---|
| Tenancy | Tenant = Organización. Contratos SECOP en BD central; ficha de obra (ubicación, riesgo) **por organización** |
| Participación | Modelo **cerrado**: solo veedores invitados por una organización aportan evidencia |
| Obra vs. contrato | El contrato SECOP es inmutable; GovTrace mantiene su propia ficha de obra (puede agrupar varios contratos) |
| Ubicación de la obra | SECOP no trae coordenadas → *First-Touch Anchoring* (el primer reporte ancla la obra), corregible por el Admin (US-035) |
| Cadena de custodia | Foto optimizada (1920 px, JPEG 80 %) y sin EXIF → SHA-256 en el teléfono → el servidor verifica y calcula su propia raíz de Merkle → Smart Contract de Soroban que solo acepta la firma de la cuenta selladora → la cuenta patrocinadora de GovTrace paga la comisión con fee bump |
| Publicación | Toda evidencia nace **Oculta**; el Admin publica una por una, rechaza con motivo o retira dejando lápida. Nada se borra físicamente |
| Verificación | Validador en el navegador (contextual, libre o con prueba adjunta) + script independiente; cada descarga incluye su prueba de inclusión |
| Privacidad | Coordenadas públicas aproximadas (~100 m), seudónimo del veedor, PDF sin metadatos; rostros y placas no se difuminan (riesgo aceptado) |
| Blockchain | **Stellar**, con Smart Contracts en **Soroban (Rust)**. Reemplaza EVM/Polygon desde el 2026-09-28 (el proyecto participa en Stellar Apex) |
| Red | Red local *standalone* de Stellar en Docker (Stellar CLI) para desarrollo; testnet de Stellar para pruebas y la prueba de humo; red principal (pubnet) en producción |
| Comisiones | Sin proveedores de relayer de terceros: el backend patrocina cada transacción con *fee bump* (Stellar lo soporta de forma nativa) desde una cuenta patrocinadora con XLM |
| Mapas | OpenStreetMap |

> **Pivote a Stellar (2026-09-28).** Las reglas de esta SPEC ya están redactadas para Stellar. Algunas historias posteriores de EPIC-003 y relacionadas todavía hablan de Polygon, POL, gas o relayer: US-020b, US-004, US-021, US-022, US-023, US-024, US-025, US-038-CFG y US-046-INT. **Ahí rige el ecosistema Stellar**, y cada una se ajusta, con sus Gherkin y criterios, al abrir su iteración. El inventario está en `specs/PLAN.md` → "Pivote a Stellar".

## Actores

| Actor | Rol |
|---|---|
| Super Administrador | Plataforma global: da de alta organizaciones, datos legales, parámetros, sincronización, cuenta patrocinadora (XLM) y costos |
| Administrador de Organización | Su equipo de veedores, su territorio, revisión y publicación de evidencias, fichas de obra |
| Veedor de Campo | Captura y envía reportes de las obras de su territorio, desde el teléfono |
| Verificador Público | Ciudadano o periodista **sin cuenta**: mapa, vista de obra, validador, descargas y datos abiertos |

## Historias de usuario

56 historias: 39 del discovery inicial y 17 de gap descubiertas en Completitud. Prioridad: **P1** = semanas 1-2 · **P2** = semanas 3-4 · **P3** = semana 5 o posterior.

| ID | Historia | Épica | Origen | Prioridad |
|---|---|---|---|---|
| US-001 | Como **Super Administrador** quiero **dar de alta una nueva organización (tenant) validando sus datos legales (NIT y nombre de la veeduría u ONG) y asignándole su subdominio** para **habilitar su acceso al sistema de forma controlada y prevenir suplantaciones o spam** | EPIC-009 | discovery_inicial | P1 |
| US-002 | Como **Super Administrador** quiero **asignar el usuario Administrador inicial de una organización aprobada** para **transferirle la autonomía en la gestión de sus propios veedores** | EPIC-009 | discovery_inicial | P1 |
| US-003a | Como **Super Administrador** quiero **suspender o reactivar una organización activa** para **bloquear o restablecer temporalmente su acceso ante impagos o revisiones de seguridad** | EPIC-009 | discovery_inicial | P2 |
| US-003b | Como **Super Administrador** quiero **dar de baja de forma definitiva a una organización** para **retirar del sistema a entidades inoperantes aplicando políticas de retención de datos** | EPIC-009 | discovery_inicial | P3 |
| US-004 | Como **Super Administrador** quiero **visualizar un reporte del consumo de transacciones y gas en Polygon agrupado por organización** para **controlar la rentabilidad y los costos de infraestructura del SaaS** | EPIC-009 | discovery_inicial | P3 |
| US-005 | Como **Administrador de Organización** quiero **invitar nuevos usuarios por correo electrónico asignándoles el rol de veedor de campo** para **conformar el equipo de supervisión local** | EPIC-006 | discovery_inicial | P1 |
| US-006 | Como **Administrador de Organización** quiero **desactivar el acceso a un veedor de campo** para **revocar sus permisos en caso de que deje la organización o cometa infracciones** | EPIC-006 | discovery_inicial | P2 |
| US-007 | Como **Administrador de Organización** quiero **actualizar el nombre de fantasía y el logo de la veeduría** para **mantener la identidad visual de su observatorio** | EPIC-009 | discovery_inicial | P2 |
| US-008 | Como **Veedor de Campo** quiero **crear un reporte de evidencia vinculado a un contrato público del SECOP (capturando las coordenadas GPS del dispositivo)** para **registrar una anomalía o avance de obra en terreno** | EPIC-002 | discovery_inicial | P1 |
| US-009 | Como **Veedor de Campo** quiero **adjuntar fotografías y documentos PDF con metadatos automáticos** para **respaldar visualmente la veeduría** | EPIC-002 | discovery_inicial | P1 |
| US-010 | Como **Veedor de Campo** quiero **consultar el listado y estado de mis propios reportes enviados y su hash de sellado en la blockchain** para **hacer seguimiento a lo que envié** | EPIC-002 | discovery_inicial | P2 |
| US-011 | Como **Super Administrador** quiero **actualizar el NIT y los datos legales de una organización desde el panel global, a solicitud formal de la organización (fusión, cambio de razón social)** para **mantener la validez legal de la organización sin abrir un vector de suplantación** | EPIC-009 | discovery_inicial | P1 |
| US-012 | Como **Administrador de Organización** quiero **configurar desde mi panel las ciudades y/o departamentos que vigila mi organización** para **que el mapa y las obras disponibles correspondan a nuestro territorio** | EPIC-009 | discovery_inicial | P1 |
| US-013 | Como **Sistema** quiero **ejecutar una tarea programada que consulte la API del SECOP II filtrando únicamente por las ciudades/departamentos que las organizaciones activas tienen configurados** para **mantener los contratos de interés actualizados sin consumir almacenamiento innecesario** | EPIC-001 | discovery_inicial | P1 |
| US-014 | Como **Super Administrador** quiero **visualizar un panel de salud de la sincronización (última ejecución exitosa, contratos insertados, errores/caídas de la API)** para **monitorear la estabilidad de la integración** | EPIC-001 | discovery_inicial | P2 |
| US-015 | Como **Administrador de Organización** quiero **ver un listado de los contratos sincronizados en mi territorio (ordenable por fecha o valor presupuestal)** para **planificar a qué obras debo enviar a mis veedores** | EPIC-001 | discovery_inicial | P1 |
| US-016 | Como **Veedor de Campo** quiero **buscar contratos activos dentro de mi territorio por palabras clave (nombre de la obra, contratista o número de proceso)** para **seleccionar ágilmente la obra exacta al subir mi evidencia** | EPIC-002 | discovery_inicial | P1 |
| US-017 | Como **Verificador Público** quiero **ver los datos clave del contrato oficial (entidad contratante, contratista, valor total, plazo y enlace al SECOP) en la vista de la obra** para **contrastar la magnitud de los fondos públicos con la evidencia ciudadana** | EPIC-004 | discovery_inicial | P1 |
| US-018 | Como **Veedor de Campo** quiero **que la aplicación guarde reportes y evidencias localmente cuando no tengo señal y los sincronice automáticamente al recuperar la conectividad** para **garantizar cero pérdida de datos** | EPIC-002 | discovery_inicial | P3 |
| US-019 | Como **Veedor de Campo** quiero **que el sistema detecte mis coordenadas y me sugiera primero las obras en un radio de proximidad (ej. 500 m)** para **minimizar el tiempo de búsqueda y evitar errores de asignación** | EPIC-002 | discovery_inicial | P3 |
| US-020a | Como **Sistema** quiero **desplegar el Smart Contract de sellado en Stellar (Soroban) con control de acceso (solo la cuenta selladora puede sellar)** para **que ningún tercero pueda registrar sellos falsos** | EPIC-003 | discovery_inicial | P1 |
| US-020b | Como **Sistema** quiero **encolar el hash SHA-256 validado de cada evidencia y registrarlo en el Smart Contract de Soroban, con la comisión patrocinada por GovTrace (fee bump)** para **garantizar la inmutabilidad sin fricción para el usuario** | EPIC-003 | discovery_inicial | P1 |
| US-021 | Como **Sistema** quiero **aplicar reintentos con retraso exponencial si el nodo RPC falla, hay congestión o la transacción queda pending/dropped** para **que ningún reporte quede huérfano de su sello** | EPIC-003 | discovery_inicial | P2 |
| US-022 | Como **Super Administrador** quiero **ver el saldo MATIC/POL de la billetera del Relayer y recibir alertas (Slack/Email) cuando caiga bajo un umbral** para **recargar fondos antes de que se detenga el sellado** | EPIC-003 | discovery_inicial | P3 |
| US-023 | Como **Veedor de Campo** quiero **ver en el detalle de mi evidencia confirmada un Recibo de Inmutabilidad (TxID, número de bloque y enlace a Polygonscan)** para **tener la prueba criptográfica independiente de que mi reporte no puede ser alterado** | EPIC-003 | discovery_inicial | P2 |
| US-024 | Como **Verificador Público** quiero **arrastrar una foto o documento a la herramienta de Validación para que mi navegador recalcule su SHA-256 y consulte el Smart Contract en Polygon** para **obtener un veredicto Auténtico/Alterado que no dependa de la base de datos de GovTrace** | EPIC-005 | discovery_inicial | P2 |
| US-025 | Como **Verificador Público** quiero **ver públicamente el Recibo de Inmutabilidad (TxID, bloque, timestamp de red y enlace a Polygonscan) junto a cada evidencia confirmada** para **auditar el registro en un explorador de bloques independiente** | EPIC-005 | discovery_inicial | P2 |
| US-026 | Como **Verificador Público** quiero **descargar el archivo de evidencia sellado (exactamente el que se hasheó: sanitizado en origen, sin recompresión ni marcas de agua)** para **someterlo a mi propio peritaje forense o usarlo como prueba legal** | EPIC-005 | discovery_inicial | P2 |
| US-027 | Como **Verificador Público** quiero **ver un mapa interactivo con pines de obras que cambian de color automáticamente según su estado (ej. rojo vencidas, amarillo suspendidas, verde terminadas)** para **detectar de un vistazo los proyectos en riesgo o abandono** | EPIC-004 | discovery_inicial | P2 |
| US-028 | Como **Verificador Público** quiero **filtrar los pines del mapa por estado, rango de presupuesto o municipio** para **enfocar mi análisis en los megaproyectos o en mi zona de interés** | EPIC-004 | discovery_inicial | P3 |
| US-029 | Como **Verificador Público** quiero **hacer clic en el pin de una obra y ver, además de los datos del contrato, una línea de tiempo cronológica con sus evidencias publicadas** para **comprobar la evolución o el estancamiento de la obra** | EPIC-004 | discovery_inicial | P2 |
| US-030 | Como **Veedor de Campo** quiero **aceptar la invitación y crear mi contraseña** para **activar mi cuenta de acceso** | EPIC-006 | discovery_inicial | P1 |
| US-031 | Como **usuario registrado** quiero **iniciar sesión** para **acceder a las herramientas de mi rol** | EPIC-006 | discovery_inicial | P1 |
| US-032 | Como **Sistema** quiero **traer solo los contratos de tipo 'Obra'** para **descartar contratos irrelevantes (ej. papelería)** | EPIC-001 | discovery_inicial | P1 |
| US-033 | Como **Sistema** quiero **sincronizar solo los contratos nuevos o modificados** para **no duplicar registros ni saturar la base de datos** | EPIC-001 | discovery_inicial | P1 |
| US-034 | Como **Sistema** quiero **ejecutar un Job diario que marque 'en riesgo' las obras cuya fecha de fin de contrato ya pasó (fecha actual vs fecha fin)** para **colorear su pin en el mapa** | EPIC-004 | discovery_inicial | P1 |
| US-035 | Como **Administrador de Organización** quiero **corregir la ubicación oficial de una obra, dejando registro del cambio** para **que una ubicación errónea no bloquee los reportes de mis veedores** | EPIC-004 | discovery_inicial | P1 |
| US-036 | Como **Administrador de Organización** quiero **revisar las evidencias ocultas y publicarlas** para **decidir qué se muestra en nuestro mapa público** | EPIC-004 | discovery_inicial | P1 |
| US-037 | Como **Administrador de Organización** quiero **retirar una evidencia publicada, dejando una lápida visible** para **cumplir nuestras políticas sin borrar el rastro** | EPIC-004 | discovery_inicial | P1 |
| US-038-CFG | Como **Super Administrador** quiero **ajustar desde el panel global los parámetros operativos (radio de geocerca, ventana de reporte de Terminados/Liquidados, vigencia de invitaciones, umbral de saldo del Relayer y hora de la sincronización)** para **adaptar la operación sin desplegar código** | EPIC-009 | analisis_completitud (⚡ Importante) | P2 |
| US-039-USR | Como **usuario registrado** quiero **restablecer mi contraseña yo mismo con un enlace que recibo por correo** para **recuperar el acceso sin depender de un administrador** | EPIC-006 | analisis_completitud (⚡ Importante) | P2 |
| US-040-USR | Como **Administrador de Organización** quiero **reenviar o revocar una invitación pendiente** para **corregir invitaciones enviadas por error o no atendidas** | EPIC-006 | analisis_completitud (💡 Mejora) | P3 |
| US-041-USR | Como **Administrador de Organización** quiero **reactivar a un veedor desactivado** para **reincorporarlo al equipo sin crear una cuenta nueva** | EPIC-006 | analisis_completitud (⚡ Importante) | P2 |
| US-042-SEC | Como **Administrador de Organización** quiero **autorizar en el sistema al Super Administrador para crear reportes o subir evidencias en nombre de mi organización** para **dar mi consentimiento de forma explícita y trazable (R-SA-02)** | EPIC-006 | analisis_completitud (⚡ Importante) | P2 |
| US-043-MON | Como **Super Administrador o Administrador de Organización** quiero **consultar el log de auditoría (el Super Administrador el de toda la plataforma, cada Administrador el de su organización)** para **saber quién hizo qué, cuándo y qué cambió** | EPIC-009 | analisis_completitud (⚡ Importante) | P2 |
| US-044-MON | Como **Super Administrador** quiero **recibir alertas de una herramienta de monitoreo externa sobre errores de la aplicación, caídas y disponibilidad** para **enterarme de las fallas técnicas antes que los usuarios** | EPIC-009 | analisis_completitud (⚡ Importante) | P2 |
| US-045-INT | Como **Administrador de Organización** quiero **agrupar varios contratos de tipo Obra en una misma ficha de obra** para **que una obra física con varias fases o reinicios se vea y se reporte como una sola** | EPIC-004 | analisis_completitud (⚡ Importante) | P2 |
| US-046-INT | Como **Verificador Público** quiero **usar un script de verificación independiente, publicado en el repositorio abierto, que compruebe un archivo con su prueba de inclusión solo contra Polygon** para **verificar aunque GovTrace no esté disponible** | EPIC-005 | analisis_completitud (⚡ Importante) | P2 |
| US-047-MNT | Como **Super Administrador** quiero **volver a encolar desde mi panel las evidencias en 'Falla de Sellado', una o varias a la vez** para **recuperar sellos fallidos sin intervención técnica fuera del sistema** | EPIC-003 | analisis_completitud (💡 Mejora) | P3 |
| US-048-MNT | Como **Sistema** quiero **archivar fuera de la base principal los contratos sin evidencias cerrados hace más de 5 años** para **contener el crecimiento de la base central** | EPIC-001 | analisis_completitud (⚡ Importante) | P2 |
| US-049-RPT | Como **Administrador de Organización** quiero **ver un resumen de mi territorio: obras por color, evidencias por clasificación y por mes, y veedores activos** para **entender el estado de nuestra veeduría de un vistazo** | EPIC-004 | analisis_completitud (⚡ Importante) | P2 |
| US-050-RPT | Como **Administrador de Organización** quiero **exportar las obras y evidencias de mi organización en CSV** para **analizarlas con otras herramientas** | EPIC-004 | analisis_completitud (💡 Mejora) | P3 |
| US-051-RPT | Como **Verificador Público** quiero **ver estadísticas públicas del territorio: obras en riesgo, evidencias por mes y contratos anulados con evidencias** para **dimensionar el problema sin revisar obra por obra** | EPIC-004 | analisis_completitud (💡 Mejora) | P3 |
| US-052-RPT | Como **Verificador Público** quiero **descargar datos abiertos (CSV/JSON) de las evidencias publicadas y sus sellos** para **auditar y reutilizar la información de forma independiente** | EPIC-005 | analisis_completitud (💡 Mejora) | P3 |
| US-053-RPT | Como **Super Administrador** quiero **ver un resumen de uso por organización: veedores activos y evidencias recibidas, publicadas, rechazadas y retiradas** para **seguir la adopción de la plataforma** | EPIC-009 | analisis_completitud (💡 Mejora) | P3 |
| US-054-RPT | Como **Super Administrador** quiero **recibir una alerta cuando una organización lleve 30 días sin actividad** para **detectar a tiempo organizaciones que abandonan la plataforma** | EPIC-009 | analisis_completitud (💡 Mejora) | P3 |

**Totales:** P1 = 21 · P2 = 22 · P3 = 13 (US-016 adelantada de P2 a P1 en `/plan` por dependencia funcional con US-008) · Total = 56.

**Épicas:** EPIC-001 Sincronización SECOP II · EPIC-002 Recolección de evidencia · EPIC-003 Sellado criptográfico · EPIC-004 Visualización geoespacial y publicación · EPIC-005 Verificación de integridad · EPIC-006 Cuentas y roles · EPIC-009 Gestión de organizaciones. Quedan **fuera del MVP**: EPIC-007 Moderación de reportes (reemplazada por la publicación manual de US-036/037) y EPIC-008 Radicación de denuncias ante la Contraloría. Detalle y puntajes VUIFED en `specs/epicas/`.

## Reglas de negocio

64 reglas: las explícitas del caso más las descubiertas en Historias, Criterios y Completitud. Cada una es una frase verificable, con las historias donde se aplica. *(infra)* y *(CI)* marcan reglas operativas que no se expresan como escenario Gherkin; se verifican con tareas de infraestructura o de integración continua (ver `sessions/cristhian-barros/dqs-lite.md`).

### Organizaciones y roles (Super Admin / Admin de Organización / Veedor)

- **R-SA-01** — No puede modificar, borrar ni falsificar hashes de evidencias o contratos ya sellados en Stellar — US-003b, US-020a
- **R-SA-02** — (precisada) El Super Admin solo crea reportes en una organización si su Admin lo autoriza en el sistema, con registro (US-042-SEC) — US-042-SEC
- **R-SA-03** — El subdominio de cada organización lo asigna el Super Administrador al darla de alta (US-001), evitando colisiones — US-001
- **R-TA-01** — No puede dar de alta otras organizaciones (exclusivo del Super Administrador) — US-005
- **R-TA-02** — No puede alterar ni borrar evidencias ya selladas en Stellar — US-037
- **R-TA-03** — No puede editar el NIT ni los datos legales de validación inicial; solo el Super Administrador, a solicitud formal — US-007, US-011
- **R-USR-01** — Un mismo correo puede ser veedor en varias organizaciones: una cuenta independiente por organización — US-005
- **R-USR-02** — El veedor ve 'Rechazada' con el motivo del Administrador (Rechazar exige motivo); 'En Revisión' = solo Oculto — US-010, US-036
- **R-USR-03** — Los reportes offline de un veedor cuya organización fue suspendida se conservan y se envían si se reactiva dentro de los 7 días — US-003a
- **R-VC-01** — No puede invitar, aprobar ni gestionar cuentas de otros usuarios de la organización — US-041-USR
- **R-VC-02** — No puede ver ni modificar reportes creados por otros veedores (en el MVP cada uno gestiona sus propios reportes) — US-010
- **R-VC-03** — No tiene acceso a la configuración ni a los reportes de costos de sellado de la organización — US-004, US-007
- **R-VC-04** — Solo puede reportar sobre obras dentro del territorio (ciudades/departamentos) configurado por su organización — US-008, US-012, US-016, US-019

### Contratos SECOP II

- **R-SEC-01** — Nadie (ni el Super Administrador) puede editar, modificar o borrar datos de un contrato descargado; SECOP es la única fuente de verdad y los errores del Estado se muestran tal cual — US-013, US-017, US-033, US-034, US-048-MNT
- **R-SEC-02** — No se consultan ni guardan contratos de territorios que no pertenezcan a ninguna organización activa — US-013, US-032
- **R-SEC-07** — Los estados de SECOP II se leen con una tabla de equivalencias y sin distinguir mayúsculas: siempre reportables "En ejecución", "Modificado", "Aprobado", "cedido" y "Suspendido" (las obras paralizadas son donde más importa la evidencia); reportables dentro de la ventana configurada "terminado" y "Cerrado"; nunca "Cancelado", "Borrador", "enviado Proveedor", "En aprobación" ni un estado desconocido. En ejecución (para "en riesgo") son "En ejecución" y "Modificado". El contrato conserva el texto de SECOP tal cual (R-SEC-01) — US-008, US-016, US-034

### Captura, geolocalización e integridad

- **R-GEO-01** — Al crear un reporte (US-008) se capturan las coordenadas GPS del dispositivo y se guardan en la Evidencia. Si la obra no tiene coordenadas (Spatial-Null), las del primer reporte fijan su ubicación (First-Touch Anchoring, patrón human-in-the-loop). La ubicación vive en una FICHA DE OBRA propia de GovTrace, separada del contrato SECOP (que queda intacto, R-SEC-01 se cumple) — US-008, US-035
- **R-HASH-01** — El SHA-256 de cada archivo se calcula en el teléfono en el momento de la captura (Web Crypto API) y se guarda junto al archivo en IndexedDB. Al recibirlo, el backend recalcula el hash y lo compara antes de encolar el sellado — US-009, US-018, US-020b

### Sellado en blockchain

- **R-BLK-01** — Ningún Veedor ni Verificador crea cuentas de Stellar, instala wallets (p. ej. Freighter), maneja llaves ni paga o ve comisiones en XLM. GovTrace patrocina cada transacción con *fee bump*: su cuenta patrocinadora paga la comisión completa, incluida la de recursos de Soroban. Si aparece un prompt criptográfico, es un fallo de UX — US-020b
- **R-BLK-02** — El Smart Contract de Soroban solo recibe [referencia de la obra (`BytesN<32>`), raíz de Merkle (`BytesN<32>`)]; la hora la pone la red (hora y número del ledger en que se selló), no el servidor. Nunca recibe imágenes, PDFs, nombres ni textos largos. La referencia de la obra es el SHA-256 de «organización:ficha», única entre organizaciones y sin datos legibles — US-020a, US-020b
- **R-BLK-03** — La función de sellado no es pública: exige la autorización (`require_auth`) de la cuenta selladora que el contrato fija al desplegarse; cualquier otra cuenta es rechazada por la red. El contrato no ofrece ninguna operación para modificar ni borrar sellos, ni para reemplazar su propio código (sin `upgrade`): lo desplegado es inmutable — US-020a
- **R-BLK-04** — La cuenta selladora (firma la invocación) y la patrocinadora (paga la comisión con fee bump, tiene los XLM) son distintas; no hay proveedores de relayer de terceros. Ninguna llave secreta está en el repositorio, en su historial ni en `.env.example`: se inyectan como variables de entorno al arrancar, desde un gestor de secretos (en CI, las credenciales de Jenkins). La patrocinadora es una **hot wallet**: tiene solo el saldo para unos días de sellos y la recarga con frecuencia una cuenta de tesorería que nunca toca el servidor. La tesorería también paga el despliegue del contrato y la extensión de su vigencia (instancia y código), así la hot wallet solo paga sellos (D12). Si alguien comprometiera el servidor, con la selladora solo podría registrar sellos que no coinciden con la base, y con la patrocinadora solo tomaría ese saldo chico. Antes de la red principal, la firma pasa a un servicio remoto Ed25519 que no entrega la llave (D11 de `specs/PLAN.md`) — US-020b
- **R-BLK-05** — Se sella una raíz de Merkle por reporte; cada archivo conserva su prueba de inclusión, que se publica para que el navegador del Verificador recomponga la raíz y la compare con la cadena — US-024
- **R-BLK-06** — Stellar confirma cada transacción de forma definitiva al cerrar el ledger (consenso SCP, sin reorganizaciones): un sello está "Sellado" cuando su transacción queda incluida con éxito en un ledger cerrado. No hacen falta confirmaciones adicionales ni auditorías de reorganización — US-020b

### Verificación pública

- **R-VER-01** — La validación (US-024) nunca envía el archivo al backend: el hash se calcula solo en la memoria del navegador (Web Crypto API) — US-024
- **R-VER-02** — Descargar evidencias y usar el validador no exige registro, inicio de sesión ni tokens — US-017, US-024, US-025, US-026, US-031, US-051-RPT, US-052-RPT

### Privacidad

- **R-PRIV-01** — El archivo crudo del sensor nunca se publica. La PWA purga los metadatos EXIF en el teléfono ANTES de calcular el SHA-256; lo que se sube, se sella y se publica es ese archivo sanitizado — US-009, US-026
- **R-PRIV-02** — Las coordenadas de cada evidencia se muestran al público aproximadas (~100 m), nunca exactas — US-029, US-050-RPT, US-052-RPT
- **R-PRIV-03** — En el JSON de metadatos sellado, el ID del veedor se reemplaza por un seudónimo antes de sellar; ese JSON se publica — US-020b, US-050-RPT, US-052-RPT
- **R-PRIV-04** — La app limpia los metadatos de los PDF (autor, software, fechas) antes de calcular el hash — US-009
- **R-PRIV-05** — No se difuminan rostros ni placas: se publican tal cual y la organización decide al revisar (US-036). Riesgo aceptado por el usuario — US-009
- **R-PRIV-06** — Las fotos se optimizan en el teléfono antes del hash: lado mayor 1920 px, JPEG, calidad 80 %; se sella el archivo optimizado — US-009

### Mapa y publicación

- **R-MAP-01** — No existe un mapa global que mezcle evidencias de varias organizaciones: cada tenant tiene su propio mapa público aislado en su subdominio y asume la responsabilidad curatorial y legal de sus pines — US-027, US-029, US-036, US-037, US-049-RPT, US-051-RPT
- **R-MAP-02** — La carga inicial del mapa solo devuelve [{id, lat, lng, color_pin}]; datos del contrato y línea de tiempo se piden bajo demanda al hacer clic en un pin — US-017, US-027, US-028, US-029

### Seguridad

- **R-SEC-03** — 5 intentos fallidos de inicio de sesión bloquean la cuenta 15 minutos — US-031, US-039-USR
- **R-SEC-04** — Los logos SVG se aceptan, pero se limpian de contenido ejecutable — US-007
- **R-SEC-05** — El servidor registra su hora de recepción y marca los reportes cuya hora de captura está más de 5 minutos en el futuro (tolerancia por latencia y desfase del reloj del teléfono) o es anterior a los 7 días de vigencia offline; la marca la ven el Admin de Organización y el Super Admin — US-008, US-036
- **R-SEC-06** — El servidor calcula su propia raíz de Merkle y las pruebas; ignora la raíz del teléfono — US-020b

### Configuración

- **R-CFG-01** — Red de sellado: Stellar. Red local *standalone* en Docker para desarrollo, testnet para pruebas y la prueba de humo, red principal (pubnet) al salir a producción — (infra)
- **R-CFG-02** — Fijos en el código: precisión GPS 50 m, archivos por reporte (5 fotos o 1 PDF, 10 MB), vigencia offline 7 días. Configurables por el Super Admin (globales): geocerca 500 m, ventana 12 meses, invitación 48 h, umbral de saldo de la cuenta patrocinadora (50 XLM — D12 de `specs/PLAN.md`), sincronización 02:00. Ninguno es configurable por organización — US-038-CFG

### Auditoría e integridad

- **R-AUD-01** — Organización suspendida: su mapa y evidencias publicadas siguen visibles y verificables, con aviso de 'organización suspendida' — US-003a
- **R-AUD-02** — Organización dada de baja: su mapa deja de estar en línea; sus evidencias siguen verificables en el validador libre — US-003b
- **R-AUD-03** — Retención: archivos de una organización dada de baja se conservan 5 años y luego se borran; el sello permanece — US-003b
- **R-AUD-04** — Log de auditoría (quién, cuándo, valor anterior y nuevo) para: ciclo de vida de organizaciones, NIT/datos legales, publicar/rechazar/retirar evidencias, corrección de ubicación, parámetros globales, invitar/desactivar/reactivar veedores, cambios de territorio, autorizaciones al Super Admin — US-038-CFG, US-040-USR, US-041-USR, US-042-SEC, US-043-MON, US-047-MNT
- **R-AUD-05** — Los reportes se validan con los parámetros (p. ej. radio de geocerca) vigentes en el momento de la captura — US-038-CFG
- **R-AUD-06** — (ajuste de 4B→4A por conflicto con R-SEC-02) Contratos de un territorio sin organizaciones activas se conservan pero dejan de actualizarse — US-013

### Monitoreo

- **R-MON-01** — Evidencia más de 2 h 'En Cola' → alerta al Super Admin y al Admin de la organización afectada — US-044-MON
- **R-MON-02** — Los reportes con hora sospechosa solo se marcan; decide el Admin al revisar — US-036

### Integraciones

- **R-INT-01** — Si la red de Stellar (su RPC) no responde, o la cuenta patrocinadora no tiene saldo, el sellado espera y reintenta (US-021) — US-021
- **R-INT-02** — Mapas con OpenStreetMap y teselas abiertas — US-027, US-035
- **R-INT-03** — Si el API de precios falla, el reporte de costos usa el último precio conocido con su fecha; el municipio SECOP se empareja con DIVIPOLA normalizado y lo no emparejado se descarta y reporta — US-004, US-013, US-014
- **R-INT-04** — Cada descarga incluye la prueba de inclusión para verificar solo contra Stellar, sin depender de GovTrace — US-024, US-026, US-046-INT
- **R-INT-05** — Una ficha de obra puede agrupar varios contratos de tipo Obra (lo decide el Admin de Organización) — US-045-INT

### Mantenimiento

- **R-MNT-01** — El validador consulta todas las direcciones históricas del Smart Contract; los sellos antiguos siguen verificables — US-024, US-046-INT
- **R-MNT-02** — Las pruebas de inclusión se conservan para siempre, aunque se borren los archivos — US-003b, US-026
- **R-MNT-03** — El log de auditoría se conserva para siempre; la tabla seudónimo→veedor, 5 años — US-043-MON
- **R-MNT-04** — Contratos sin evidencias y cerrados hace más de 5 años se archivan fuera de la base principal — US-048-MNT

### Backup y recuperación

- **R-BCK-01** — Pérdida máxima de datos aceptada (RPO): 1 hora — (infra)
- **R-BCK-02** — Tiempo máximo de recuperación (RTO): 4 horas — (infra)
- **R-BCK-03** — Los archivos de evidencia se respaldan periódicamente, igual que la base de datos — (infra)
- **R-BCK-04** — Las copias de respaldo se guardan 30 días — (infra)
- **R-BCK-05** — Una restauración de prueba antes de salir a producción (ajuste tras nota asesor; sin prueba mensual) — (infra)

### Estrategia de pruebas

- **R-TST-01** — Sellado: el Smart Contract se prueba con el entorno de pruebas de Soroban (`cargo test`); el backend, contra la red local *standalone*; y hay una prueba de humo contra la testnet de Stellar antes de cada salida a producción — (CI)
- **R-TST-02** — SECOP II: pruebas con respuestas grabadas (fixtures), incluidas variantes raras de nombres de municipio — (CI)
- **R-TST-03** — Captura y modo offline: pruebas de componentes (Vitest) + pruebas de extremo a extremo en navegador real simulando pérdida de señal — (CI)
- **R-TST-04** — Toda regla de comportamiento tiene su escenario automático que la viola a propósito; las reglas operativas (respaldo, red, estrategia de pruebas) tienen una verificación de infraestructura o CI (redactada de nuevo en /plan) — (CI)
## Endpoints

> **Esbozo** derivado de las historias. Nombres y verbos se afinan en `/plan`. Con Inertia, varias rutas serán páginas y no API JSON. Tres zonas: **central** (dominio global, Super Administrador), **tenant** (subdominio de cada organización, con sesión) y **pública** (subdominio, sin sesión).

### Central — Super Administrador

| Método | Ruta | Descripción | Quién | Historia |
|---|---|---|---|---|
| POST | `/admin/organizations` | Alta de organización (NIT con DV, nombre, subdominio) | Super Admin | US-001 |
| POST | `/admin/organizations/{id}/admin` | Asignar Administrador inicial | Super Admin | US-002 |
| PATCH | `/admin/organizations/{id}/legal` | Actualizar NIT y datos legales | Super Admin | US-011 |
| POST | `/admin/organizations/{id}/suspend` · `/reactivate` | Suspender o reactivar | Super Admin | US-003a |
| POST | `/admin/organizations/{id}/decommission` | Baja lógica con doble confirmación | Super Admin | US-003b |
| GET | `/admin/sync/health` | Panel de salud de la sincronización SECOP | Super Admin | US-014 |
| GET | `/admin/reports/gas` | Consumo de gas y costo por organización y mes | Super Admin | US-004 |
| GET | `/admin/relayer/balance` | Saldo del Relayer | Super Admin | US-022 |
| GET · PUT | `/admin/settings` | Parámetros operativos configurables | Super Admin | US-038-CFG |
| GET | `/admin/audit-log` | Log de auditoría de toda la plataforma | Super Admin | US-043-MON |
| POST | `/admin/evidences/requeue` | Volver a encolar fallas de sellado | Super Admin | US-047-MNT |
| GET | `/admin/usage` | Resumen de uso por organización | Super Admin | US-053-RPT |

### Tenant — con sesión, en el subdominio de la organización

| Método | Ruta | Descripción | Quién | Historia |
|---|---|---|---|---|
| POST | `/login` | Inicio de sesión (bloqueo tras 5 intentos) | Admin Org, Veedor | US-031 |
| POST | `/password/forgot` · `/password/reset` | Restablecer contraseña | Admin Org, Veedor | US-039-USR |
| POST | `/invitations` | Invitar veedor | Admin Org | US-005 |
| POST | `/invitations/{id}/resend` · `/revoke` | Reenviar o revocar invitación | Admin Org | US-040-USR |
| POST | `/invitations/{token}/accept` | Aceptar invitación y crear contraseña | Veedor | US-030 |
| POST | `/observers/{id}/deactivate` · `/reactivate` | Desactivar o reactivar veedor | Admin Org | US-006, US-041-USR |
| PATCH | `/organization/profile` | Nombre de fantasía y logo | Admin Org | US-007 |
| PUT | `/organization/territory` | Territorio (códigos DIVIPOLA) | Admin Org | US-012 |
| GET | `/contracts` | Listado paginado del territorio | Admin Org | US-015 |
| GET | `/contracts/search?q=` | Búsqueda de obras seleccionables | Veedor | US-016 |
| GET | `/worksites/nearby?lat=&lng=` | Hasta 5 obras ancladas a menos de 500 m | Veedor | US-019 |
| POST | `/reports` | Crear reporte: archivos, hashes, lat/lng/hora de captura, clasificación y comentario | Veedor | US-008, US-009, US-018 |
| GET | `/me/reports` · `/me/reports/{id}/receipt` | Mis reportes y Recibo de Inmutabilidad | Veedor | US-010, US-023 |
| GET | `/inbox` | Bandeja de evidencias ocultas | Admin Org | US-036 |
| POST | `/evidences/{id}/publish` · `/reject` · `/retire` | Publicar, rechazar (con motivo) o retirar (con motivo) | Admin Org | US-036, US-037 |
| PATCH | `/worksites/{id}/location` | Corregir la ubicación oficial | Admin Org | US-035 |
| POST | `/worksites` | Agrupar contratos en una ficha de obra | Admin Org | US-045-INT |
| POST · DELETE | `/authorizations/super-admin` | Autorizar o revocar al Super Admin (30 días) | Admin Org | US-042-SEC |
| GET | `/audit-log` | Log de auditoría de la organización | Admin Org | US-043-MON |
| GET | `/summary` · `/export.csv` | Resumen del territorio y exportación CSV | Admin Org | US-049-RPT, US-050-RPT |

### Pública — sin sesión, en el subdominio de la organización

| Método | Ruta | Descripción | Quién | Historia |
|---|---|---|---|---|
| GET | `/map/pins` | Pines livianos `[{id, lat, lng, color_pin}]` con filtros | Cualquiera | US-027, US-028 |
| GET | `/worksites/{id}` | Contrato(s) y línea de tiempo de evidencias publicadas (bajo demanda) | Cualquiera | US-017, US-029 |
| GET | `/evidences/{id}/receipt` | Recibo de Inmutabilidad público | Cualquiera | US-025 |
| GET | `/evidences/{id}/download` | Archivo sellado exacto + prueba de inclusión | Cualquiera | US-026 |
| GET | `/proofs/{sha256}` | Prueba de inclusión de un hash; el archivo nunca se envía | Cualquiera | US-024 |
| GET | `/stats` | Estadísticas públicas del territorio | Cualquiera | US-051-RPT |
| GET | `/open-data.csv` · `/open-data.json` | Datos abiertos de evidencias publicadas | Cualquiera | US-052-RPT |

### Procesos sin endpoint

Son tareas programadas y trabajos de cola:
- Sincronización SECOP a las 02:00, e inmediata al activar una organización o cambiar su territorio (US-013, US-032, US-033).
- Cálculo diario de obras en riesgo (US-034).
- Sellado y reintentos (US-020b, US-021).
- Auditoría nocturna de reorganizaciones (US-020b).
- Saldo del Relayer cada 15 minutos (US-022).
- Alerta de cola estancada (US-021).
- Archivado mensual (US-048-MNT).
- Inactividad a 30 días (US-054-RPT).

A esto se suma el script de verificación independiente del repositorio (US-046-INT).

## Pantallas (Mobile-First)

Regla común: el diseño sin prefijo es el del teléfono y solo `md:` / `lg:` escalan. Los botones son de al menos 44×44 px. Toda pantalla contempla los estados **carga / error / vacío / éxito**, con los mensajes exactos de `specs/criterios/`.

### App del Veedor (PWA, uso en la calle — prioridad móvil absoluta)
- **Mis Reportes** (US-010, US-018, US-023):
  - lista de reportes con estado técnico y editorial por separado;
  - bandeja de salida con contador de pendientes;
  - vacío: sin reportes;
  - detalle con el Recibo, o el mensaje "en proceso de sellado".
- **Nuevo Reporte** (US-008, US-009, US-016, US-019): el flujo es buscar la obra o elegir una cercana → pedir el GPS (con reintento si la precisión supera 50 m) → elegir la clasificación → escribir el comentario opcional → adjuntar 1 a 5 fotos o 1 PDF. La optimización, la limpieza del EXIF y el hash ocurren en el teléfono. Errores: GPS denegado, fuera de la geocerca, almacenamiento lleno. Éxito: "Reporte recibido…" o "Sin conexión. Reporte guardado…".
- **Activar cuenta / Iniciar sesión / Restablecer contraseña** (US-030, US-031, US-039-USR).

### Panel del Administrador de Organización (móvil primero, cómodo en escritorio)
- **Bandeja de entrada** (US-036, US-037): una evidencia a la vez, con la marca de hora sospechosa; publicar, rechazar con motivo o retirar con motivo.
- **Equipo** (US-005, US-006, US-040-USR, US-041-USR) · **Territorio** con buscador DIVIPOLA (US-012) · **Contratos** en tabla paginada de 20 (US-015) · **Ficha de obra**: corregir ubicación en el mapa, agrupar contratos (US-035, US-045-INT).
- **Configuración**: nombre y logo (US-007) · **Resumen y exportación** (US-049-RPT, US-050-RPT) · **Log de auditoría** (US-043-MON) · **Autorización al Super Admin** (US-042-SEC).
- **Banners:** fallas de sellado y cola estancada (US-021).

### Panel global del Super Administrador (escritorio aceptable, sin romper en móvil)
- **Organizaciones**: alta, admin inicial, datos legales, suspender, reactivar y dar de baja (US-001, US-002, US-011, US-003a, US-003b).
- **Salud SECOP** (US-014) · **Costos de gas** (US-004) · **Relayer** (US-022) · **Parámetros** (US-038-CFG) · **Uso** (US-053-RPT) · **Log** (US-043-MON) · **Re-encolar fallas** (US-047-MNT).

### Sitio público de cada organización (sin sesión)
- **Mapa** (US-027, US-028):
  - pines por color y filtros;
  - vacío: "No se encontraron obras…";
  - aviso de organización suspendida (R-AUD-01).
- **Vista de obra** (US-017, US-029, US-025, US-026): tarjeta del contrato (con badge de anulado) y línea de tiempo con miniaturas, visor, lápidas, Recibo y descarga.
- **Validador** (US-024), con tres modos:
  - contextual, con resultado Auténtico o Alterado;
  - libre, con resultado Auténtico o No encontrado;
  - con prueba adjunta.

  Además, estados de archivo mayor a 10 MB y de error de conexión con la red de Stellar.
- **Estadísticas y datos abiertos** (US-051-RPT, US-052-RPT).

## Criterios de aceptación (YAML)

Ver `specs/criterios/`: un archivo YAML por historia (56), con camino feliz, validaciones, errores con su mensaje exacto, requisitos de UX y casos límite, y `validacion_smart` completo en todos. La especificación ejecutable está en `features/`: 56 archivos, 248 escenarios.

## Cobertura (DQS-lite)

Resumen de `sessions/cristhian-barros/dqs-lite.md`:

- **Las 10 áreas del ciclo de vida fueron exploradas.** SEC es la más profunda. BCK queda floja en verificabilidad: son objetivos operativos, sin escenario Gherkin.
- **Balance camino feliz / negativo:** 136 de los 248 escenarios violan una regla a propósito, y ningún `.feature` tiene solo caminos felices. Todos los mensajes exactos de los criterios aparecen literalmente en los escenarios.
- **Reglas sin escenario Gherkin** (a resolver en `/plan` como tareas de infraestructura o CI): R-BCK-01..04, R-CFG-01, R-INT-02, R-MNT-03 y R-TST-01..03. Esto choca con R-TST-04.
- **Riesgos aceptados conscientemente:**
  - rostros y placas sin difuminar (R-PRIV-05);
  - teléfono comprometido, solo mitigado con la marca de hora sospechosa (R-SEC-05).
- **Resuelto en `/plan`:** R-TST-04 redactada de nuevo; US-016 adelantada a P1. La carga real por fase está en `specs/PLAN.md`.
