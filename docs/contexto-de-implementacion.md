# Contexto de implementación

Este documento es para quien decide el producto y necesita entender lo que está construido, sin leer el código. Responde cinco preguntas:

1. qué se construyó y cómo se trabajó;
2. qué decisiones técnicas le dan forma al producto, y qué implica cada una;
3. dónde vive cada función del producto;
4. qué está frágil o conviene vigilar;
5. qué espera una decisión de producto, y cómo pedir un cambio.

Complementa a [`docs/arquitectura.md`](arquitectura.md), que es el mapa técnico: carpetas, capas, el recorrido de un reporte, el contrato y las pruebas. Aquí está el porqué y el estado. Al 2026-10-15, sobre la iteración 60.

## 1. Qué se construyó

GovTrace recibe la evidencia que los veedores toman en las obras públicas, la sella en la red Stellar y la publica en el mapa de cada veeduría. Cualquiera puede comprobar después que una foto no cambió, sin confiar en GovTrace.

**En cifras (2026-10-14):**

| | |
|---|---|
| Historias de usuario | 67, en 9 épicas, cada una con su `.feature` |
| Escenarios Gherkin | 466, cada uno con una prueba que lleva su nombre |
| Código | ~18 200 líneas de PHP en `app/` y ~8 800 de Vue y JavaScript |
| Pruebas | ~20 500 líneas de PHP y ~9 800 de JavaScript: Pest 1219, Vitest 703, e2e 58 |
| Base de datos | 35 migraciones de la base central y 28 de la base de cada veeduría |
| Iteraciones | De la 1 a la 60 en `specs/PLAN.md`, muchas con partes a, b, c… Todas cumplidas salvo la 37b (espera producción), la tercera parte de la 52 (paginar lo que falta) y la 57, que es una propuesta |
| Entornos | Local con Docker, staging en AWS sobre testnet (`govtrace.duckdns.org`). La red principal espera la it. 37b |

**Lo que puede hacer cada rol hoy** (detalle en [`docs/funciones-por-rol.md`](funciones-por-rol.md)):

- **Super Administrador:** da de alta las veedurías, con la validación asistida en el RUES; ajusta los parámetros; vigila el sellado, sus costos y la salud de SECOP; consulta la auditoría.
- **Administrador de la veeduría:** invita veedores y otros administradores, define el territorio, revisa la Bandeja (publica, rechaza o retira cada evidencia), corrige ubicaciones, agrupa contratos en una obra, descarga el expediente para la Contraloría y atiende los informes de los ciudadanos.
- **Veedor:** desde el celular, elige la obra de su municipio, toma la foto en el lugar y la envía. Sin señal, la guarda y la envía al volver la señal.
- **Ciudadano, sin cuenta:** ve el mapa (al tocar un pin, la tarjeta de la obra con sus miniaturas, desde la it. 60) y la lista de obras, comprueba una evidencia, informa a la veeduría (también de una obra que nadie ha visitado, desde la it. 58) y descarga los datos abiertos.

## 2. Cómo se trabajó

El repositorio sigue el flujo de `CLAUDE.md`:

```
discovery (BDD 2.0) → specs/ y features/ → PLAN.md → pruebas primero → código → PR → CI → main
```

- **Las decisiones de producto las tomó el dueño del producto.** Las técnicas se propusieron en `specs/PLAN.md`, con su porqué y sus alternativas, y se aplicaron solo después de su aprobación. Cada aprobación queda citada en el plan con su fecha.
- **Cada cambio es una iteración:** primero se enmienda o se escribe la historia (`specs/criterios/*.yaml` y `features/*.feature`), después las pruebas, que se ven fallar, y al final el código. Un commit por iteración, una rama y un PR.
- **Nada entra a `main` sin el pipeline en verde** ("CI completo", unos 10 minutos en GitHub Actions). Antes de cada commit, un hook prueba lo que cambió.
- **El modelo por iteración:** las de riesgo alto (sellado, concurrencia, datos entre bases) se hicieron con el modelo más capaz y más esfuerzo de razonamiento; las demás, con uno más rápido. Cada iteración del plan dice cuál.

**Dónde está escrito cada porqué:**

| Qué | Dónde |
|---|---|
| Las reglas del negocio (R-GEO-01, R-PRIV-05…) | `specs/SPEC.md` |
| Cada iteración: por qué, qué se decidió, cómo quedó y qué encontró | `specs/PLAN.md` |
| Las decisiones de arquitectura (D1 a D14) | El inicio de `specs/PLAN.md` |
| Los vacíos de cada rol (V1 a V21) y cuáles siguen abiertos | `docs/mapa-funcional.md` |
| La bitácora del discovery | `sessions/<slug>/discovery-log.md` |
| El análisis legal y de presupuesto | `docs/viabilidad-legal.md` |

## 3. Las decisiones que le dan forma al producto

Cada una tiene consecuencias que se ven en las pantallas. Conviene tenerlas presentes al proponer algo nuevo.

### 3.1 Cada veeduría es un mundo aparte

**La decisión:** cada veeduría tiene su propia base de datos y su subdominio (`<slug>.govtrace…`), con Stancl Tenancy. La base central guarda lo que todas comparten: los contratos de SECOP, los municipios, los parámetros, la auditoría y el turno de sellado.

**Lo que implica:**
- **No hay un mapa global** (R-MAP-01): cada veeduría responde por los pines de su mapa. El Inicio solo es un directorio que lleva a cada uno.
- **La misma obra puede estar en dos veedurías**, cada una con su ficha y su propia ubicación. Corregirla en una no toca la otra.
- **Dos veedurías pueden vigilar el mismo territorio:** nada lo impide, y en Medellín pasará. Por eso, desde la it. 59, el Inicio sugiere la veeduría con reglas públicas, sin elegir por el ciudadano. Como las fichas viven en cada base, un resumen en la base central (`contract_followers`) dice quién sigue qué contrato.
- **Una cuenta vale en una sola veeduría:** el mismo correo puede ser veedor en dos, con dos cuentas (R-USR-01), y la sesión vale solo en el subdominio donde se abrió.

### 3.2 La blockchain es invisible

**La decisión:** Stellar con un contrato inteligente en Soroban (Rust). Se pasó de Polygon a Stellar el 2026-09-28, por Stellar Apex. Explicado para quien no conoce blockchain en [`docs/sellado-en-stellar.md`](sellado-en-stellar.md).

**Lo que implica:**
- **El veedor no ve nada de Stellar.** No tiene billetera ni paga: tres cuentas de GovTrace lo hacen por él.
  - la **tesorería** (fría, fuera del servidor) crea las otras dos, despliega el contrato y lo renueva;
  - la **selladora** firma cada sello, sin fondos;
  - la **patrocinadora** paga las comisiones.
- **Se sella una sola huella por reporte:** la raíz de un árbol de Merkle con sus archivos y un JSON de contexto, donde el veedor va con un seudónimo. En la red no queda ningún dato legible.
- **El contrato no se puede cambiar ni actualizar.** Ni el operador puede borrar o modificar un sello. Cambiar la cuenta selladora es desplegar otro contrato.
- **Cuesta dinero, y el dinero es compartido.**
  - Cada sello cuesta unos 0,24 a 0,27 XLM, medido en testnet.
  - La vigencia del contrato se renueva cada ~180 días, por unos 27 XLM, con un aviso 30 días antes.
  - La patrocinadora es una sola para todas las veedurías. El sellado se pausa bajo 2 XLM, sin perder nada, y avisa bajo 50 XLM. La it. 57 propone un cupo por veeduría.
- **Hay un límite de velocidad.** Stellar admite una transacción pendiente por cuenta, así que la selladora sella por turnos: unos 10 a 12 sellos por minuto. Una ráfaga se sella en orden.

### 3.3 La evidencia nace oculta

**La decisión:** toda evidencia sellada nace "Oculta". Solo el Administrador de su veeduría la publica, la rechaza o la retira (US-036, US-037).

**Lo que implica:**
- **Lo publicado es exactamente lo sellado:** el mismo archivo, byte a byte, con su prueba de inclusión.
- **Retirar no borra.** El sello sigue en la red, y en el mapa queda una lápida con la fecha y el motivo.
- **El estado de una obra es una alerta de GovTrace,** no la decisión de una autoridad (US-055-LEG). Sale de la evidencia publicada más reciente y de las fechas del contrato en SECOP.

### 3.4 La privacidad se resuelve en el celular

**La decisión:** lo sensible se quita antes de calcular la huella, en el teléfono. Así lo sellado y lo publicado ya están limpios.

**Lo que implica:**
- **Los rostros se difuminan en el celular** (R-PRIV-05): el original nunca sale del teléfono. La persona revisa la foto ya difuminada y puede difuminar más a mano.
- **Las fotos salen sin EXIF** (sin el GPS ni el modelo del teléfono), y los PDF sin metadatos.
- **El mapa público muestra la ubicación aproximada,** a unos 100 m (R-PRIV-02).
- **El veedor va con un seudónimo** en lo sellado y en los datos abiertos (R-PRIV-03).
- **El correo del ciudadano se guarda cifrado:** la veeduría le responde sin verlo (R-LEG-10).
- **Las direcciones no se pueden recorrer:** las URL llevan un identificador público aleatorio, nunca el número interno (it. 46c).

### 3.5 SECOP II no trae dónde queda la obra

**La decisión:** como SECOP II no tiene coordenadas, la ubicación de una obra la pone la primera persona que la reporta desde el lugar (First-Touch, R-GEO-01).

**Lo que implica:**
- **El primer reporte la fija** solo si se tomó a menos de 30 km de la cabecera del municipio y con un GPS de 20 m o menos. Si no, la ubicación queda por confirmar en la Bandeja.
- **Después, cada reporte debe hacerse dentro de la geocerca** de la obra (500 m por defecto). El Administrador puede corregir la ubicación, y queda en la auditoría.
- **Desde la it. 58, un ciudadano puede proponer dónde queda** una obra sin ubicación. No la fija: la confirma el Administrador.
- **Lo que se reporta es lo que SECOP llama "Obra".** SECOP clasifica a veces así contratos que no lo son: en el Magdalena se vieron varios de prestación de servicios del ICBF.

### 3.6 Los parámetros tienen memoria

**La decisión:** un parámetro (la geocerca, la vigencia de una invitación, los meses para reportar un contrato cerrado…) nunca se edita: se agrega una versión con su fecha (R-AUD-05).

**Lo que implica:** un reporte se juzga con el valor que regía cuando se tomó, no con el de hoy. Cambiar un parámetro nunca vuelve inválido algo ya recibido.

### 3.7 Se puede comprobar sin GovTrace

**La decisión:** la verificación no depende del servidor de GovTrace.

**Lo que implica:**
- **El validador del navegador** calcula la huella del archivo en el equipo de quien verifica, sin subirlo, y la busca en la red Stellar.
- **El verificador independiente** (`tools/verify`, Node sin dependencias) hace lo mismo desde cualquier computador, con el archivo y su `.prueba.json`.
- **Si GovTrace desapareciera,** los sellos seguirían en la red y se podrían comprobar.

### 3.8 Funciona sin señal

**La decisión:** la app del veedor es una PWA con una bandeja de salida en el celular.

**Lo que implica:** un reporte sin señal se guarda con su lugar y su hora congelados (hasta 10 reportes, 50 MB y 7 días) y se envía solo al volver la señal. Lo que todavía no funciona sin señal es **elegir la obra**: la lista viene del servidor (V20, se decide con el piloto).

## 4. Dónde vive cada función del producto

Para conversar un cambio con quien programa, sirve saber qué pieza lo resuelve. Por épica:

| Épica | Qué hace | Dónde está |
|---|---|---|
| **EPIC-001 SECOP II** | Trae cada noche los contratos de obra del territorio vigilado. Muestra la salud de la sincronización. Archiva los viejos sin evidencia. | `SyncSecopContracts` (trabajo), `ProcessSecopContractRow`, `SecopClient`, `SecopHealth`; pantalla `SuperAdmin/SecopHealth` |
| **EPIC-002 Evidencia** | El veedor elige la obra, lee el GPS, toma la foto (con rostros difuminados) y envía. Sin señal, la bandeja de salida. | `CreateReport` (el corazón), `BrowseReportableContracts`, `NearbyWorksites`; pantallas `Veedor/NewReport`, `WorksiteBrowser`, `EvidencePicker`, `PhotoReview`; `lib/evidence/`, `lib/outbox.js`, `public/sw.js` |
| **EPIC-003 Sellado** | Encola, sella por turnos, reintenta, confirma, avisa del saldo y del vencimiento del contrato. | `PrepareReportSeal`, `SealReport`, `ConfirmSeal`, `StellarSealingNetwork`, `SealerTurn`, `SealingPause`; contrato en `contracts/sealing/src/lib.rs`; pantalla `SuperAdmin/Sealing` |
| **EPIC-004 Mapa** | El mapa y la lista de obras por veeduría, el color de cada pin, la tarjeta de una obra con sus miniaturas, la página de una obra, la Bandeja, el resumen y las estadísticas. | `PublicMap`, `PublicWorksiteCard`, `EvidenceThumbnails`, `PublicWorksiteView`, `PublicTimeline`, `WorksiteCondition`, `CalculateWorksitesAtRisk`, `EditorialDecisions`, `PublicCache`; pantallas `Public/Map`, `WorksiteCard`, `Public/Worksite`, `Admin/Inbox`, `Admin/Worksites` |
| **EPIC-005 Verificación** | El recibo de cada sello, la descarga con su prueba, el validador, el verificador independiente y los datos abiertos. | `SealReceipt`, `InclusionProof`, `OpenData`; `lib/validator.js`, `lib/merkle.js`; `tools/verify/`; pantalla `Public/Validator` |
| **EPIC-006 Cuentas** | Invitaciones, contraseña, inicio de sesión con bloqueo, impedimentos del veedor, política de datos, verificación en dos pasos del Super Administrador. | `app/Application/Auth`, `app/Application/Organization` (invitar, aceptar, desactivar), `DataPolicy`; pantallas `Auth/*`, `Veedor/Declaration`, `Public/Privacy` |
| **EPIC-007 Moderación** | Publicar, rechazar y retirar, con motivo y auditoría. | `EditorialDecisions`; pantalla `Admin/Inbox` |
| **EPIC-008 Denuncia** | El estado como alerta, los canales de la Contraloría, el expediente de una obra y los informes de los ciudadanos. | `WorksiteDossier` y sus plantillas en `resources/views/dossier/`; `CitizenReportDesk`, `CitizenReportInbox`, `UnlocatedWorksites`, `WorkFinder`, `ContractFollowers`; pantallas `Admin/CitizenReports`, `Public/Contract`, el buscador del Inicio (`WorkFinder.vue`) |
| **EPIC-009 Organizaciones** | Alta (también por solicitud pública), suspensión, baja, territorio, parámetros, auditoría, uso y costos. | `app/Application/Organization` (unos 30 casos), `Parameters`, `AuditLog`, `SealingCosts`; pantallas `SuperAdmin/*`, `Admin/Territory`, `Admin/Organization`, `Admin/Audit` |

Las rutas, comentadas por historia, están en `routes/tenant.php` (cada veeduría) y `routes/web.php` (el dominio central). Las tareas programadas, con su hora en Colombia, en `routes/console.php`.

## 5. Cómo se llegó aquí

Del 2026-09-27 al 2026-10-14, en este orden. Cada fase está en `specs/PLAN.md`, con su detalle.

| Fase | Iteraciones | Fechas | Qué le dejó al producto |
|---|---|---|---|
| **P1 · El núcleo** | 1 a 19 | 09-27 a 09-28 | Organizaciones, cuentas, SECOP, fichas de obra, el reporte con GPS y geocerca, el contrato de sellado, el sellado en testnet, la publicación, la PWA del veedor y los paneles básicos. En medio, el pivote a Stellar. |
| **P2 · Verificación y operación** | 20 a 29 | 09-28 a 09-29 | El ciclo de vida de cuentas y organizaciones, los parámetros, el recibo y el verificador independiente, el sitio público con su mapa y el validador. |
| **P3 · Campo y reportes** | 30 a 35 | 09-29 | Sin señal, obras cercanas, costos del sellado, la baja de una organización, datos abiertos y respaldos. |
| **Cierre tras la auditoría** | 36, 37a | 09-29 | Auditoría completa e inmutable, y todo lo de la salida a la red principal que no necesita datos de producción. |
| **Usable y demostrable** | 38 a 43 | 09-29 a 10-01 | `make demo`, el buzón de desarrollo, sellar varios a la vez, la accesibilidad para personas mayores (letra, botones, contraste), la identidad visual, el stack de producción, los flujos completos de cada rol y las solicitudes de alta. |
| **El proceso formal** | 44 | 09-30 | El estado como alerta y los canales de la Contraloría, el expediente, los impedimentos, la veeduría sin NIT, la política de datos y los informes ciudadanos. |
| **Lo pendiente de antes** | 45 | 09-30 a 10-02 | La hora de Colombia, los correos de usted, los datos personales de SECOP, el S3 que guarda en disco, la ubicación del veedor fuera de los registros. |
| **Seguridad y privacidad** | 46a a 46j | 10-02 a 10-06 | Varios Super Administradores, la validación en el RUES, los identificadores públicos, la auditoría con filtros, los rostros difuminados, la primera ubicación con guardas, la verificación en dos pasos y el expediente para la Contraloría. |
| **Staging y campo** | 42b, 42c, 47, 48 | 10-09 a 10-10 | Staging en AWS, desplegar sin cortes, encontrar la obra en el municipio del veedor y revisar los rostros más rápido. |
| **Calidad y rendimiento** | 49 a 56 | 10-10 a 10-11 | El pipeline en cada PR, la prueba de carga, el subdominio desde el nombre, todo paginado, el punto aproximado explicado, pantallas separadas, la Bandeja con la ubicación corregida y la caché de lo público. |
| **El ciudadano** | 58 a 60 | 10-14 a 10-15 | Informar de cualquier obra del territorio y proponer dónde queda. El Inicio empieza por la obra y sugiere la veeduría. La tarjeta de la obra al tocar un pin, con miniaturas. |
| **Staging más barato y más seguro** | 61 | 10-15 | Duerme de 21:00 a 8:00, con un presupuesto sin créditos y una alerta de gastos fuera de lo normal; buckets solo por HTTPS y la máquina protegida contra la terminación. |

**Propuesta, en espera de aprobación:** la 57 (el cupo de sellado por veeduría).

## 6. Lo que está frágil o conviene vigilar

| Qué | Por qué importa | Qué hacer |
|---|---|---|
| **Las pruebas de backend usan la base de desarrollo** | `make test` borra todas las veedurías al terminar, también la de `make demo`. Dos corridas a la vez se borran una a otra. | Volver a correr `make demo` antes de una presentación. Nunca correr dos suites a la vez, ni el e2e mientras corre Pest. |
| **El e2e necesita la red local de Stellar** | `make up` no la levanta. Sin ella, los sellos se quedan en "En Cola" y la prueba de los rostros falla. | Levantarla como lo hace `make demo` (ver `docker/demo/demo.sh`). |
| **Una sola patrocinadora para todas** | Una veeduría que sella de más agota el saldo de todas. | La alerta de 50 XLM existe. El cupo por veeduría es la it. 57. |
| **El contrato se renueva cada ~180 días** | Si nadie lo renueva, los sellos se archivan. La red puede restaurarlos, pero falta confirmar que el RPC de la red principal devuelve los sellos archivados. | El aviso llega 30 días antes. La confirmación del RPC va en la it. 37b. |
| **La calidad de los datos de SECOP II** | Contratos mal clasificados como "Obra"; el supervisor está en ~70 % de los contratos; EPM no está en el conjunto de datos. Si datos.gov.co cambia el conjunto, la sincronización se rompe. | El panel de salud de SECOP lo muestra. Decidir si se filtra lo mal clasificado. |
| **El costo de cada petición pública** | En staging, la máquina atiende unas 19 peticiones por segundo. Lo caro es identificar la veeduría y conectar a su base (~70 ms medidos en local), no armar el mapa. | La caché de lo público (it. 56) está; falta medir su efecto en staging. El siguiente paso es cachear la identificación de la veeduría. |
| **Los rostros en celulares de gama media** | La revisión de cada foto tarda en un celular lento, y un rostro muy pequeño (menos de unos 48 px) se escapa. Por eso existe el difuminado a mano. | `make faces-bench` lo mide con la CPU frenada. |
| **El resumen central de quién sigue qué obra** | Las sugerencias del Inicio salen de un resumen en la base central. Si una actualización falla después de que la veeduría guardó, la sugerencia queda desfasada hasta la noche. | `contracts:followers` lo recalcula cada noche y en cada despliegue; se puede correr a mano. |
| **Staging se despliega a mano** | Fusionar en `main` no actualiza staging. | `make staging-deploy REF=main` después de fusionar. |
| **Staging duerme de 21:00 a 8:00** (it. 61) | De noche el sitio no responde y las tareas nocturnas no corren, la sincronización con SECOP incluida. | En staging, la sincronización va a las 08:30 (*Parámetros*). Para encenderlo fuera de horario: `docs/staging.md`. Producción no duerme. |
| **El `README.md` es el de Laravel** | Es lo primero que ve quien llega a un proyecto de código abierto. | Escribir uno propio, que lleve a `CONTRIBUTING.md` y a estos documentos. |

## 7. Lo que espera una decisión

**Para salir a la red principal** (lista completa en [`docs/go-live.md`](go-live.md) y [`docs/viabilidad-legal.md`](viabilidad-legal.md)):
- **El operador** (L1): quién responde por GovTrace. A su nombre van las cuentas, los fondos y la política de datos, que sigue como borrador sin sus datos.
- **Las reglas** entre el operador, las veedurías y los terceros (L2), y la revisión legal (L3).
- **El dominio, el correo (SES) y los XLM** de la tesorería (~200 XLM).
- **La firma con AWS KMS** (it. 37b): la llave de la selladora no sale de KMS.
- **Activar la verificación en dos pasos** del Super Administrador: está construida e inactiva.

**Del producto:**
- **La tercera parte de la 52:** paginar la línea de tiempo de una obra, el expediente, el panel global, la búsqueda de territorios y los datos abiertos.
- **Aprobar la iteración 57,** con sus decisiones: el cupo por defecto y si se mide en sellos o en XLM.
- **Después del piloto:** elegir la obra sin señal (V20) y el correo al veedor cuando su reporte se publica o se rechaza (V17).
- **Pospuestos:** la denuncia sin nombre (Ley 962 de 2005, art. 81) y el derecho de petición a la Contraloría.
- **Un abogado confirma** la regla de a qué contraloría acudir, que el expediente da como orientación.
- **Los contratos mal clasificados en SECOP:** mostrarlos igual, marcarlos o filtrarlos.

## 8. Cómo pedir un cambio

1. **Descríbelo en términos del producto:** quién, qué necesita y por qué. Si es un problema, cómo verlo.
2. **Se traduce a la spec:** una historia nueva o la enmienda de una, con sus criterios y sus escenarios.
3. **Se propone como iteración** en `specs/PLAN.md`: el porqué, el entregable, el "Done-when", las decisiones que son tuyas y el modelo. No se implementa hasta que la apruebes.
4. **Se implementa con pruebas primero,** en una rama, y llega como PR con su resumen.
5. **Se fusiona con el pipeline en verde,** y se despliega en staging si quieres verlo allí.

**Comandos útiles para ver el producto:**

| Para | Comando |
|---|---|
| Una demostración en vivo, con datos de ejemplo | `make demo` (o `make demo TERRITORIO=medellin LUGAR="lat,lng"`) |
| Los correos y los códigos de desarrollo | `make mail` (Mailpit) y `make invites` |
| Recorrer cada pantalla de cada rol, con capturas | `make ux-check` (quedan en `storage/framework/testing/ux/shots/`) |
| Los flujos completos de cada rol en un navegador | `make e2e` |
| Llevar `main` a staging | `make staging-deploy REF=main` |

**Para leer después de este documento:** [`docs/arquitectura.md`](arquitectura.md) (el mapa técnico), [`docs/mapa-funcional.md`](mapa-funcional.md) (los flujos y sus vacíos) y [`docs/sellado-en-stellar.md`](sellado-en-stellar.md) (el sellado, sin jerga).
