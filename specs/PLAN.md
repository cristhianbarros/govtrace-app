# PLAN — GovTrace MVP

> Generado por `/plan` a partir de `specs/SPEC.md` y `features/*.feature`. Las iteraciones están numeradas y cada una tiene un "Done-when" concreto y verificable. Cada iteración termina en **un commit** con tests en verde, porque lo exige el hook de pre-commit. Se trabaja con GitFlow: una rama por iteración desde `main` (`feature/it-NN-<slug>`) y un commit `feat: iteración N — <resumen> (US-XXX)`.
>
> **Trazabilidad verificada:** las 56 historias del SPEC tienen su `.feature` (56/56, **0 huérfanas**). En total son 248 escenarios y 362 casos ejecutables, contando las filas de los *Esquemas* (351 al generar el plan; +11 filas al ajustar US-016, US-034 y US-008 a los datos reales de SECOP, R-SEC-07 y R-SEC-05).

## Antes de empezar

### Observaciones de trazabilidad y dependencias

| # | Observación | Cómo lo resuelve el plan |
|---|---|---|
| O1 | **US-008 (P1) necesita elegir la obra**, pero la búsqueda US-016 es P2 y la sugerencia de cercanía US-019 es P3. Sin una de las dos, P1 no se puede usar de punta a punta. | **US-016 se adelanta a P1** (iteraciones 8 y 16). ✅ *Confirmado.* |
| O2 | US-036/037 (publicar y retirar, P1) no tienen dónde mostrarse al público hasta el mapa y la línea de tiempo (US-027/029, P2). | Se acepta: en P1 la publicación queda lista en backend y se ve a partir de P2. |
| O3 | US-017 (tarjeta del contrato, P1) vive en la vista de obra de US-029 (P2). | El backend va en P1 (it. 8) y la pantalla en P2 (it. 26). |
| O4 | Las historias de P1 ya usan parámetros configurables (geocerca, invitación de 48 h, ventana de 12 meses), pero su pantalla (US-038-CFG) es P2. | En P1 los parámetros existen con valores por defecto **y con historial**, porque R-AUD-05 exige saber el valor vigente al capturar (it. 6). La pantalla llega en P2 (it. 21). |
| O5 | Las historias de P1 escriben en el log de auditoría (US-011, US-035, US-037), pero consultarlo (US-043-MON) es P2. | La escritura del log se construye en P1 (it. 6) y la consulta en P2 (it. 21). |
| O6 | Unas 10 reglas no se pueden expresar como escenario Gherkin: R-BCK-01..05, R-CFG-01, R-INT-02, R-MNT-03 y R-TST-01..03. Eso choca con R-TST-04. | Se verifican con Done-when de infraestructura y CI (it. 1, 12, 14 y 35). R-TST-04 **redactada de nuevo**: *"toda regla de comportamiento tiene su escenario automático; las reglas operativas tienen una verificación de infraestructura o CI"*. ✅ *Confirmado.* |

### Decisiones técnicas

✅ **El 2026-09-29 el usuario aprobó todas las decisiones registradas en este plan**, las de cada iteración incluidas (it. 23 a 35). Fijó como estándares obligatorios para el go-live la desactivación en cascada de la baja (it. 33), los respaldos fuera del sitio y el simulacro de restauración en producción (it. 35). Confirmadas D1 a D10. D4 y D5 se reformularon por el **pivote a Stellar** (2026-09-28, ver la sección "Pivote a Stellar"). D11 y D12 quedaron resueltas el 2026-09-28 (secretos inyectados, patrocinadora como *hot wallet*, umbral de 50 XLM y la tesorería paga la vigencia del contrato). No quedan decisiones técnicas abiertas.

| # | Tema | Propuesta | Se necesita en |
|---|---|---|---|
| D1 | Cola de trabajos | Driver `database`, con un servicio `worker` en el docker-compose base. Sin Horizon ni Redis en el MVP. | it. 1 |
| D2 | Archivos de evidencia | LocalStack (compatible con S3) en docker-compose para desarrollo y CI; S3 en producción. *Ajustada en it. 1: MinIO ya no se descarga sin login desde 2025.* | it. 1 |
| D3 | Roles y permisos | `spatie/laravel-permission`, preferencia expresada en el discovery | it. 4 |
| D4 | Smart Contract | **Soroban (Rust, `soroban-sdk`)** en `contracts/sealing/`, probado con el entorno de pruebas del SDK (`cargo test`, sin red). Red local *standalone* de Stellar en un perfil `stellar` de docker-compose: la imagen `stellar/quickstart` que levanta `stellar container start local`, con RPC y friendbot. Rust y Stellar CLI corren en un contenedor de herramientas, así que el host sigue necesitando solo Docker y `make` (R-TST-01). *Reemplaza Solidity/Foundry/Anvil (pivote a Stellar).* | it. 12 |
| D5 | Comisiones de red (R-BLK-01, R-BLK-04) | ✅ **Resuelta: sin proveedores de terceros.** El backend patrocina cada transacción con *fee bump*, nativo de Stellar. La cuenta **selladora** firma la invocación (`require_auth`); la cuenta **patrocinadora** de GovTrace, que tiene los XLM, paga la comisión completa, incluida la de recursos de Soroban. | it. 13-14 |
| D6 | Árbol de Merkle | Esquema único y documentado (SHA-256, pares ordenados), implementado en PHP en el servidor y en JS en el validador y el script, con vectores de prueba compartidos | it. 13 |
| D7 | Seudónimo del veedor (R-PRIV-03) | HMAC del ID del veedor con un secreto del servidor, más una tabla seudónimo→veedor con retención de 5 años (R-MNT-03) | it. 13 |
| D8 | Mapas | Leaflet con teselas de OpenStreetMap (R-INT-02) | it. 18 |
| D9 | Frontend | Se mantiene JavaScript, como está la plomería. Filament y TypeScript no se usan salvo que decidas lo contrario. | it. 16 |
| D11 | Custodia de las llaves (R-BLK-04) | ✅ **Resuelta (2026-09-28).** Para el MVP y testnet, **(a)**: las llaves de la selladora y la patrocinadora se inyectan como variables de entorno al arrancar, desde un gestor de secretos (en CI, las credenciales de Jenkins); nunca en el repositorio, su historial ni `.env.example`. La patrocinadora es una **hot wallet**: tiene el saldo de unos días de sellos y la recarga seguido una cuenta de **tesorería** fría, que no vive en el servidor. La selladora no tiene fondos. **Antes de la red principal: (b)** firma remota Ed25519 sin que la llave salga del servicio, detrás de la misma interfaz `SealingNetwork`. ✅ **Elegido el 2026-09-29: AWS KMS** (it. 37b). Al abrir la 37b se verifica primero que la llave de KMS sea Ed25519, la curva de Stellar, y que firme el hash de 32 bytes de la transacción tal cual. | it. 14, 37b |
| D12 | Umbral de saldo de la cuenta patrocinadora (US-021, US-022, US-038-CFG) | ✅ **Resuelta (2026-09-28).** Umbral de alerta de **50 XLM** (`sponsor_balance_alert_threshold_xlm`, ~200 sellos de 0,2425 XLM); reemplaza al parámetro en POL de la it. 6. La **tesorería** paga el despliegue y la extensión de la vigencia de la instancia y del código del contrato, así la hot wallet solo paga sellos. Se mantiene la vigencia máxima por sello (se revisa en la it. 23). Detalle en la it. 14. | it. 21 |
| D10 | Proximidad (US-019) | Haversine en SQL, sin PostGIS | it. 31 |
| D13 | Red principal de Stellar (R-CFG-01, it. 37) | ✅ **Resuelta (2026-09-29).** El RPC de Stellar (JSON-RPC de Soroban, no Horizon) lo da un proveedor, **QuickNode o Validation Cloud**: la red principal no tiene un RPC público gratuito como el de testnet. **Dos endpoints:** `STELLAR_RPC_URL`, privado, para el servidor, y `STELLAR_PUBLIC_RPC_URL`, de solo lectura y restringido al dominio de GovTrace, para el validador del navegador, así el token del proveedor no queda a la vista. La **tesorería la fondea el presupuesto operativo central** del proyecto (el operador del SaaS), desde un exchange corporativo. Presupuesto: **28 XLM iniciales** (subir el código, desplegar la instancia y extender su vigencia), **unos 27 XLM cada ~180 días** para volver a extenderla, y la patrocinadora aparte, con alerta bajo 50 XLM (D12). | it. 37 |

### Convenciones de pruebas
- **Backend:** Pest en `tests/Feature/<Épica>/US-XXX…Test.php`, con un test por `Escenario` y el mismo nombre. Cada `Esquema` es un test con `->with()` (dataset).
- **Frontend:** Vitest junto al componente, un test por escenario de UI.
- **Smart Contract:** `cargo test` en `contracts/sealing/`, con el entorno de pruebas de Soroban (sin red); los tests llevan el nombre del escenario, igual que en Pest.
- **SECOP:** respuestas grabadas (fixtures) de la API SODA (R-TST-02). **Nunca** se llama a la API real en `make test`.
- Un Done-when como "US-XXX (N casos)" significa que **todos** los casos de `features/US-XXX.feature` tienen su test y está en verde.

---

## Fase P1 — Semanas 1-2 · Núcleo del flujo de evidencia

### Iteración 1 — Infraestructura del MVP

**Entregable:** el docker-compose suma un `worker` de cola (D1) y un almacenamiento de objetos S3-compatible con su bucket `evidencias` (D2), y `make setup` funciona desde cero.
**Done-when:**
- `make setup` desde un clon limpio termina sin errores;
- `make ps` muestra app, proxy, pgsql, scheduler, storage y worker en *healthy*;
- `curl -s -o /dev/null -w "%{http_code}" http://govtrace.localhost:8080/up` devuelve `200`;
- `tests/Feature/HealthCheckTest.php` y el nuevo `tests/Feature/Infra/ObjectStorageTest.php` (escribe y lee un objeto en el disco `evidencias`) en verde;
- `make test-all` en verde.

**✅ Cumplido:** `docker-compose.yml` con el servicio `storage` y `worker` (con su propio healthcheck de proceso, no el de Apache que trae la imagen); `config/filesystems.php` con el disco `evidencias` (driver `s3`); `docker/storage/init-bucket.sh` crea el bucket al arrancar. `tests/Feature/Infra/ObjectStorageTest.php` (2) y `QueueTest.php` (2) en verde. `tests/infra/verify-stack.sh` confirma los 6 servicios *healthy* y `/up` en 200.
**Decisión que cambió D2:** MinIO ya no se puede descargar sin iniciar sesión (`minio/minio` y el mirror de Bitnami rechazan el pull anónimo desde 2025). Se usa **LocalStack** (`localstack/localstack:3.8`) en su lugar: mismo API S3, gratis, se descarga sin problema. La producción sigue apuntando a un bucket S3 real.
**Cubre:** infraestructura base · D1, D2 (ajustada) · R-BCK-03 (el almacenamiento queda preparado para respaldo).

### Iteración 2 — Datos de referencia: DIVIPOLA y emparejamiento
**Entregable:** tabla central de departamentos y municipios cargada con un seeder, desde el archivo oficial DIVIPOLA versionado en `database/data/`. Servicio de emparejamiento normalizado, sin tildes ni mayúsculas.
**Done-when:**
- test de seeder: el conteo de departamentos y municipios coincide con el archivo oficial, y se verifican los códigos 47, 47001, 47189, 05 y 05001;
- los 4 casos del Esquema *"Emparejamiento normalizado del municipio con la tabla DIVIPOLA"* (US-013) en verde como tests unitarios del servicio.

**✅ Cumplido:** `database/data/divipola.json` con el dataset oficial completo del DANE (dataset `gdxc-w37w` de datos.gov.co): **33 departamentos y 1122 municipios**, no un subconjunto. Migraciones `departments`/`municipalities` (BD central), modelos `app/Domain/Geography/{Department,Municipality}.php`, `DivipolaSeeder` (idempotente) y `MunicipalityMatcher` (normaliza con `Str::ascii()` + mayúsculas). `tests/Feature/Geography/{DivipolaSeederTest,MunicipalityMatcherTest}.php` en verde — 7 tests, incluidos los 4 casos del Esquema de US-013. *(Los tests del matcher viven en `Feature/`, no en `Unit/`, porque `tests/Pest.php` solo liga `Tests\TestCase` — y por tanto la base de datos — a `Feature/`; "unitario" en el Done-when describe el estilo de la prueba, llamar al servicio directo, no la carpeta.)*
**Cubre:** US-012 (validación DIVIPOLA), US-013 (emparejamiento) · R-INT-03.

### Iteración 3 — Organización: invariantes y alta
**Entregable:**
- `app/Domain/Organization`: valores `Nit` (DV de la DIAN), `Subdomain` (formato, mínimo 3 caracteres, palabras reservadas) y `OrganizationName`.
- Caso de uso de alta en `app/Application`.
- Alta del tenant con stancl, con su dominio `<subdominio>.govtrace.localhost`.

**Done-when:** US-001 (22 casos) en verde. Incluye NIT duplicado, DV inválido, subdominio duplicado, reservado, mal formado y fuera de rango, más el negativo "solo el Super Admin asigna el subdominio".

**✅ Cumplido:** `app/Domain/Organization/{Nit,Subdomain,OrganizationName}.php` (value objects inmutables) y `Exceptions/OrganizationValidationException.php` con los mensajes exactos. `app/Application/Organization/RegisterOrganization.php` valida, comprueba duplicados y crea el tenant + su dominio en un solo paso (alta = aprobación). `tests/Feature/Organization/RegisterOrganizationTest.php`: **22/22 en verde**.

**Hallazgo de infraestructura (corregido aquí):** `routes/tenant.php` existía desde el arranque del proyecto pero **nunca se cargaba** — `bootstrap/app.php` solo registraba `routes/web.php`. Ningún subdominio de organización respondía. Se agregó el callback `then:` de `withRouting()` para incluirlo.

**Tres decisiones técnicas de esta iteración, no anticipadas en el plan:**
1. **Columnas reales en `tenants`.** El trait `VirtualColumn` de stancl mete todo atributo salvo `id` en un JSON `data`, salvo que se declare `getCustomColumns()`. Sin eso, la comprobación de NIT duplicado no se puede indexar ni consultar. Se sobrescribió en `App\Infrastructure\Tenancy\Tenant`.
2. **Inmutabilidad del subdominio como invariante, no como permiso.** Ninguna historia del SPEC (US-007 solo nombre/logo, US-011 solo NIT) permite cambiar el subdominio después del alta — ni siquiera el Super Admin. Se implementó como una regla de `App\Infrastructure\Tenancy\Domain` (bloquea el `UPDATE` de la columna `domain`), no como una verificación de rol, porque el rol todavía no existe (llega en la it. 4) y, sobre todo, porque la capacidad de cambiarlo no existe para nadie.
3. **Los tests de esta historia no usan `RefreshDatabase`.** Crear una organización ejecuta `CREATE DATABASE` sobre la misma conexión central, y Postgres rechaza ese comando dentro de una transacción — que es justamente lo que envuelve `RefreshDatabase` en cada test. Se limpia a mano en `afterEach` (borrar el tenant dispara el borrado de su base).

**Cubre:** US-001 · R-SA-03.

### Iteración 4 — Identidad y acceso
**Entregable:**
- roles Super Administrador, Administrador de Organización y Veedor (D3);
- inicio de sesión en el dominio central y en el subdominio, con redirección por rol;
- bloqueo de 5 intentos / 15 min;
- asignación del Administrador inicial con su correo de bienvenida.

**Done-when:** US-002 (6 casos) y US-031 (9 casos) en verde.

**✅ Cumplido:** 15/15 tests en verde (`tests/Feature/Organization/AssignInitialAdministratorTest.php` y `tests/Feature/Auth/LoginTest.php`).

**Diseño de identidad (dos espacios de usuarios, no uno):** el Super Administrador vive en la tabla `users` **central** (`App\Models\User`, guard `web`); el Administrador de Organización y el Veedor viven en la tabla `users` **de cada tenant** (`App\Domain\Organization\User`, guard `tenant`), instalada por una migración nueva en `database/migrations/tenant/`. `spatie/laravel-permission` (D3) también se migra por tenant, con dos roles sembrados por una migración (no un seeder aparte) para que estén listos apenas se crea la organización. `App\Domain\Auth\Exceptions\AuthenticationRejected` + `app/Application/Auth/AuthenticateUser.php` implementan el bloqueo de 5 intentos / 15 min (mismo código para ambos guards) y la cuenta desactivada. `AssignInitialAdministrator` reutiliza `WelcomeNotification` con un enlace de 48 h, pensado para que la it. 5 (invitar veedores) lo reuse tal cual.

**Rutas y paneles placeholder.** No hay pantallas Vue todavía (llegan en it. 17-19): se agregaron rutas mínimas (`POST /login` central y de tenant, `GET /dashboard` · `/organization/dashboard` · `/veedor/dashboard`) solo para que el backend sea probable por HTTP de punta a punta. Se reemplazan por las páginas reales en su iteración correspondiente.

**Hallazgo corregido en código de producción (no solo en el test):** `$tenant->run($callback)` de stancl **no es exception-safe** — si el `$callback` lanza, nunca revierte el contexto de tenancy ni purga la conexión, dejando el resto del *request* corriendo contra la base de datos del tenant equivocado. `AssignInitialAdministrator` ya no usa `$tenant->run()`; inicializa el tenancy a mano dentro de un `try/finally`. Vale para cualquier código futuro que use `$tenant->run()` con lógica que pueda lanzar.

**Cubre:** US-002, US-031 · R-SEC-03, R-VER-02 (la zona pública no pide sesión).

### Iteración 5 — Invitaciones de veedores
**Entregable:** invitación con token de 48 h y aceptación con reglas de contraseña. El correo es único por organización y puede repetirse entre organizaciones.
**Done-when:** US-005 (7 casos) y US-030 (7 casos) en verde.

**✅ Cumplido:** 14/14 casos (`InviteObserverTest` 7 + `AcceptInvitationTest` 7). `InvitationToken` (nuevo, `app/Domain/Organization/`) extrae la generación de token que ya se repetía en `AssignInitialAdministrator`; ambos use cases lo usan ahora (DRY). `AcceptInvitation` + `SetPasswordController` son el consumidor real del enlace que ya generaban US-002 y US-005: valida el token con `hash_equals` + vencimiento, activa la cuenta e inicia sesión de inmediato. `StrongPassword` (regla de validación reusable, `app/Domain/Auth/Rules/`) impone las 4 condiciones de la contraseña con un único mensaje exacto — pensada para reusarse en US-039-USR (it. 20). Se registraron los alias de middleware `role`/`permission`/`role_or_permission` de spatie (no vienen automáticos en el estilo `bootstrap/app.php` de Laravel 11+); `POST /observers/invite` los usa (`role:Administrador de Organización,tenant`).

**Bug de producción encontrado y corregido (no solo en el test):** `invitation_token_hash` e `invitation_expires_at` **no estaban en `$fillable`** de `App\Domain\Organization\User`. `User::create([...])` los descartaba en silencio — la invitación se creaba sin fecha de vencimiento real. El test de la it. 4 no lo detectó porque nunca verificaba ese valor (solo que se enviara un correo); ahora sí lo hace, como regresión.

**El último escenario de US-005** ("el Administrador de Organización no puede dar de alta otras organizaciones") se implementó como una guarda estructural en `RegisterOrganization`: si `tenant()` está activo, rechaza — el Super Administrador nunca opera desde un dominio de tenant, así que esta condición por sí sola basta, sin necesitar todavía el panel/rol completo de la it. 19.

**Cubre:** US-005, US-030 · R-USR-01, R-TA-01.

### Iteración 6 — Datos legales, territorio, log de auditoría y parámetros
**Entregable:**
- actualización del NIT por el Super Admin;
- territorio de la organización guardado en la base central, para que la sincronización lo consulte;
- **escritura** del log de auditoría (quién, cuándo, acción, antes y después);
- tabla de parámetros con valores por defecto **e historial con fecha de vigencia** (O4).

**Done-when:**
- US-011 (4 casos) y US-012 (4 casos) en verde;
- test que prueba que la tabla de parámetros devuelve el valor vigente en una fecha pasada, base de R-AUD-05.

**✅ Cumplido:** 8/8 casos (`UpdateOrganizationLegalDataTest` 4, `ConfigureTerritoryTest` 4) + 3 tests de `ParametersTest`. Infraestructura nueva, central en las tres:

- **`audit_logs`** (`App\Domain\Audit\AuditLog`, con `AuditLog::record(...)`): quién, cuándo, acción, antes y después. Solo `UpdateOrganizationLegalData` escribe en esta iteración; el resto de acciones que R-AUD-04 lista (publicar/rechazar evidencia, invitar veedor, cambio de territorio…) se conectan en sus propias iteraciones — no se retrocedió a instrumentar it. 3/4/5.
- **`organization_territories`** (central, no por tenant, porque la sincronización SECOP de la it. 7 necesita leer el territorio de todas las organizaciones sin abrir cada base): una fila por departamento o municipio elegido. `ConfigureTerritory` reemplaza el conjunto completo en una transacción.
- **`parameters`** (`App\Domain\Configuration\Parameters`): nunca se actualiza una fila, se inserta una versión nueva con su propia `effective_from` — así `valueAt($clave, $fecha)` puede responder "qué valor regía en esa fecha", la base literal de R-AUD-05. Sembrada con los 5 valores por defecto conocidos (geocerca 500 m, ventana 12 meses, invitación 48 h, umbral Relayer 5 POL, sincronización 02:00). **No se reconectaron** los usos ya hardcodeados de esos valores (US-018's 48h, US-008's 500m, etc.) — eso le corresponde a la it. 21, que construye el panel de US-038-CFG.

**Alcance parcial, documentado:** el escenario *edge* de US-012 ("quitar una ciudad no borra lo ya registrado") solo se probó en su mecánica de reemplazo del territorio (la ciudad desaparece del conjunto). La parte sobre evidencias que siguen en la blockchain y el mapa público no se puede probar todavía — esas piezas llegan en it. 8+ y it. 24+.

**Cubre:** US-011, US-012 · R-TA-03, R-AUD-04 (escritura), R-AUD-05 (base).

### Iteración 7 — Sincronización SECOP II
**Entregable:**
- cliente SODA;
- tarea programada a las 02:00 y sincronización inmediata al dar de alta, reactivar o cambiar territorio;
- filtro de tipo "Obra";
- upsert por `id_contrato` con restricción de unicidad;
- contratos anulados pasan a `cancelled`, sin borrarse nunca;
- los territorios sin organizaciones activas no se consultan.

**Done-when:** US-013 (12 casos), US-032 (5) y US-033 (5) en verde con fixtures grabadas de SECOP II.

**✅ Cumplido (21/22 casos, 1 pendiente documentado):** US-032 5/5, US-033 5/5 y US-013 11/12 (`ProcessSecopContractRowTest`, `SyncSecopContractsTest`), más 10 tests técnicos: cliente SODA (`SecopClientTest` 2), variantes reales de nombres (`MunicipalityMatcherTest` +2, con 27 pares grabados), estado real "Cancelado", borrado prohibido, fila fuera de territorio, reporte de lo no emparejado en la corrida, hora programada y sincronización acotada a una organización. Suite completa: 131 en verde + 1 `todo`.

- **Fixtures grabadas de la API real** (`tests/fixtures/secop/`, 2026-09-27): filas de obra de Magdalena y Antioquia, recortadas a los campos que se usan y con los nombres de personas naturales seudonimizados. `Http::preventStrayRequests()` en `tests/Pest.php` hace que `make test` falle si algo intenta salir a internet (R-TST-02).
- **Lo que enseñaron los datos reales** y se corrigió antes del commit:
  1. SECOP **no** publica "Anulado": publica **"Cancelado"**. Los dos pasan a `cancelled`.
  2. **67 nombres DIVIPOLA se repiten entre departamentos** (Armenia, Barbosa, San Andrés…). El emparejador buscaba solo por nombre y un contrato de Armenia (Quindío) caía en Armenia (Antioquia). Ahora busca **dentro del departamento** y, sin departamento, no adivina un nombre ambiguo.
  3. SECOP escribe las ciudades de forma suelta ("Calarca", "Cali", "Cúcuta", "Cartagena", "No Definido"), así que un filtro exacto por ciudad del lado de SECOP perdía contratos. Ahora **se consulta por departamento** (33 nombres; solo Bogotá y San Andrés difieren de DIVIPOLA, `Department::SECOP_NAMES`) y la ciudad se empareja localmente. Una tabla de 13 alias verificados (`MunicipalityMatcher::SECOP_ALIASES`) cubre las capitales con nombre oficial largo. Bogotá es a la vez departamento y único municipio, así que todo lo del Distrito Capital va a 11001. Cobertura medida sobre los 53.398 contratos de obra de SECOP II: 89,9%. El resto es ciudad "No Definido" fuera de Bogotá (3.716) o departamento "No Definido" (1.691), y se descarta y se reporta. No queda ningún otro nombre sin emparejar.
  4. Antioquia tiene más de 1.000 contratos de obra: el cliente ahora **pagina** (`$order=:id`, `$offset`) con un generador.
- **Diseño:** `WatchedTerritories` (dominio) dice qué vigila al menos una organización activa y decide qué departamentos consultar y qué filas guardar. Lo que cae fuera no se guarda ni se actualiza (R-SEC-02, R-AUD-06). `ProcessSecopContractRow` devuelve un `SecopRowOutcome` por fila. `SyncSecopContracts` recibe el id de la organización para la sincronización inmediata (alta y cambio de territorio) o nada para la corrida nocturna (`routes/console.php`, hora tomada de `parameters`). Cada corrida queda en `secop_sync_runs`: nuevos, actualizados, descartados, **qué ubicaciones no emparejaron** (`unmatched_locations`) y el error. Eso es lo que leerá el panel de US-014 (it. 22). Si la corrida falla, la cola reintenta hasta 5 veces con espera de 1, 5, 15 y 60 min.
- `Contract` prohíbe editar fuera de `fromSecop()` y prohíbe **borrar** siempre (R-SEC-01, US-033).

**Alcance parcial, documentado:**
- La fila "el Super Administrador **reactiva** una organización suspendida" del Esquema de sincronización inmediata queda como `->todo()`: reactivar es US-003a (it. 20), que debe despachar `SyncSecopContracts::dispatch($tenant->id)`.
- "Con 3 evidencias selladas" (US-033) comparte test con "sin evidencias": las evidencias llegan en la it. 10+. Lo que se prueba en ambos casos es que el contrato nunca se borra.

**Decisiones pendientes:**
- Los contratos con departamento conocido y ciudad "No Definido" (3.716, p. ej. de las Gobernaciones) hoy se descartan y se reportan, porque todo contrato necesita municipio. Si deben aparecer para quien vigila el departamento entero (cascada de US-015, it. 8), habría que permitir `municipality_code` nulo.
- La corrida trae cada noche **todo** el histórico de obra del territorio. El upsert no escribe nada si la fila no cambió, pero un filtro incremental por `:updated_at` (o por la ventana de 12 meses de US-016) reduciría la descarga.
- `raw_payload` guarda la fila completa de SECOP, que incluye datos públicos de representantes legales y supervisores. Se puede recortar a los campos útiles si se prefiere minimizar datos personales.

**Cubre:** US-013, US-032, US-033 · R-SEC-01, R-SEC-02, R-AUD-06, R-INT-03, R-TST-02.

### Iteración 8 — Contratos del territorio: listado, búsqueda y tarjeta pública
**Entregable:**
- consultas con cascada DIVIPOLA (un departamento incluye su Gobernación y sus municipios);
- listado paginado del administrador;
- búsqueda del veedor con la regla de contratos seleccionables (activos, más Terminados/Liquidados de hasta 12 meses);
- tarjeta pública del contrato, con aviso de anulado.

**Done-when:** US-015 (5 casos), US-016 (11 casos, adelantada por O1) y US-017 (4 casos) en verde (backend).

**✅ Cumplido (20/20 casos):** `ListTerritoryContractsTest` 5, `SearchSelectableContractsTest` 11, `PublicContractCardTest` 4, más 2 tests técnicos añadidos a `ProcessSecopContractRowTest` para el punto 2 de abajo. Suite completa: 153 en verde + 1 `todo`.

- **`ListTerritoryContracts`**: paginado a 20, orden por `signed_at` (por defecto, descendente) o `value`, filtrado por el territorio vigilado. El truncado del objeto a 50 caracteres con tooltip es de la pantalla (it. 18) — el backend entrega el texto completo.
- **Se resolvió la decisión pendiente de la it. 7**: US-015 y US-016 exigen que "un departamento incluye la Gobernación y todos sus municipios". Un contrato de Gobernación llega de SECOP con departamento conocido y ciudad "No Definido" — hasta ahora se descartaba como no emparejado. Ahora `ProcessSecopContractRow` lo guarda como **contrato departamental** (`municipality_code` nulo) si — y solo si — alguna organización activa vigila **el departamento entero**; si solo vigila uno de sus municipios, el contrato de la Gobernación queda fuera (`WatchedTerritories::coversDepartmentCode()`). Requirió una migración nueva (`municipality_code` nullable) y actualizar dos tests de la it. 7 que asumían el descarte (`SyncSecopContractsTest`): ahora Magdalena trae sus 6 filas en vez de 5, y el caso de "no emparejado y reportado" usa el Esquema de "Villa Inexistente" en vez de la fila real, porque en el corpus grabado ya no queda ningún nombre sin emparejar.
- **`SearchSelectableContracts`**: exige 3+ caracteres (si no, ni consulta), busca por objeto/contratista/número de proceso (`ilike`, Postgres) y aplica la regla de US-016 — siempre seleccionables "En ejecución", "Celebrado", "Adjudicado"; "Terminado"/"Liquidado" solo dentro de `closed_contract_report_window_months` (parámetro con historial, it. 6, hoy 12) contado desde `end_date`; nunca los `cancelled`. *(Después se ajustó a los estados reales de SECOP II, con R-SEC-07; ver las decisiones tomadas en la it. 10.)* El debounce de 300 ms es de la PWA (it. 16).
- **`GetPublicContractCard`**: entidad, contratista, valor (crudo; el formato en COP es de la pantalla, it. 26), plazo en meses (`signed_at`→`end_date`) y el aviso de "⚠️ Contrato Anulado/Retirado en SECOP" cuando el contrato está `cancelled`. Sin autenticación (R-VER-02). Como la ficha de obra no existe todavía (it. 9), la tarjeta se arma directamente a partir del `Contract` — es "el backend en P1" de la observación O3; conectarla a la vista de la obra es de la it. 26.

**Alcance parcial, documentado:**
- US-017: "la obra tiene 2 evidencias publicadas" no se prueba — las evidencias llegan en it. 10+. Lo que se prueba es que la tarjeta del contrato muestra su aviso sin importar el estado de las evidencias.
- El formato en pesos colombianos del valor y el truncado del objeto quedan sin construir: son de pantalla (it. 18 y it. 26).

**Cubre:** US-015, US-016, US-017 · R-VC-04.

### Iteración 9 — Ficha de obra por organización y cálculo de riesgo
**Entregable:** modelo de ficha de obra en la base de cada tenant, más el job diario de "En riesgo" (fecha vencida y contrato aún "En ejecución").
**Done-when:** US-034 (4 casos) en verde, incluido el negativo *"el estado vive en la ficha, no en el contrato"*.

**✅ Cumplido (4/4 casos + 2 técnicos):** `CalculateWorksitesAtRiskTest` — el Esquema de 3 filas, el escenario de separación estado/contrato, más el job programado y el aislamiento entre organizaciones (activa vs. suspendida). Suite completa: 159 en verde + 1 `todo`.

- **`worksites`** (`App\Domain\Worksites\Worksite`) es la primera tabla **por tenant** de una historia funcional (antes solo `users`): vive en la base de cada organización (`database/migrations/tenant/`), separada del `Contract` central — así "en riesgo" nunca toca el contrato de SECOP (R-SEC-01). Por ahora una ficha ancla un solo contrato; agrupar varios en una misma ficha es US-045-INT (it. 29, R-INT-05) y no se adelantó.
- **`CalculateWorksitesAtRisk`** es el primer job que entra al contexto de cada tenant (`Tenant::run()`) para leer también una tabla **central** (`Contract`) desde adentro. Eso expuso un problema real: sin `$connection` explícito, Eloquent usa la conexión por defecto, y Stancl Tenancy la reapunta a la base del tenant mientras `run()` está activo — `Contract::query()` habría fallado ahí ("relation contracts does not exist"). Se corrigió con el trait oficial del paquete, `Stancl\Tenancy\Database\Concerns\CentralConnection`, en `Contract` (el único modelo central que este job toca; los demás no lo necesitan todavía).
- Programado a las **03:00**, una hora después de la sincronización SECOP de las 02:00 — calcular el riesgo con contratos de ayer no tendría sentido. Recorre solo organizaciones activas, igual que `SyncSecopContracts`.

**Decisión pendiente:** `CentralConnection` solo se aplicó a `Contract`. Si una iteración futura lee otra tabla central (`Department`, `Parameters`, etc.) desde dentro de `Tenant::run()` — muy probable en la it. 10, que valida el contrato y el radio de geocerca al crear un reporte desde el contexto del veedor — va a tropezar con el mismo problema y necesita el mismo trait.

**Cubre:** US-034 · R-SEC-01.

### Iteración 10 — Crear reporte: GPS, geocerca y First-Touch
**Entregable:** endpoint de reporte con:
- clasificación y comentario;
- precisión GPS de 50 m o mejor;
- geocerca con el **radio vigente al capturar**;
- First-Touch con bloqueo atómico;
- restricción por territorio;
- marca de hora sospechosa.

**Done-when:** US-008 (22 casos) en verde, incluida la carrera de dos veedores (test con dos transacciones concurrentes).

**✅ Cumplido (22/22 casos):** `CreateReportTest` (21 casos contra el endpoint real) y `FirstTouchRaceTest` (la carrera, en dos variantes). A eso se suman tests técnicos: el radio vigente al capturar (2), el rechazo de un contrato anulado, solo el Veedor puede reportar, `CentralConnectionTest` (8), la primera versión de un parámetro y el riesgo de una ficha con varios contratos. Suite completa: 197 en verde + 1 `todo`.

- **`POST /reports`** (tenant, `auth:tenant`, solo el rol Veedor de Campo) responde `201 {id}`, o `422` con el motivo bajo el campo al que se refiere (`classification`, `comment`, `location`, `accuracy_meters`, `secop_contract_id`). El controlador solo revisa la forma de la petición. Las reglas viven en `App\Domain\Reports` (`ReportClassification`, `ReportComment`, `GpsReading`, `Geofence`, `SuspiciousCaptureTime`), con los mensajes textuales de `specs/criterios/US-008.yaml`.
- **La ficha de obra ahora agrupa varios contratos**, porque el escenario "ficha con varios contratos" es de US-008 y la it. 9 había dejado uno solo. Se crea la tabla `worksite_contracts` (`secop_contract_id` único dentro de la organización); la migración mueve los datos de la it. 9. El job de riesgo marca la ficha cuando **cualquiera** de sus contratos venció y sigue "En ejecución". Además usa `end_date < hoy`: un contrato que termina hoy queda en riesgo recién mañana. Los casos de la it. 9 no cambian.
- **First-Touch con bloqueo atómico** (`CreateReport`): la ficha se lee con `SELECT … FOR UPDATE`. De dos primeros reportes simultáneos, el segundo espera y se valida contra la ubicación que fijó el primero. Si la ficha ni siquiera existía, la carrera la resuelve el índice único más un savepoint.
  - El test usa **dos transacciones reales en dos procesos**: A queda abierta y B corre en `tests/Support/create_report_in_parallel.php`. A solo confirma cuando Postgres muestra a B esperando el bloqueo.
  - Se corrió 5 veces seguidas, estable. Se verificó que detecta el error: sin el `lockForUpdate()`, B sobrescribe la ubicación y el test falla.
- **R-AUD-05:** la geocerca usa el radio que regía **al capturar**, y queda guardado en el reporte (`geofence_radius_meters`). `Parameters::valueAt()` ahora responde con la primera versión cuando el momento es anterior a todas; antes devolvía `null`. Es lo que pasa con un teléfono con el reloj atrasado.
- **R-SEC-05 / R-MON-02:** el reporte se marca, sin rechazarse, cuando la hora de captura está en el futuro del servidor o es anterior a los 7 días de vigencia offline.
- **Se resolvió la decisión pendiente de la it. 9:** todos los modelos de tablas centrales (`Contract`, `SecopSyncRun`, `Department`, `Municipality`, `OrganizationTerritory`, `ParameterValue`, `AuditLog`) usan `CentralConnection`. Crear un reporte lee el territorio y los parámetros desde el contexto del veedor, y sin esto fallaba con `relation "departments" does not exist`. `CentralConnectionTest` lo vigila.
- **Una sola regla para tres lugares:** `Contract::scopeInTerritory()` y `Contract::scopeReportableAt()` las usan el listado y la búsqueda de la it. 8 (refactorizados) y la creación del reporte. Así `CreateReport` rechaza del lado del servidor lo que la búsqueda no ofrece, como un contrato anulado.

**Alcance parcial, documentado:**
- Los archivos y sus hashes son US-009 (it. 11). El sellado llega en las it. 12-13. El mensaje de éxito y el de "reintentar hasta obtener buena señal" son de la PWA (it. 16).
- "Si queda marcado lo ven el Administrador y el Super Administrador": la marca queda guardada. La bandeja es la it. 15 y el panel global la it. 19.
- Corregir la ubicación como acción del Administrador, con su registro, es US-035 (it. 11). El test de "ubicación errónea" la simula cambiando la ficha.
- La ficha "Acueducto Gaira" no tiene nombre todavía: nombrar y agrupar fichas es US-045-INT (it. 29).
- Una organización suspendida hoy ve sus reportes rechazados como "fuera del territorio", porque su territorio activo queda vacío. El 403 con su mensaje propio es de US-003a (it. 20).

**Decisiones tomadas después del cierre** (aprobadas el 2026-09-28; rama `fix/estados-secop-y-tolerancia-reloj`):
1. ✅ **Estados reales de SECOP II (nueva R-SEC-07).** El discovery hablaba de "Celebrado", "Adjudicado", "Terminado" y "Liquidado", y ninguno existe entre los 53.398 contratos de obra de SECOP II. Los valores reales son:

   | Estado en SECOP II | Contratos |
   |---|---|
   | Modificado | 16.089 |
   | terminado (en minúscula) | 13.781 |
   | En ejecución | 10.419 |
   | Cerrado | 3.785 |
   | Aprobado | 2.687 |
   | Borrador | 2.668 |
   | Cancelado | 1.809 |
   | Suspendido | 952 |
   | enviado Proveedor | 693 |
   | En aprobación | 464 |
   | cedido | 51 |

   Con la regla literal solo el 19,5% era reportable. Se aprobó esta tabla de equivalencias, que vive en `App\Domain\Contracts\SecopContractStatus` y se compara sin distinguir mayúsculas (`Contract::scopeStatusIn`):
   - **siempre reportables:** En ejecución, Modificado, Aprobado, cedido y **Suspendido**. Las obras paralizadas, los "elefantes blancos", son donde la evidencia ciudadana más importa.
   - **reportables dentro de la ventana:** terminado y Cerrado.
   - **nunca:** Cancelado, Borrador, enviado Proveedor, En aprobación, ni cualquier estado desconocido.
   - **"en riesgo" (US-034):** En ejecución y Modificado.

   El contrato conserva el texto de SECOP tal cual (R-SEC-01). Se actualizaron los Esquemas de `features/US-016.feature` (7 → 13 filas) y `features/US-034.feature` (3 → 5), sus criterios y los tests. Además se añadió un test técnico: un reporte sobre una obra "Suspendido" se acepta.
2. ✅ **Tolerancia de reloj (R-SEC-05):** 5 minutos hacia el futuro (`SuspiciousCaptureTime::CLOCK_SKEW_TOLERANCE_SECONDS = 300`), como el *leeway* al validar un JWT. Absorbe la latencia y el desfase del reloj sin abrir una ventana real para fechar evidencias. `features/US-008.feature` suma 3 filas (4, 5 y 6 minutos en el futuro).

Con esto US-016 pasa de 11 a 17 casos, US-034 de 4 a 6 y US-008 de 22 a 25, todos en verde.

**Cubre:** US-008 · R-GEO-01, R-VC-04, R-SEC-05, R-AUD-05, R-MON-02.

### Iteración 11 — Archivos de evidencia y corrección de ubicación
**Entregable:**
- recepción de 1 a 5 fotos o 1 PDF de hasta 10 MB;
- recálculo del SHA-256 en el servidor, con el mensaje de discrepancia;
- guardado en MinIO;
- corrección de ubicación de la ficha, con auditoría.

**Done-when:** los casos de servidor de US-009 en verde (cantidad, combinación, peso, video, discrepancia de hash y "las fotos no se difuminan"), más US-035 (4 casos).

**✅ Cumplido:**
- **US-009, servidor:** 13 casos en `ReportEvidenceTest`: el hash que coincide y encola, cantidad y combinación (7), peso (2), video, "no se difuminan" y hash alterado.
- **US-035:** 4 casos en `CorrectWorksiteLocationTest`, más 2 técnicos (solo el Administrador corrige; se rechazan coordenadas imposibles).
- Suite completa: 228 en verde + 1 `todo`.

- **Los archivos viajan en el mismo `POST /reports`** (`files[]` + `hashes[]`, en el mismo orden). Las reglas viven en `App\Domain\Reports`:
  - `EvidenceSet`: de 1 a 5 fotos o 1 PDF, sin mezclar; 10 MB por archivo; recálculo del SHA-256.
  - `EvidenceKind`: JPEG o PDF, **detectado por el contenido del archivo**, no por el nombre. Un video renombrado a `.jpg` no pasa.
  - `EvidenceUpload`.

  Todo se verifica **antes** de tocar la base: con un solo byte alterado no queda ni reporte, ni evidencia, ni objeto guardado, y el veedor recibe el mensaje textual de los criterios.
- **Evidencia = cada archivo** (`evidences`, en la base del tenant): lo que se sella (it. 12-13), se verifica y se descarga (US-024/026). Se guarda **byte a byte** en el disco `evidencias` (S3) bajo `{organización}/reports/{reporte}/{sha256}.{jpg|pdf}`: nada se difumina ni se re-codifica (R-PRIV-05, R-PRIV-06). "Encolada para el sellado" es `seal_status = pending`; el lote de Merkle de la it. 13 la recoge. Si falla el guardado de un archivo, se borran los ya subidos y la transacción deshace el reporte.
- **`PATCH /worksites/{id}/location`** (solo el Administrador de Organización), con `CorrectWorksiteLocation`: mueve la ubicación oficial (`Worksite::relocateTo`) y deja en `audit_logs` quién, cuándo, las coordenadas anteriores y las nuevas. El mensaje de éxito usa el radio vigente. La misma obra en otra organización no se toca, porque cada ficha vive en su propia base.
- El escenario de US-008 "una ubicación oficial errónea se corrige" ahora usa este endpoint real. En la it. 10 lo simulaba cambiando la ficha.
- Las fixtures de archivos (`tests/fixtures/evidence/`) son una JPEG y un PDF mínimos, pero reales. El contenedor no tiene GD, y la app no lo necesita: el servidor nunca procesa imágenes.

**Alcance parcial, documentado:**
- Los escenarios de US-009 que ocurren en el teléfono quedan para la PWA (it. 16): optimizar la foto a JPEG de 1920 px con calidad 80 %, purgar el EXIF, limpiar los metadatos del PDF y no dejar mezclar fotos y PDF. El servidor no puede limpiar nada sin cambiar el hash que se sella.
- "Arrastrando el pin" y "escribiendo latitud y longitud" son la pantalla del Administrador (it. 18). Al backend le llegan las mismas coordenadas, así que las dos filas del Esquema ejercitan la misma petición.
- El cruce de dos organizaciones que vigilan la misma obra se probó con dos fichas independientes; la ficha "Acueducto Gaira" con nombre sigue siendo US-045-INT (it. 29).

**Cubre:** US-009 (servidor), US-035 · R-HASH-01 (servidor), R-PRIV-05.

### Iteración 12 — Smart Contract de sellado en Soroban
*Reformulada el 2026-09-28 por el pivote a Stellar (Stellar Apex). Reemplaza la versión Solidity/Foundry/Anvil.*

**Entregable:**
- contrato Soroban en Rust, en `contracts/sealing/` (`soroban-sdk`):
  - `__constructor(sealer: Address)` fija la cuenta selladora al desplegar (R-BLK-03);
  - `seal(worksite: BytesN<32>, root: BytesN<32>)`:
    - exige `sealer.require_auth()`;
    - rechaza una raíz ya registrada con el error `HashAlreadyRegistered` ("Hash ya registrado");
    - guarda `{obra, hora del ledger, número del ledger}` con la raíz como clave, en almacenamiento persistente, y extiende su TTL;
    - emite el evento `sealed` (R-BLK-02);
  - `get_seal(root) -> Option<Seal>` para leerlo; lo usarán el validador y el script (it. 23-27);
  - sin funciones para modificar ni borrar sellos, ni `upgrade`: el código desplegado es inmutable (R-SA-01).
- perfil `stellar` en `docker-compose` con dos piezas:
  - la red local *standalone* (imagen `stellar/quickstart`, con RPC y friendbot);
  - un contenedor de herramientas con Rust y Stellar CLI.
- `make stellar-up`, `make contract-test` y `make contract-deploy`. El último:
  - compila el WASM;
  - crea con friendbot las cuentas selladora y patrocinadora de desarrollo;
  - despliega con el constructor y deja `STELLAR_SEALING_CONTRACT_ID` en `.env`.

**Done-when:**
- US-020a (4 casos) como `cargo test` en verde, con el entorno de pruebas de Soroban; el test fija la hora y el número del ledger;
- un test comprueba que la interfaz exportada del contrato es exactamente `__constructor`, `seal` y `get_seal` (el escenario "nadie puede modificar ni borrar");
- `make contract-deploy` despliega en la red local, y un `stellar contract invoke … -- get_seal` responde;
- el Jenkinsfile corre `cargo test` del contrato.

"El servidor descarta el duplicado" (tercer escenario de US-020a) pasa del lado de Laravel: se prueba en la it. 13, cuando el backend empiece a sellar.

**✅ Cumplido (4/4 casos + 1 técnico):** `contracts/sealing/src/test.rs` tiene un test por escenario, con su nombre, más la vigencia máxima del sello (TTL). `make contract-test` corre `cargo fmt --check`, `cargo clippy -D warnings`, `cargo test`, `stellar contract build` y `scripts/check-interface.sh`, todo en verde. La suite de Laravel no cambia: 228 + 1 `todo`.

- **Versiones fijas** (2026-09-28): `soroban-sdk` 27.0.6 y Stellar CLI 27.1.0 corresponden al **Protocolo 27**, el de la red principal. El 28 solo corre en testnet, y testnet ejecuta contratos del 27. A eso se suman Rust 1.98.1 (el SDK exige ≥ 1.91), target `wasm32v1-none` y `stellar/quickstart:v670-b1459.1-latest` (el mismo digest que `latest`), con `--protocol-version 27 --limits testnet`.
- **El contrato** (3 KB de WASM):
  - `__constructor(sealer)` fija la selladora;
  - `seal(worksite, root)` exige `require_auth` de la selladora, rechaza el duplicado con `Error(Contract, #1)` = `HashAlreadyRegistered`, guarda `{worksite, sealed_at, ledger}` (hora y número **del ledger**) en almacenamiento persistente con el TTL al máximo de la red, y emite el evento `Sealed`;
  - `get_seal(root)` lo lee.

  No hay nada para modificar ni borrar sellos, ni `upgrade`. Cambiar de selladora es desplegar otro contrato, y el verificador de la it. 23 consulta todas las direcciones históricas.
- **"Nadie puede modificar ni borrar" se verifica en dos partes, a propósito:**
  - el test de Rust intenta llamar operaciones de cambio y exige que el sello siga igual;
  - `check-interface.sh` exige que el WASM exporte **exactamente** `__constructor`, `get_seal` y `seal`.

  Hace falta lo segundo porque, en el entorno de pruebas, un `upgrade` con un hash inexistente falla con el mismo error que una función que no existe. Se comprobó agregando un `upgrade` al contrato: el test de Rust no lo notó y el chequeo de interfaz sí. Quitando el `require_auth`, fallan los dos tests de autorización.
- **Red local:** `make stellar-up` levanta la red *standalone* (sana en ~15 s; RPC y friendbot en `http://stellar:8000` dentro de Docker y en `127.0.0.1:8100` desde el host). `make contract-deploy`:
  - crea con friendbot las cuentas selladora y patrocinadora de desarrollo (sus llaves quedan en `.cache/`, fuera de git);
  - despliega con la patrocinadora y comprueba que `get_seal` responda;
  - escribe en `.env` solo valores públicos: RPC, passphrase, ID del contrato y direcciones.
- **Prueba de humo real** (`make contract-smoke`, además del Done-when):
  - sella una raíz y la lee de vuelta con la hora del ledger;
  - comprueba que una cuenta externa es rechazada **porque la red exige la firma de la selladora**;
  - comprueba que el duplicado se rechaza **con `Error(Contract, #1)`**.

  El script exige el motivo exacto, no solo que falle.
- **CI y hook:** el Jenkinsfile suma la etapa "Test Contract" (`make contract-test`, sin necesitar la red). El hook de pre-commit corre `make contract-test` cuando cambia algo en `contracts/`.
- **VPN:** con la VPN de la oficina activa, los `RUN` de `docker build` se quedaban sin DNS ni internet, porque la VPN publica rutas dentro de `172.17.0.0/16`, la subred de `docker0`. Los builds ahora usan la red del host (`DOCKER_BUILD_NETWORK=host`, documentado en `.env.docker.example`). La red del proyecto (`172.29.0.0/24`) no estaba afectada. El puerto por defecto de la red local pasó a 8100, porque el 8000 es un puerto de desarrollo muy común.
- **Validación con la VPN arriba** (2026-09-28, a pedido): 15 de 16 pasos en verde. Pasaron los builds sin caché de `app` y `soroban`, `make up`, el proxy, la salida a internet de los contenedores (packagist, npm, crates.io), `make lint`, `make test`, `make test-front`, la red de Stellar, app → RPC de Stellar, y `contract-test`, `contract-deploy` y `contract-smoke`. El único fallo fue `composer diagnose` contra GitHub. La VPN empuja rutas /32 hacia cinco IPs de GitHub (`140.82.112.3`, `.112.10`, `.113.5`, `.114.6`, `.114.10`) y por el túnel no responden. Como el DNS de GitHub rota, `git push`, `gh` y los zips de Composer fallan de forma intermitente. Es del sistema, no del proyecto, y lo resuelve TI. Del lado del repo:
  - Stellar CLI ahora sale de su **imagen oficial en Docker Hub** (`stellar/stellar-cli:27.1.0`, el mismo commit que el release) y no de un release de GitHub, así que la imagen `soroban` compila con la VPN sin tocar GitHub.
  - Hay un comando nuevo, **`make doctor`** (`tests/infra/check-network.sh`), que revisa la subred del proyecto, la red de los builds, la salida a internet de los contenedores y las IPs de GitHub desviadas a un túnel mudo, con el comando sugerido para sacarlas si la política lo permite.

**Cubre:** US-020a · R-BLK-02, R-BLK-03, R-SA-01, R-TST-01 (contrato).

### Iteración 13 — Sellado por raíz de Merkle (red local)
**Entregable:**
- árbol de Merkle en el servidor, con una hoja por archivo más una de metadatos con seudónimo (D6, D7);
- pruebas de inclusión guardadas;
- estados Recibida → En Cola → Transmitiendo → Sellada. "Sellada" es incluida con éxito en un ledger cerrado: Stellar no reorganiza, así que no hay confirmaciones extra ni auditoría de reorganizaciones (R-BLK-06);
- la transacción la firma la selladora y la envuelve en *fee bump* la patrocinadora (D5);
- pausa y alerta por falta de saldo de la patrocinadora;
- interfaz `SealingNetwork`, implementada contra la red local *standalone*. El SDK de Stellar para PHP se confirma al abrir la iteración; `soneso/stellar-php-sdk` soporta Soroban y fee bump.

**Done-when:**
- US-020b en verde contra la red local. Su Gherkin se ajusta a Stellar al abrir la iteración: sin las 3 confirmaciones ni la auditoría de reorganizaciones, y con fee bump y XLM en vez de relayer y gas;
- vectores de prueba de Merkle compartidos (`tests/fixtures/merkle/*.json`), usados por PHP y por JS;
- el servidor descarta un duplicado que el contrato rechaza con "Hash ya registrado" (US-020a).

**✅ Cumplido:**
- **US-020b, 7/7 casos**, en `SealReportTest`, con la red en memoria (`Tests\Support\FakeSealingNetwork`).
- **La mitad de servidor de US-020a** ("el servidor descarta el duplicado").
- **Contra la red local de verdad**, `StellarSealingNetworkTest` (grupo `stellar`, `make test-stellar`) pasa 4/4:
  - la selladora firma y **no paga nada**, y la patrocinadora paga con fee bump (se lee el sobre en la red);
  - la red rechaza el duplicado;
  - una patrocinadora sin XLM se detecta;
  - un reporte llega de punta a punta a "Sellada" con un ledger real.
- **Vectores de Merkle compartidos** (`tests/fixtures/merkle/vectors.json`, generados con Python para que ninguna implementación se valide contra sí misma), usados por `MerkleTreeTest` en PHP y por `resources/js/lib/merkle.test.js` en JS.
- Suite rápida: 247 en verde + 1 `todo`, **también con la red de Stellar apagada**. Vitest: 11.

- **El JSON de metadatos** (US-020b, R-PRIV-03, D6) es canónico: claves ordenadas, sin espacios, UTF-8 sin escapar (tampoco `/` ni U+2028), coordenadas con 7 decimales y hora de captura en UTC. Así el navegador lo recompone byte a byte. Lleva `captured_at`, `classification`, `comment`, `latitude`, `longitude` y `pseudonym`. La **clasificación** se agregó a lo que pedía el criterio, porque también es algo que no debería poder cambiarse sin que se note.
- **El seudónimo** (D7) es el HMAC-SHA256 de «organización:veedor» con `SEALING_PSEUDONYM_KEY` (o una llave derivada de `APP_KEY`). La tabla `veedor_pseudonyms` es la única forma de volver al veedor; su purga a los 5 años es de la it. 35.
- **El árbol** (`App\Domain\Sealing\MerkleTree`, D6) usa pares ordenados. Un nodo sin pareja sube tal cual. Las hojas son los archivos, en orden, y al final el hash del JSON. Cada evidencia guarda `leaf_index` y `merkle_proof`.
- **Estados** en `report_seals`: una raíz por reporte, así que un solo estado, con su hora en cada paso. `evidences.seal_status` de la it. 11 se quitó.
  - "Recibida" se escribe en la misma transacción que el reporte, y "En Cola" al confirmarla.
  - `SealReport` arma el árbol, envía y deja "Transmitiendo".
  - `ConfirmSeal` pasa a "Sellada" cuando la red cierra el ledger (R-BLK-06).
  - `SealReport` va de a uno (`WithoutOverlapping`), porque la selladora firma con su número de secuencia.
- **Sin XLM:** la patrocinadora se revisa antes de cada envío. Si no alcanza, el sellado se pausa (`sealing_pauses`, central, con historial), se manda **una** alerta crítica por email al Super Administrador, y los reportes esperan "En Cola". Se reanuda solo cuando vuelve a haber saldo.
- **Duplicado** (un reintento del mismo reporte): la simulación ya devuelve `Error(Contract, #1)`. El servidor no reenvía: lee el sello que la red tiene (`get_seal`) y lo toma.
- **Stellar desde Laravel** (`App\Infrastructure\Stellar`): del SDK `soneso/stellar-php-sdk` 1.15 se usan las transacciones, el fee bump, el XDR y la lectura de respuestas. Las cuatro llamadas JSON-RPC van por el cliente HTTP de Laravel (`StellarRpc`), porque el `SorobanServer` del SDK exige HTTPS salvo en `localhost` y no envía fee bumps.
  - El SDK pide la extensión `gmp`, que se sumó a la imagen de la app.
  - El SDK exige `guzzlehttp/guzzle ^7`, así que guzzle bajó de 8.2 a **7.15.5**; Laravel 13 y el SDK de AWS aceptan las dos.
- **Costo medido de un sello** en la red local, con límites de testnet: **≈ 0,067 XLM** (673.103 stroops), con una comisión de recursos declarada de 0,08 y la parte no usada reembolsada. Es el primer insumo de D12. Por ahora el sellado se pausa por debajo de 2 XLM (`STELLAR_SPONSOR_MIN_BALANCE_XLM`); la reserva mínima de una cuenta es 1 XLM.
- **Dos bugs latentes corregidos al pasar:**
  - `App\Models\User` (el Super Administrador) no declaraba `CentralConnection`. Leído desde el contexto de una organización, la alerta habría ido a la tabla `users` de esa organización, es decir, a sus veedores. Ahora `CentralConnectionTest` lo vigila, junto con `SealingPause`.
  - El hook de pre-commit corría Vitest en el host con `node_modules` instalados en Alpine. Ahora usa `make test-front`.
- `make contract-deploy` escribe las llaves secretas de desarrollo en el `.env` local, fuera de git, para que Laravel firme. `.env.example` las deja vacías y un test revisa que ningún archivo versionado contenga una llave de Stellar (R-BLK-04).
- **Jenkins:** nueva etapa "Test Stellar" (`make stellar-up && make contract-deploy && make test-stellar`).
- El test de la carrera de First-Touch (it. 10) no despacha el sellado en su proceso hijo, para no depender de una red de Stellar.

**Pendiente, ya asignado:**
- Reintentos con espera, "Falla de Sellado" y cola estancada: US-021 (it. 22).
- La custodia de las llaves en producción: D11 (antes de la it. 14).
- El umbral de alerta de saldo: D12.

**Cubre:** US-020b · R-BLK-01, R-BLK-04, R-BLK-05, R-BLK-06, R-SEC-06, R-PRIV-03.

### Iteración 14 — Sellado en la testnet de Stellar
**Entregable:** la firma de las cuentas selladora y patrocinadora según D11, y la configuración de la testnet.

**Done-when:**
- prueba de humo (`make smoke-testnet`): un reporte de prueba llega a "Sellada" en la testnet de Stellar, con la comisión pagada por la cuenta patrocinadora (fee bump);
- una revisión automática confirma que ni el repositorio ni `.env.example` contienen llaves secretas de Stellar (empiezan con `S` y tienen 56 caracteres);
- se mide la comisión real por sello, el insumo de D12.

**✅ Cumplido (2026-09-28):**
- **`make smoke-testnet` en verde contra la testnet real de Stellar**, con dos reportes hasta "Sellada" (11 aserciones). La selladora no paga nada y la patrocinadora paga con fee bump.
- **`make secrets-check`** en verde: ninguna llave en los archivos versionados, en **todo el historial de git** ni en `.env.example` / `.env.testnet.example`.
- Suite rápida sin cambios en verde. Los grupos `stellar` y `testnet` corren aparte.

- **D11 aplicada** (opción (a) + hot wallet):
  - las llaves llegan como variables de entorno;
  - en desarrollo, `make testnet-setup` crea cuentas de prueba con friendbot, despliega el contrato y escribe `.env.testnet` (modo `600`, fuera de git);
  - `make smoke-testnet` carga ese archivo en el entorno de la prueba;
  - en CI, la etapa "Smoke Testnet" corre **solo al construir un tag de release** y escribe `.env.testnet` desde las credenciales de Jenkins (`stellar-testnet-contract-id`, `-sealer-secret`, `-sponsor-secret`), que se borra al terminar;
  - la etapa "Secrets Check" corre siempre.

  La opción (b), firma remota Ed25519, queda documentada en D11 como mejora antes de la red principal.
- **Testnet:** RPC `https://soroban-testnet.stellar.org`, passphrase `Test SDF Network ; September 2015`, en el Protocolo 28, que ejecuta el contrato compilado para el 27. Contrato desplegado para la prueba: `CABXHM74HFSAZD4FDFDONSIDJOVJBU7ZJXYBCHY3JJDCQUDFTHBT2WUI` (redesplegado con D12; el de la it. 14 era `CB5DS2M3…NUBZH`). SDF reinicia testnet cada tanto: si desaparece, se corre `make testnet-setup` y se actualizan las credenciales de Jenkins.
- **Costos reales en testnet** (Horizon de testnet, comisión cobrada):

  | Operación | Comisión |
  |---|---|
  | Subir el código WASM (una vez por versión del contrato) | 1,098 XLM |
  | Desplegar una instancia | 0,008 XLM |
  | **Primer sello tras subir código nuevo** | **27,5 XLM**: extiende a la vigencia máxima la entrada del código recién subido |
  | Primer sello de una instancia nueva, con el código ya extendido | 0,415 XLM |
  | **Sello de régimen** | **0,2435 XLM**, estable en 6 mediciones |

  El sello de régimen cuesta ~3,6 veces más que en la red local (0,067). Casi todo es renta, porque cada sello se guarda con la vigencia máxima de la red. La prueba sella dos reportes, registra ambos costos (`storage/logs/testnet-smoke.json`) y pone la cota de cordura (< 1 XLM) solo en el de régimen: el primero crece con el tiempo que el contrato pasó sin sellar.

**Insumos para D12 (umbral de saldo de la hot wallet):**
1. Con 0,2435 XLM por sello, cada 100 reportes al día cuestan ~24,4 XLM. El umbral y la recarga pueden pensarse en "días de sellos".
2. **El golpe único de 27,5 XLM al subir código nuevo no debería pagarlo la hot wallet.** Propuesta: que el script de despliegue extienda la vigencia del código y de la instancia con la cuenta que despliega (la tesorería) antes de habilitar el sellado.
3. **La vigencia de cada sello decide el costo.** Extenderla al máximo lo mantiene legible sin pasos extra. Una vigencia menor bajaría el costo, pero un sello archivado habría que restaurarlo (y pagarlo) antes de que el validador del navegador pueda leerlo: se decide junto con la lectura de sellos de la it. 23.

**✅ D12 resuelta y aplicada (2026-09-28, rama `fix/d12-hot-wallet`)** — se aprobaron las tres propuestas:
1. **Umbral de 50 XLM** (~200 sellos de régimen). Una migración siembra `sponsor_balance_alert_threshold_xlm = 50` y quita `relayer_balance_alert_threshold_pol`, un concepto del diseño EVM que nada usaba. La pantalla (US-038-CFG) se ajusta en la it. 21 y la alerta en la it. 32.
2. **La tesorería paga el despliegue y la vigencia del contrato**, no la hot wallet:
   - `seal()` ya no extiende la instancia (test Rust `sellar_no_extiende_la_vigencia_de_la_instancia_ni_del_codigo`, visto en rojo antes del cambio). Cada sello solo paga la renta de su propia entrada.
   - Los scripts de despliegue crean una tercera cuenta, la tesorería (`govtrace-treasury` / `govtrace-testnet-treasury`). Ella despliega y, antes de que se pueda sellar, extiende la instancia y el código hasta la vigencia máxima de la red (`max_entry_ttl − 1`, leída con `stellar network settings`). Su llave no va al `.env`: Laravel no la usa.
   - `make contract-extend` (red local) y `make testnet-extend` repiten esa extensión. Hay que correrlas antes de que venzan: unos 180 días con la vigencia máxima de hoy. Vigilarlo entra en la it. 32.
3. **Se mantiene la vigencia máxima por sello**; se revisa en la it. 23.

**Medido en testnet tras D12:**

| Quién paga | Operación | Comisión |
|---|---|---|
| Tesorería | Subir el WASM | 1,083 XLM |
| Tesorería | Desplegar la instancia | 0,008 XLM |
| Tesorería | Extender la instancia a la vigencia máxima | 0,172 XLM |
| Tesorería | Extender el código a la vigencia máxima | 26,71 XLM |
| Hot wallet | **Primer sello tras el despliegue** | **0,2425 XLM** (antes, 27,5) |
| Hot wallet | Sello de régimen | 0,2425 XLM |

La prueba de humo ahora pone la cota de cordura (< 1 XLM) en los dos sellos. Extender el código cuesta ~26,7 XLM cada ~180 días, ~53 XLM al año por versión del contrato, y lo paga la tesorería.

**Cubre:** R-BLK-04, R-CFG-01, R-TST-01 (prueba de humo en testnet).

### Iteración 15 — Publicación editorial
**Entregable:**
- estados Oculto, Publicado, Rechazado y Retirado;
- publicar de a una;
- rechazar y retirar con motivo obligatorio;
- lápida;
- retiro definitivo;
- todo con auditoría.

**Done-when:** US-036 (8 casos) y US-037 (6 casos) en verde.
**Cubre:** US-036, US-037 · R-TA-02, R-USR-02 (backend), R-MON-02.

**✅ Cumplido (2026-09-28):**
- **US-036, 8/8 casos**, en `tests/Feature/Publication/ReviewInboxTest.php`, contra los endpoints reales. Más 2 reglas derivadas: solo lo sellado llega a la bandeja y se publica; cada decisión va al log de auditoría.
- **US-037, 6/6 casos**, en `tests/Feature/Publication/WithdrawEvidenceTest.php`.
- Los 21 tests se vieron en rojo antes de implementar (404: no existían las rutas).
- Suite rápida: 268 en verde + 1 `todo`. `make test-stellar`: 4/4.

- **"Evidencia" es el reporte entero**, con todos sus archivos: se publica, se rechaza o se retira entero, porque se selló entero (una raíz por reporte, US-020b).
- **Estados editoriales** (`App\Domain\Reports\EditorialStatus`), aparte del estado de sellado. Columnas en `reports`: `editorial_status`, `editorial_reason` y `editorial_decided_at`.
  - Oculto → Publicado → Retirado (lápida).
  - Oculto → Rechazado (nunca público, sin lápida).
  - Retirado y Rechazado son finales. Las reglas y los mensajes viven en `Report::publish()`, `reject()` y `withdraw()`.
- **Endpoints** del Administrador de Organización:
  - `GET /inbox`: la bandeja, con las evidencias ocultas y selladas, la marca de hora sospechosa y las acciones de cada fila;
  - `POST /reports/{id}/publish`, `/reject` y `/withdraw`.

  Un motivo faltante responde 422 en `reason` ("Rechazar exige un motivo." / "Retirar exige un motivo."). Una decisión que el estado no permite responde 409, con su mensaje. No hay endpoint de publicación masiva.
- **Auditoría** (R-AUD-04): `evidence.published`, `.rejected` y `.withdrawn`, con quién, cuándo, el estado anterior y el nuevo, y el motivo. Cada decisión se aplica con la fila bloqueada. Si el log falla, la decisión no se aplica.
- **Lo público**:
  - `Report::onPublicMap()` son las publicadas; la it. 24 arma los pines sobre esto;
  - `PublicTimeline` da las tarjetas de una obra. La retirada es una lápida: sin archivos ni comentario, con el aviso "🚫 Evidencia retirada…" y el sello para auditoría externa.
- **R-TA-02 en el dominio**: `Report`, `Evidence` y `ReportSeal` no se borran. Tampoco se altera lo que envió el veedor, el archivo ni el sello ya escrito. Solo cambia el estado editorial.
- **R-USR-02 (backend)**: `Report::editorialStatusForVeedor()` da "En Revisión", "Publicado", "Rechazada" (con motivo) o "Retirado". La pantalla es "Mis Reportes" (it. 28).
- **Seguridad: sesión por organización.** Los usuarios viven en la base de cada organización, con ids que se repiten entre ellas, y las sesiones se guardan en la base central. Una cookie de sesión copiada a otro subdominio autenticaba al usuario con el mismo id de la otra organización. El test lo mostró: el Administrador de Santa Marta publicaba una evidencia de Ciénaga (200). Las rutas de tenant ahora usan `ScopeSessions` de stancl/tenancy: una sesión vale solo en la organización donde se abrió, y en otra responde 403.
- **Decisiones derivadas, aprobadas por el usuario (2026-09-28)** por ser consistentes con la promesa de inmutabilidad y el alcance del MVP:
  1. Publicar exige que la evidencia esté "Sellada", y la bandeja solo muestra las selladas. Lo publicado tiene que poder verificarse (US-024).
  2. El veedor ve "Retirado" en una evidencia retirada, sin el motivo; US-010 no lo define. Se revisa en la it. 28.
  3. Una retirada deja de contar para el mapa; queda solo como lápida en la línea de tiempo. Se revisa con los colores de pin de la it. 24.

### Iteración 16 — PWA del veedor: Nuevo Reporte (la pantalla central)
**Entregable:**
- flujo buscar obra → GPS (con reintento) → clasificación y comentario → adjuntos;
- en el teléfono: optimización de fotos a 1920 px, JPEG al 80 % y sin EXIF; limpieza de metadatos del PDF; SHA-256 con Web Crypto;
- los mensajes exactos de US-008 y US-009.

**Done-when:** Vitest en verde para:
- GPS denegado;
- reintento con precisión de 51 m;
- 6 fotos rechazadas;
- mezcla de fotos y PDF bloqueada;
- conversión de HEIC a JPEG sin EXIF;
- PDF sin metadatos;
- hash calculado sobre el archivo optimizado;
- búsqueda desde 3 caracteres con debounce de 300 ms.

**Cubre:** US-008, US-009, US-016 (UI) · R-PRIV-01, R-PRIV-04, R-PRIV-06, R-HASH-01.

**✅ Cumplido (2026-09-28):** Vitest 59 en verde (48 nuevos), cada uno visto en rojo antes de implementar. Los 8 puntos del Done-when:

| Done-when | Dónde |
|---|---|
| GPS denegado | `Pages/Veedor/NewReport.test.js` ("Permiso de GPS denegado"), `lib/geolocation.test.js` |
| Reintento con precisión de 51 m | `NewReport.test.js` ("Precisión mínima del GPS de 50 m"): con 51 m pide reintentar y no deja enviar; con 15 m, sí |
| 6 fotos rechazadas | `lib/evidence/attachments.test.js`, `Components/EvidencePicker.test.js` |
| Mezcla de fotos y PDF bloqueada | los mismos: con fotos, el selector solo ofrece fotos; con el PDF, nada más |
| HEIC a JPEG sin EXIF | `lib/evidence/photos.test.js`: 4000×3000 → 1920×1440 al 80 %; el EXIF con GPS se quita del JPEG, byte a byte |
| PDF sin metadatos | `lib/evidence/pdf.test.js`, con un PDF real: sin Info (autor, software, fechas) ni XMP |
| Hash sobre el archivo optimizado | `lib/evidence/prepare.test.js`: SHA-256 con Web Crypto sobre lo que se sube |
| Búsqueda desde 3 caracteres con debounce de 300 ms | `Components/ContractSearch.test.js`, con temporizadores falsos |

Además, con el nombre de su escenario: reporte exitoso (FormData con archivos, hashes, posición, clasificación y comentario, más el mensaje de éxito), clasificación obligatoria, comentario de 0/500/501, sin archivos, videos, 10 y 10,1 MB, búsqueda sin resultados, y los rechazos del servidor (geocerca y hash) mostrados con sus palabras.

- **Pantalla** `GET /reports/new` (Inertia, `Veedor/NewReport`). El flujo es buscar obra → GPS (con "Reintentar GPS") → clasificación y comentario → adjuntos → enviar a `POST /reports`. Si el servidor rechaza, se muestra su motivo y el reporte queda para reintentar.
- **"Buscar Obra"**: nuevo `GET /contracts/search?q=`, solo para el veedor, sobre `SearchSelectableContracts` (it. 8). Una respuesta vieja que llega tarde se ignora.
- **En el teléfono**, en `resources/js/lib/`:
  - foto: el navegador la decodifica respetando la orientación, se escala sin agrandar y sin difuminar (R-PRIV-05), se codifica en JPEG al 80 % y se le quitan los segmentos APP1–APP15 y COM (EXIF, XMP, ICC, comentarios);
  - PDF: `pdf-lib` (MIT) quita el diccionario Info y todo `/Metadata`, también dentro de flujos comprimidos. Se carga solo cuando se adjunta un PDF, así el paquete principal pesa 83 KB gzip y no 258;
  - las reglas y los mensajes son los mismos del servidor (`EvidenceSet`, `GpsReading`, `ReportValidationException`).
- `captured_at` es la hora de la lectura de GPS, la que certifica la presencia en la obra.
- Backend: `NewReportScreenTest` (3 casos). Suite: 271 en verde + 1 `todo`.
- `config/inertia.php`: las páginas están en `resources/js/Pages`, con mayúscula; Inertia las buscaba en `pages`.
- **Decisiones con el usuario (2026-09-28):**
  1. **HEIC, aprobado.** La foto HEIC la decodifica el navegador: Safari, que es donde el iPhone la produce. Mantener liviano el paquete de Vue es prioridad, así que no hay decodificador WASM. Un Android con HEIC en un navegador que no la lee recibe "No se pudo leer la foto. Tómela de nuevo con la cámara del teléfono."
  2. **Fotos dentro del PDF, ajustado** (rama `fix/pdf-embedded-exif`). Cada imagen JPEG del PDF (`DCTDecode`) pasa por el mismo `stripJpegMetadata` que las fotos: sale sin EXIF y sin coordenadas GPS, y la imagen queda byte a byte. Si un JPEG va además comprimido con otro filtro, o no se deja leer, el PDF se **rechaza**: "Este PDF trae imágenes que no se pueden limpiar. Expórtelo de nuevo o adjunte fotos en lugar del PDF." Nunca se sube sin limpiar. Se conserva el segmento APP14 "Adobe", sin datos personales, porque los JPEG en CMYK, comunes en los PDF, lo necesitan para sus colores. Tests: `pdf.test.js` (acta con una foto con GPS; imagen no limpiable) y `photos.test.js` (APP14).

### Iteración 17 — Acceso: iniciar sesión y activar la cuenta
**Entregable:** pantallas mobile-first de inicio de sesión y de activación con contraseña.
**Done-when:** Vitest de los estados de US-031 y US-030 en verde: credenciales incorrectas, bloqueo, cuenta desactivada, enlace vencido y contraseña débil.
**Cubre:** US-030, US-031 (UI).

**✅ Cumplido (2026-09-28):** Vitest 77 en verde (15 nuevos), vistos en rojo antes de implementar:
- `Pages/Auth/Login.test.js`: credenciales incorrectas, bloqueo tras 5 intentos y cuenta desactivada (el mensaje exacto del servidor, y la contraseña se vacía); las validaciones del formulario (correo sin dominio, contraseña vacía) no envían nada.
- `Pages/Auth/SetPassword.test.js`: activación exitosa (envía token, contraseña y confirmación); enlace vencido (sin formulario, con el motivo); las 5 reglas de contraseña débil; contraseñas distintas; y el enlace que vence con el formulario abierto.
- `testing/inertia.js`: un doble de `useForm` de Inertia. El test decide qué responde el servidor.

Backend (`AccessScreensTest`, 9 casos, vistos en rojo):
- `GET /login` en el subdominio (con el nombre de la organización) y en el panel global ("Panel global").
- `GET /set-password/{id}?token=`: si el enlace vale, trae el correo de la cuenta y a dónde enviar. Si no, el mensaje de `InvitationRejected`, igual para un token equivocado, un usuario que no existe o un enlace vencido, **sin revelar si la cuenta existe**. `AcceptInvitation::isValid()` es la misma regla del POST.
- Sin sesión, una pantalla protegida lleva al inicio de sesión de donde se esté: `redirectGuestsTo(url('/login'))`. Antes iba al del panel central.
- Tras entrar (login o activación), `Inertia::location()` hace una visita completa al panel del rol, porque la sesión y su token CSRF cambiaron. Un POST que no viene de Inertia sigue recibiendo la redirección de siempre: los tests de la it. 4 y 5 no cambian.

Suite: 280 en verde + 1 `todo`, estable en 5 corridas seguidas.

- **Arreglo de aislamiento en `LoginTest` (it. 4).** El test del Super Administrador dejaba `root@govtrace.app` en la base central. Una corrida completa lo tapaba, porque un test posterior la limpia; correr solo `tests/Feature/Auth` y luego la suite fallaba con un correo duplicado. Ahora su `afterEach` lo borra, y el archivo pasa dos veces seguidas.
- **Deuda técnica menor (aceptada por el usuario, 2026-09-28):** `APP_LOCALE=en`. Los mensajes por defecto de Laravel (`required`, `email`, `confirmed`) salen en inglés si alguien se salta la pantalla; las pantallas validan antes, en español, así que para el MVP es aceptable. Traducir `lang/es` queda pendiente.

### Iteración 18 — Panel del Administrador de Organización (P1)
**Entregable:**
- bandeja de entrada (publicar, rechazar, retirar);
- invitar veedores;
- territorio con buscador;
- contratos;
- corrección de ubicación en mapa Leaflet/OSM (D8).

**Done-when:** Vitest de cada pantalla con sus estados (carga, error, vacío, éxito) y los mensajes de US-036, US-037, US-005, US-012, US-015 y US-035 en verde.
**Cubre:** US-005, US-012, US-015, US-035, US-036, US-037 (UI).

**✅ Cumplido (2026-09-28):** Vitest 125 en verde (48 nuevos). Cada pantalla tiene sus estados de carga, error con "Reintentar", vacío y éxito, y los mensajes de sus historias; todo visto en rojo antes de implementar.

| Pantalla | Qué hace | Tests |
|---|---|---|
| **Bandeja** `/admin/inbox` (US-036, US-037) | Pestañas "Por revisar" y "Publicadas". Cada evidencia muestra sus fotos o su PDF tal como se sellaron, la clasificación, el comentario, la marca "Hora de captura sospechosa" y el ledger del sello. Publicar, rechazar y retirar van de a una (sin selección múltiple); rechazar y retirar exigen motivo, y retirar avisa que quedará una lápida. El 409 del servidor se muestra en la tarjeta. | `Inbox.test.js` (14) |
| **Veedores** `/admin/observers` (US-005) | Invitar por correo y ver el equipo con su estado: Activo, Invitación pendiente o Invitación vencida. | `Observers.test.js` (7) |
| **Territorio** `/admin/territory` (US-012) | Buscar departamentos y municipios (3 caracteres, 300 ms, sin importar tildes), sumarlos, quitarlos y guardar. No deja guardarlo vacío. | `Territory.test.js` (8) |
| **Contratos** `/admin/contracts` (US-015) | 20 por página, por fecha de firma o por valor (un segundo toque invierte el orden). El objeto se corta en 50 caracteres y se lee completo al pasar sobre él; el valor va en pesos. | `Contracts.test.js` (7) |
| **Obras** `/admin/worksites` (US-035) | Cada obra con sus contratos y su ubicación oficial. Se corrige arrastrando el pin del mapa o escribiendo latitud y longitud. | `Worksites.test.js` (8), `LocationMap.test.js` (3, con Leaflet de verdad) |

Además: `AdminLayout` (el nombre de la organización y una barra de navegación abajo, para el pulgar) y las piezas compartidas `LoadState`, `useLoader`, `useDebouncedSearch` (ahora también la usa "Buscar Obra") y `services/errors.js`.

**Backend** (`AdminPanelTest`, 27 casos, vistos en rojo):
- **Las páginas:** `/admin/*`, solo para el Administrador. `/organization/dashboard` abre la bandeja.
- **El JSON de cada pantalla:**
  - `GET /inbox?status=published`;
  - `GET /evidences/{id}/file`: el archivo tal como se selló, privado, para revisarlo; la descarga pública con prueba es la it. 23;
  - `GET /observers`, y `POST /observers/invite`, que ahora responde JSON 201 con "Invitación enviada a … El enlace vence en 48 horas.";
  - `GET/PUT /territory` y `GET /territory/search`. `ConfigureTerritory` registra en el log quién cambió el territorio (R-AUD-04);
  - `GET /contracts?sort=&direction=&page=`, que solo ordena por fecha de firma o valor;
  - `GET /worksites`.
- **Nombres DIVIPOLA:** vienen en mayúsculas oficiales; `PlaceName::forDisplay()` los muestra como se escriben ("Bogotá, D.C.", "Archipiélago de San Andrés, Providencia y Santa Catalina"). Lo guardado no cambia.
- **Mapa (D8):** Leaflet 1.9.4 (BSD-2) con las teselas de OpenStreetMap y su atribución, y un pin dibujado con CSS, sin las imágenes del ícono por defecto.

**Peso:** cada pantalla es un archivo aparte (`import.meta.glob` sin `eager`). El veedor descarga ~86 KB gzip en total: Vue e Inertia 61, axios 19 y su pantalla 5. El panel del Administrador, Leaflet (43 KB) y pdf-lib (176 KB) solo bajan cuando se usan.

Suite: 307 en verde + 1 `todo`.

- **Ajuste aprobado por el usuario (2026-09-28, rama `fix/veedor-entra-a-nuevo-reporte`):** hasta "Mis Reportes" (it. 28), el veedor entra a "Nuevo Reporte" al iniciar sesión o activar su cuenta; `/veedor/dashboard` también lleva ahí. El Administrador entra a su bandeja.

### Iteración 19 — Panel global del Super Administrador (P1)
**Entregable:** alta de organización, Administrador inicial y datos legales.
**Done-when:** Vitest de los formularios de US-001, US-002 y US-011 en verde, con sus mensajes de error.
**Cubre:** US-001, US-002, US-011 (UI).

**✅ Cumplido (2026-09-28):** Vitest 15 en verde (140 en total). Backend `SuperAdminPanelTest`, 17 casos, vistos en rojo antes de implementar (404: no existían las rutas ni las pantallas).

| Pantalla | Qué hace |
|---|---|
| **Organizaciones** `/admin/organizations` (US-001, US-011) | Listado con NIT, subdominio y estado; "Editar NIT" abre un formulario inline por fila (pide el detalle con `GET /admin/organizations/{id}` para tener el `id`, que el listado no expone) y guarda con `PUT .../nit`. |
| **Nueva organización** `/admin/organizations/new` (US-001, US-002) | Un solo formulario: nombre, NIT y subdominio, más un Administrador inicial opcional (nombre y correo) — si se llena uno de los dos, se exige el otro. `RegisterOrganizationWithAdministrator` hace las dos altas en un paso; si el Administrador es inválido, deshace el alta completa (borra el tenant recién creado, libera el subdominio). |

- **Backend nuevo:** `GET /admin/organizations/data` (el listado), `POST /admin/organizations` (alta + Administrador inicial), `GET /admin/organizations/{id}` y `PUT /admin/organizations/{id}/nit`. Todas detrás de `auth:web` (guardia del Super Administrador); sin sesión, redirigen a `/login`.
- `/dashboard` ahora abre el listado de organizaciones, en vez del texto de marcador de la it. 4.
- `Tenant::statusLabel()`: hoy solo "Activa" (único estado que existe); suspender/dar de baja (US-003a/b) lo extienden en la it. 20.
- Reutiliza `LoadState`, `useLoader` y `services/errors.js` de la it. 18 — sin layout aparte: el panel global es una sola sección de `AppLayout`, no una barra de navegación con varias pantallas como la del Administrador.
- **Aislamiento del test de acceso sin sesión:** `actingAs()` dentro de un test deja la sesión autenticada para las peticiones siguientes del mismo test; una comprobación de "invitado" tiene que ir en su propio `it()`, no reutilizar el mismo request tras un `actingAs()` anterior (se encontró al escribir esta prueba: pasaba con 200 en vez de redirigir).

Suite: 325 en verde + 1 `todo`, estable en dos corridas.
**Cubre (además):** US-013 (dispara la sincronización SECOP al dar de alta, ya existente desde la it. 3).

---

## Fase P2 — Semanas 3-4 · Verificación pública y operación

### Iteración 20 — Ciclo de vida de cuentas y organizaciones
**Entregable:**
- suspender y reactivar organizaciones, con el mapa visible y aviso, y los reportes offline conservados; **reactivar despacha `SyncSecopContracts::dispatch($tenant->id)`** y cierra el `todo` de US-013 que quedó en `SyncSecopContractsTest` (it. 7);
- desactivar y reactivar veedores;
- restablecer contraseña.

**Done-when:** US-003a (6 casos), US-006 (3), US-041-USR (2) y US-039-USR (5) en verde.
**Cubre:** US-003a, US-006, US-039-USR, US-041-USR · R-AUD-01, R-USR-03, R-VC-01.

**✅ Cumplido (2026-09-28, con Opus xhigh):** la iteración se subió de Sonnet a Opus al abrirla, porque revoca sesiones abiertas y bloquea organizaciones (control de acceso).

Backend en verde, vistos en rojo antes de implementar (404 o clases inexistentes):
- `SuspendOrganizationTest`: US-003a, 6/6 casos más 4 derivados;
- `DeactivateObserverTest`: US-006, 3/3, y US-041-USR, 2/2, más 5 derivados;
- `PasswordResetTest`: US-039-USR, 5/5 casos más 3 derivados;
- se cerró el `todo` de US-013 en `SyncSecopContractsTest`: reactivar despacha `SyncSecopContracts`.

Vitest: 158 en verde (18 nuevos). Suite: 355 en verde, estable en dos corridas, ya sin `todo`.

- **Suspender y reactivar organizaciones** (US-003a), desde el panel global, con confirmación y auditoría (`organization.suspended` / `.reactivated`):
  - suspendida, ninguno de sus usuarios entra: el login responde "La organización veedora ha sido temporalmente suspendida. Contacte a soporte";
  - no acepta reportes: la app recibe 403 con ese motivo y conserva los suyos;
  - su sitio público sigue en línea. Cada pantalla pública recibe `organizationNotice` con el aviso, que el mapa mostrará en la it. 26, y las evidencias publicadas siguen con su sello.
  - Reactivar deja entrar de inmediato y dispara la sincronización SECOP. Un reporte capturado hace 3 días entra sin marca de hora sospechosa.
- **Desactivar y reactivar veedores** (US-006, US-041-USR), desde el panel del Administrador, con confirmación y auditoría (`observer.deactivated` / `.reactivated`):
  - el estado es "Inactivo";
  - sus reportes se conservan con su autoría;
  - desactivar también anula una invitación pendiente, así el enlace viejo no reactiva la cuenta;
  - reactivar devuelve la misma cuenta y contraseña;
  - un Administrador no aparece como veedor (404).
- **Revocación inmediata:** el middleware `EnsureAccountIsUsable`, en todas las rutas autenticadas de la organización, lee **de la base** en cada petición si la organización está suspendida y si la cuenta sigue activa (`Tenant::freshStatus()`, `is_active`). No confía en el usuario ni en la organización que ya están en memoria, que pueden ser anteriores al cambio. Así cierra la sesión en su siguiente petición: la app recibe 403 con el motivo y una pantalla vuelve al login con el mensaje. Las sesiones se guardan en la base central sin la organización del usuario, y los ids se repiten entre organizaciones, así que borrar filas de sesión por usuario no es posible. **Prueba de mutación:** si el middleware usa el `is_active` en memoria, el test de revocación falla justo después de desactivar (200 en vez de 403).
- **Restablecer contraseña** (US-039-USR), en el subdominio y también para el Super Administrador en el panel global:
  - enlace por correo, de 60 minutos y un solo uso;
  - la respuesta es siempre "Si el correo existe, recibirás un enlace";
  - no se envía el enlace a una cuenta inactiva ni a una invitación pendiente: la invitación se acepta con su propio enlace, dentro de sus 48 horas;
  - el broker de Laravel se arma en cada llamada (`PasswordResets`), porque el del manager guarda su conexión a la base y dentro de una organización tiene que ser la suya;
  - la pantalla del enlace muestra el motivo si venció o ya se usó. El login ofrece "¿Olvidó su contraseña?" y confirma el cambio;
  - **prueba de mutación:** si la validez del enlace siempre diera "válido", los dos casos de enlace vencido o usado fallan.
- **Hallazgos en los tests:**
  - un dataset de Pest con un parámetro tipado `Closure` se entrega tal cual, sin evaluarlo. El "estropear el enlace" no hacía nada hasta quitar la capa extra; se encontró porque el caso nunca había estado en rojo por la razón correcta;
  - `->map->only()` no sirve sobre arreglos.
- `RowAction.vue`: una acción por fila con confirmación opcional y el motivo del servidor en la fila; la usan los dos paneles.
- **Pendiente para después:** restablecer la contraseña no cierra otras sesiones abiertas de esa cuenta; no lo pide US-039.

### Iteración 21 — Perfil, parámetros y consulta de auditoría
**Entregable:**
- nombre y logo de la organización, con limpieza de SVG;
- pantalla de parámetros globales (usa el historial de la it. 6);
- consulta del log de auditoría por alcance.

**Done-when:** US-007 (15 casos), US-038-CFG (10) y US-043-MON (3) en verde.
**Cubre:** US-007, US-038-CFG, US-043-MON · R-SEC-04, R-VC-03, R-CFG-02, R-AUD-04 (consulta).

**✅ Cumplido (2026-09-28, con Opus xhigh):** backend en verde:
- `OrganizationProfileTest`: US-007, 15/15 más 4 derivados;
- `SvgSanitizerTest`: 8;
- `ParametersPanelTest`: US-038-CFG, 10/10 más 9 derivados;
- `NightlyScheduleTest`: 3;
- `AuditLogQueryTest`: US-043-MON, 3/3 más 3 derivados.

Todos vistos en rojo antes de implementar (404 o clases inexistentes). Vitest: 180 en verde (22 nuevos). Suite: 410 en verde.

- **Al abrir la iteración, US-038-CFG se ajustó a Stellar:** "umbral de saldo del Relayer, 5 → 10 POL" pasa a "umbral de saldo de la patrocinadora, 50 → 80 XLM", en el Gherkin, los criterios y la historia.
- **Nombre de fantasía y logo** (US-007), pantalla `/admin/organization` del Administrador:
  - columnas nuevas `tenants.display_name` y `logo_path`, aparte del nombre legal y el NIT, que se muestran sin editar;
  - el logo se valida por lo que el archivo **es**, no por su nombre: PNG, JPG o SVG, hasta 2 MB, y al menos 128x128 px si es un mapa de bits. Un SVG no tiene mínimo: es un dibujo y escala sin perder calidad (derivada);
  - se guarda en el disco de objetos bajo `{organización}/profile/`, con el hash en el nombre, así la URL cambia con el logo y nadie ve uno viejo en caché;
  - los veedores lo ven en su próxima pantalla: `organization` y `organizationLogo` se comparten con cada página y se leen **de la base**. El encabezado de "Nuevo Reporte" y del panel muestran nombre y logo.
- **SVG limpio** (R-SEC-04): `SvgSanitizer` propio, con lista de lo permitido (elementos y atributos que solo dibujan). `enshrined/svg-sanitize` es GPL y el proyecto es MIT.
  - Quita scripts, manejadores `on*`, `foreignObject`, animaciones, hojas de estilo, imágenes y cualquier referencia que salga del documento (`href` y `url()` solo hacia `#…`).
  - En `style` limpia declaración por declaración.
  - Rechaza un DOCTYPE o entidades (XXE, *billion laughs*).
  - Segunda barrera: `/organization/logo` se sirve con `Content-Security-Policy: default-src 'none'; …; sandbox` y `X-Content-Type-Options: nosniff`.
  - **Prueba de mutación:** si el filtro de atributos deja pasar todo, fallan 4 tests.
- **Parámetros globales** (US-038-CFG), pantalla `/admin/parameters` del Super Administrador:
  - `ConfigurableParameter` define los 5 configurables, cada uno con su etiqueta, unidad y regla. Rangos derivados: geocerca de 50 a 5000 m, porque bajo la precisión del GPS nunca se cumpliría; ventana de 1 a 60 meses; invitación de 1 a 720 h; umbral mayor que 0 XLM; hora `HH:MM`.
  - `FixedParameters` muestra los fijos, que toman su valor de las reglas mismas (`GpsReading`, `EvidenceSet`, `SuspiciousCaptureTime`).
  - Cada cambio es una versión nueva, con auditoría `parameter.changed` del valor anterior y el nuevo.
  - Cada fila del test comprueba **el código que usa el parámetro**:
    - a 400 m ya no se puede reportar;
    - un contrato terminado hace 7 meses ya no es reportable;
    - una invitación nueva vence a las 72 h, y el correo lo dice;
    - la sincronización se programa a las 03:30.
- **Dos parámetros que no se leían de la base:**
  - La vigencia de las invitaciones estaba fija en 48 h en `InviteObserver` y `AssignInitialAdministrator`; ahora sale del parámetro, y `WelcomeNotification` escribe las horas reales.
  - La hora de sincronización ahora la programa `NightlySchedule`. El comentario de `routes/console.php` decía que un cambio exigía reiniciar el proceso, y era falso: `schedule:work` lanza `schedule:run` como proceso nuevo cada minuto, así que rige desde el minuto siguiente.
  - El cálculo de riesgo pasa a ir **una hora después de la sincronización**. Antes estaba fijo a las 03:00: con la sincronización movida a las 03:30 habría corrido antes, con los contratos del día anterior.
- **Log de auditoría** (US-043-MON):
  - `AuditLogQuery`: el Super Administrador ve todo; el Administrador solo lo de su organización, y una entrada de otra responde 404;
  - lo más reciente primero, de 20 en 20;
  - `AuditLabels` pone en palabras cada acción ("Cambió el NIT", "Retiró una evidencia"…) y cada actor;
  - pantallas `/admin/audit` en el panel global y en la organización, con el componente compartido `AuditLog`. Las fechas llevan el año con 4 cifras.
- **Paneles:**
  - `SuperAdminLayout`, con barra de navegación Organizaciones, Parámetros y Auditoría;
  - el panel del Administrador suma "Organización", desde donde se abre su registro de auditoría.

### Iteración 22 — Robustez del sellado y monitoreo
**Entregable:**
- reintentos con backoff hasta 5, "Falla de Sellado" con banner, alerta de cola estancada a las 2 h y red de Stellar (RPC) caída o patrocinadora sin saldo (US-021 se ajusta a Stellar al abrir la iteración);
- panel de salud de SECOP;
- monitoreo externo de caídas de más de 5 minutos, por Email y Webhook.

**Done-when:**
- US-021 (5 casos) y US-014 (3) en verde;
- US-044-MON (2 casos) verificado con la configuración de la herramienta externa y un test del receptor de webhook.

**Cubre:** US-014, US-021, US-044-MON · R-INT-01, R-MON-01.

**✅ Cumplido (2026-09-29, con Opus xhigh):** backend en verde, visto en rojo antes de implementar (clases, columnas o rutas inexistentes):
- `SealingRetriesTest`: US-021, 5/5 más 4 derivados;
- `StellarRpcTest`: 2;
- `SecopHealthTest`: US-014, 3/3 más 3 derivados;
- **US-044-MON (2/2)**, verificado con la configuración real de la herramienta externa: `make monitoring-check`.

Vitest: 187 en verde (7 nuevos). Suite: 427 en verde. `make test-stellar` contra la red local: 4/4.

- **Al abrir la iteración, US-021 se ajustó a Stellar:**
  - "el nodo RPC" pasa a "la red de Stellar (su nodo RPC)";
  - "el relayer gestionado no responde" pasa a "la red de Stellar no confirma la transacción en 5 minutos". La historia ya hablaba de transacciones "pending/dropped";
  - se corrigió una incoherencia de la propia historia: listaba 5 retrasos (1 min … 6 h), pero con la falla definitiva en el **quinto intento** solo caben 4 esperas: 1 min, 5 min, 15 min y 1 h.
- **Reintentos** (US-021, R-INT-01): los intentos los cuenta **cada sello** (`report_seals.attempts`), no la cola de Laravel. Si los contara la cola, la pausa por falta de saldo los gastaría y mandaría evidencias a "Falla de Sellado" sin que nada fallara. `SealingRetryPolicy` fija 5 intentos y las esperas 60, 300, 900 y 3600 s.
  - Tras el quinto: "Falla de Sellado" (nuevo estado `failed`), sin sexto intento automático. El error queda en `last_error`, para el soporte técnico.
  - El veedor ve "En Cola" (`SealStatus::veedorLabel()`, US-010): nunca un error.
- **Dos huecos del sellado que se cerraron:**
  1. Si el RPC no respondía, `Http::…->throw()` lanzaba una excepción de HTTP que el trabajo no reconocía, y la cola lo reintentaba de inmediato, sin espera. Ahora `StellarRpc` la convierte en `NetworkUnavailable` y cuenta como un intento con su espera.
  2. Una transacción que la red nunca incluía dejaba la evidencia "Transmitiendo" para siempre. Ahora `ConfirmSeal` la da por fallida a los 5 minutos, o si la red la rechazó, y la devuelve a la cola con la misma política. Si la primera entra después, el reenvío recibe "Hash ya registrado" y toma el sello que ya existe. Mientras el RPC no responde, espera sin gastar intentos.
  - También cuentan como intento las fallas de red al verificar el saldo durante una pausa y al buscar un sello "ya registrado".
- **El hash que manda la red:** `markSealed` se quedaba con el hash guardado. Tras un reenvío, la transacción incluida puede ser la primera, y el recibo (it. 23) habría apuntado a otra; ahora prima el hash que informa la red.
- **Banner:** el panel del Administrador muestra en rojo "Alerta: N evidencias no pudieron ser selladas…", con el singular cuando es una. El número se comparte solo con el Administrador (`sealingFailures`); al veedor, nunca.
- **Cola estancada:** `CheckSealingQueue`, cada 15 minutos. Busca evidencias con más de 2 horas sin sellar (recibidas, en cola o transmitiendo) y avisa por correo al Super Administrador, con las organizaciones afectadas, y a los Administradores de cada una. Hay una sola alerta por evidencia (`stuck_alerted_at`).
- **Salud de SECOP II** (US-014), pantalla `/admin/secop-health` del Super Administrador:
  - la última corrida, en hora de Colombia, con el estado "Success", tal como lo pide la historia;
  - procesados, con el desglose de nuevos y actualizados **por organización según su propio territorio**. La sincronización ahora lo guarda (`per_organization`, con `WatchedTerritories::coversLocation()`);
  - descartados por DIVIPOLA, con sus lugares;
  - si falló: indicador rojo y "Falla de sincronización con SECOP II: … (Error HTTP 504 Gateway Timeout). Reintento programado en N minutos.". El error queda guardado en palabras, y el momento del reintento sale del `backoff()` del trabajo (`next_retry_at`).
- **Monitoreo externo** (US-044-MON), con [Gatus](https://github.com/TwiN/gatus) (Apache-2.0), en `ops/monitoring/`. Su configuración es un YAML versionado y probado; Uptime Kuma la guarda en su propia base.
  - Corre **en otra máquina** que la de GovTrace y pide `/up` cada minuto. Alerta por correo y por webhook tras 6 fallas seguidas, más de 5 minutos, y avisa al recuperarse.
  - `make monitoring-check` levanta Gatus de verdad, con esa configuración, contra un nginx que se detiene, un receptor de webhooks en Python y Mailpit, con chequeos de 1 segundo: la misma regla en segundos. Una caída de 3 s no alerta; una de 9 s manda el webhook y el correo, y al volver, la recuperación. Estable en dos corridas.
  - Jenkins lo corre al construir un tag de release.
  - Hallazgo: Gatus (Go) no manda credenciales SMTP por una conexión sin cifrar, como debe ser; el Mailpit de la prueba usa STARTTLS con certificado propio.
- **Hallazgos en los tests:**
  - `Notification::fake` reconoce al destinatario por clase e id, y cada organización numera sus usuarios desde 1: para afirmar que "el Administrador de Ciénaga no recibió", su id no puede coincidir con el de Santa Marta;
  - Eloquent guarda una fecha en la zona del Carbon, sin convertirla a UTC;
  - `schedule:list` cambia su alineación con una expresión más larga, así que las expresiones regulares ahora aceptan espacios.

### Iteración 23 — Recibos, descarga con prueba y script independiente
**Entregable:**
- Recibo de Inmutabilidad privado y público;
- descarga del archivo exacto con su prueba de inclusión;
- script de verificación en `tools/verify/`, que consulta todas las direcciones históricas del contrato.

**Done-when:** US-023 (3 casos), US-025 (2), US-026 (3) y US-046-INT (4) en verde. El script se prueba contra la red local *standalone* con los vectores de la it. 13. US-023, US-025 y US-046-INT se ajustan a Stellar al abrir la iteración. El recibo lleva TxID, número y hora del ledger y el enlace a un explorador de Stellar (hay que elegir cuál). Hay que decidir cómo se lee un sello archivado por TTL.
**Cubre:** US-023, US-025, US-026, US-046-INT · R-INT-04, R-MNT-01, R-MNT-02.

**✅ Cumplido (2026-09-29, con Opus max):** en verde, visto en rojo antes de implementar (rutas, métodos o columnas inexistentes):
- `SealReceiptTest`: US-023, 3/3; US-025, 2/2; más 9 derivados;
- `EvidenceDownloadTest`: US-026, 3/3 (4 casos); más 6 derivados;
- `StellarRpcTest`: 3 nuevos;
- `tools/verify/test/verify.test.mjs` (Vitest): **US-046-INT, 5/5** (6 casos), más 25 de StrKey, XDR, casos negativos y la línea de comandos. La historia tenía 4 casos; al ajustarla a Stellar se sumó "Sello archivado por la red";
- `tools/verify/test/merkle.test.mjs`: los vectores de la it. 13, ahora contra la implementación del script.

Suite: 454 en verde. Vitest: 219 en verde (32 nuevos). `make test-stellar` contra la red local: 8/8. `make verify-check`: verde.

Cada regla nueva se comprobó rompiéndola a propósito: 12 en el servidor, 15 en el verificador y 2 en la auditoría de reenvíos. Todas quedaron atrapadas por su test.

- **Al abrir la iteración, US-023, US-025 y US-046-INT se ajustaron a Stellar:**
  - "bloque" pasa a "ledger", y Polygonscan a Stellar Expert;
  - "re-sellada tras una reorganización" pasa a "reenviada tras una transacción que no se incluyó": en Stellar no hay reorganizaciones (R-BLK-06).
- **Recibo** (US-023 privado, US-025 público):
  - `GET /reports/{id}/receipt` es solo para el veedor, y solo de sus reportes; de uno ajeno responde 404. `GET /public/reports/{id}/receipt` es sin sesión, para una evidencia publicada o retirada; oculta o rechazada, 404;
  - lleva la raíz, la transacción, el ledger, la hora de cierre del ledger (la de la red, no la del servidor), el contrato y "Ver en Stellar Expert";
  - mientras no está sellada, incluso en "Falla de Sellado", el veedor solo ve el mensaje "⏳ Su evidencia está en proceso de sellado…".
- **Descarga con su prueba** (US-026):
  - `GET /public/evidences/{id}/download` entrega el binario exacto que se selló, con nombre por hash (`evidencia-<sha12>.jpg`), nunca el del teléfono;
  - `GET /public/evidences/{id}/proof` entrega la prueba de inclusión (`govtrace-proof/1`): las hojas, el camino de Merkle, la raíz, la obra y dónde está en Stellar;
  - solo de evidencias publicadas. Una retirada conserva su recibo, pero no sus archivos;
  - sin caché (`no-store`), para que un retiro valga desde ese instante;
  - las tarjetas de la línea de tiempo enlazan las tres cosas.
- **Script independiente** en `tools/verify/` (US-046-INT):
  - Node 20 o más nuevo, sin dependencias, con README;
  - comprueba el hash del archivo, las hojas y el camino de Merkle, y lee el sello directamente de la red;
  - códigos de salida: 0 AUTÉNTICO, 1 NO COINCIDE, 2 no se pudo verificar;
  - `make verify-check`: GovTrace sella, publica y deja descargar una evidencia en la red local. El script la comprueba en un contenedor que solo ve su carpeta y los dos archivos, en una red de Docker `--internal` donde solo está el nodo de Stellar. Una copia alterada da NO COINCIDE. Jenkins lo corre en "Test Stellar".
  - El árbol de Merkle en JavaScript es uno solo: vive en `tools/verify/lib/merkle.mjs`, y `resources/js/lib/merkle.js` lo reexporta. Un test comprueba que es la misma función, no una copia.
- **Hallazgos:**
  - Con una organización activa, `storage_path()` es el de ella (el bootstrapper de archivos de Stancl).
  - El sellado ahora escribe en el log de auditoría, en la base central, sin `RefreshDatabase`. Los tests de sellado que no lo limpiaban dejaban entradas a los siguientes; ahora cinco archivos más lo limpian.
  - El RPC de Stellar revisa como máximo 10.000 ledgers por llamada a `getEvents`, y la red local ya va en el protocolo 27.
  - El hook de pre-commit no tenía timeout, y la suite ya pasa de 10 minutos con la máquina cargada. Ahora tiene 30 (`.claude/settings.json`).

**Decisiones de la iteración, para confirmar:**
1. **Explorador: Stellar Expert.** La URL sale de la red (`https://stellar.expert/explorer/testnet` o `/public`), o de `STELLAR_EXPLORER_URL`. La red local no tiene explorador público, y el recibo va sin el botón.
2. **Un sello archivado por su vigencia (TTL) se lee con `getLedgerEntries`, sin restaurarlo.** Sus datos siguen en la red: no hay que pagar comisión ni tener cuenta. El script avisa que está archivado, y la verificación vale igual.
3. **Cada transacción vale 4 minutos** (time bounds), menos que los 5 que espera `ConfirmSeal`. Cuando una se da por perdida y se reenvía, la vieja ya no puede entrar a un ledger. Así, la transacción del recibo es siempre la que selló.
4. **La transacción que selló la dice la red.** Si un envío se quedó sin respuesta pero entró, el reintento recibe "Hash ya registrado". Entonces `findSeal` busca el evento `sealed` del contrato en ese mismo ledger (`getEvents`), y así el recibo no muestra una anotada que no entró. El RPC guarda 7 días de eventos por omisión. Si ya no lo tiene, queda la anotada; y sin ninguna, el botón lleva al ledger.
5. **El reenvío queda solo en el log de auditoría** (`seal.resent`, actor "Sistema"), con la transacción que no entró y la que la reemplazó. Así lo piden los criterios de US-023 y US-025. Un reintento que encuentra su misma transacción no es un reenvío y no se registra.
6. **Una foto con EXIF o XMP se rechaza en el servidor**, con el mensaje "La foto conserva metadatos EXIF, como la ubicación del teléfono. Envíela desde la app GovTrace, que los quita.". No se limpia, porque eso cambiaría los bytes que se sellan. La PWA ya los quita (it. 16); esto cubre a cualquier otro cliente. También se detectan el XMP extendido y los bytes de relleno que podrían esconder un segmento.
7. **La prueba no lleva el JSON de metadatos** (R-PRIV-02): trae el comentario y las coordenadas exactas. Su hash sí va, porque es una hoja, y no permite reconstruirlo: lleva el seudónimo del veedor, un HMAC con llave del servidor (D7).
8. **El contrato se guarda en cada sello** (`report_seals.contract_id`, escrito una sola vez). Los sellos que ya existían toman el contrato configurado.
9. **Confianza del script** (R-MNT-01):
   - los contratos oficiales están en `tools/verify/contracts.json`, versionado. Hoy es el de la testnet; la red pública todavía no tiene;
   - el script pregunta por la raíz en **todos** los de esa red, también los anteriores, en una sola consulta;
   - el contrato que nombra la prueba es solo informativo: cualquiera puede desplegar uno parecido. Otra instalación de GovTrace pasa el suyo con `--contract`.

### Iteración 24 — Mapa y línea de tiempo (datos)
**Entregable:**
- pines livianos con reglas de color (evidencia publicada más reciente, peor estado de la ficha, Terminados en ventana);
- línea de tiempo bajo demanda con coordenadas aproximadas;
- agrupación de contratos en una ficha.

**Done-when:** US-027 (12 casos), US-029 (5) y US-045-INT (3) en verde.
**Cubre:** US-027, US-029, US-045-INT · R-MAP-01, R-MAP-02, R-PRIV-02, R-INT-05.

**✅ Cumplido (2026-09-29, con Opus max):** backend en verde, visto en rojo antes de implementar (rutas inexistentes):
- `PublicMapTest`: US-027, 12/12, más 7 derivados;
- `WorksiteViewTest`: US-029, 5/5, más 3 derivados;
- `GroupContractsTest`: US-045-INT, 3/3, más 10 derivados (6 casos de reglas y validación, más dos carreras con un veedor que reporta en el mismo instante).

Suite: 494 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 20 casos, todos atrapados por su test. Las pantallas son de la it. 26 (mapa y vista de obra) y de la it. 29 (agrupación).

- **Pines** (US-027): `GET /public/worksites` trae solo `[{id, lat, lng, color_pin}]` (R-MAP-02), con `color_pin` en `green`, `yellow` o `red`. Son tres consultas, sin importar cuántos pines haya.
  - El color es el peor entre dos: el de la evidencia **publicada** más reciente ("Retraso" amarillo, "Abandono" rojo, "Avance" verde) y el de los contratos de la ficha (rojo si uno venció y SECOP lo sigue mostrando en ejecución).
  - Las ocultas no cuentan, y las retiradas tampoco.
  - Cada organización ve solo sus fichas (R-MAP-01): viven en su propia base.
- **Vista de obra** (US-029): `GET /public/worksites/{id}` trae el nombre de la ficha (o el objeto de su contrato), sus contratos con la tarjeta de US-017, y la línea de tiempo, bajo demanda.
  - Cada tarjeta lleva fecha y hora, clasificación, comentario, `approximate_location` (a unos 100 m), sus archivos y el sello con su recibo, para el botón "Verificar Sello Blockchain".
  - Cada foto lleva `photo_url`: `GET /public/evidences/{id}/photo` la entrega *inline*, con los mismos bytes que se sellaron, para la miniatura y el visor.
  - La lápida no lleva archivos, comentario ni lugar.
  - Se cerró el caso de US-017 que la it. 8 dejó sin probar: un contrato anulado cuya obra ya tenía evidencias sigue mostrándolas, con su aviso.
- **Agrupación** (US-045-INT): `POST /worksites/group` es del Administrador. Pide un nombre (hasta 150 caracteres) y dos o más contratos distintos, que existan y sean del territorio de la organización. Queda en el log de auditoría (`worksite.contracts_grouped`), y el panel muestra el nombre de la ficha.
- **La regla de "vencido y en ejecución" es una sola:** `Contract::overdueInExecution()`, que usan el cálculo nocturno (US-034) y el mapa.
- **Carreras con un veedor que reporta en el mismo instante,** reproducidas con una segunda conexión a la base, como otro proceso:
  - si el veedor vincula primero el contrato, la agrupación falla limpia y pide reintentar;
  - si la agrupación funde la ficha que el reporte acababa de leer, el reporte fallaba con 404. Ahora `CreateReport` la vuelve a buscar y el reporte llega a la ficha agrupada.
- **Hallazgo en los tests:** el cliente de pruebas lleva una sola sesión para todos los subdominios, y `ScopeSessions` rechaza con 403 la de otra organización. En un navegador no pasa: cada subdominio tiene su cookie (`SESSION_DOMAIN=null`). El helper `publicGet()` entra como visitante sin sesión.

**Decisiones de la iteración, para confirmar:**
1. **Cuál es la "más reciente":** la evidencia por **fecha de captura**, no por fecha de publicación. Una evidencia vieja que se publica tarde no le gana a una más nueva.
2. **El vencimiento se calcula al pedir el mapa,** con los datos de SECOP, y no se lee la marca nocturna `at_risk`. Así, una agrupación cambia el pin en el acto.
3. **Toda ficha anclada tiene pin,** sea cual sea el estado de sus contratos: cerrados hace más de 12 meses, o anulados. Su evidencia publicada sigue siendo pública, y los elefantes blancos son justo lo que el mapa debe mostrar. Leí "Terminadas/Liquidadas dentro de la ventana de 12 meses siguen las mismas reglas" como una regla de color, no como un corte de visibilidad. Si prefieres quitarlas del mapa, es un filtro.
4. **Los pines también van a unos 100 m** (3 decimales, sobre una grilla fija, no con ruido aleatorio). La ubicación de una ficha es la del primer veedor, exacta (First-Touch), y R-PRIV-02 la protege igual que la de su evidencia.
5. **Un contrato "Suspendido" en plazo es verde.** El título de US-027 dice "amarillo suspendidas", pero sus criterios y su Gherkin no lo recogen. Seguí los criterios; si quieres el amarillo, es una regla más.
6. **No hay miniaturas generadas en el servidor.** `photo_url` sirve la foto sellada, que ya viene optimizada (lado mayor de 1920 px, unos 200 a 400 KB), y la pantalla la carga en diferido. La imagen de Docker no trae GD; si pesa en datos móviles, se agrega GD y un tamaño de 320 px.
7. **Agrupar no mueve reportes.** La obra de un reporte es parte de lo que envió el veedor (R-TA-02), y su referencia está sellada en la red. Por eso:
   - la ficha con reportes recibe a los demás contratos y conserva su ubicación;
   - la ficha que se queda vacía, sin reportes, se borra;
   - dos fichas que ya tienen reportes no se funden: la agrupación se rechaza y lo explica;
   - desagrupar no está en el alcance.
8. **La vista de obra existe también sin ubicación,** para una agrupación nueva, sin pin: sus contratos son públicos de todos modos.
9. **Inconsistencia en la spec, para resolver antes de la it. 27:** R-PRIV-03 dice que el JSON de metadatos "se publica", pero ese JSON trae las coordenadas exactas y el comentario, y R-PRIV-02 lo prohíbe. Hoy no se publica (it. 23, decisión 7): se publica su hash, como hoja del árbol. Propongo corregir R-PRIV-03 en ese sentido. Afecta al validador (it. 27) y a los datos abiertos (it. 34).

### Iteración 25 — Autorización al Super Admin, archivado y resumen
**Entregable:** autorización de 30 días, revocable; archivado mensual con retorno si llega evidencia; resumen del territorio.
**Done-when:** US-042-SEC (5 casos), US-048-MNT (4) y US-049-RPT (2) en verde.
**Cubre:** US-042-SEC, US-048-MNT, US-049-RPT · R-SA-02, R-MNT-04.

**✅ Cumplido (2026-09-29, con Opus xhigh):** backend en verde, visto en rojo antes de implementar (clases, tablas o rutas inexistentes):
- `SuperAdminAuthorizationTest`: US-042-SEC, 5/5, más 5 derivados;
- `ContractArchiveTest`: US-048-MNT, 4/4, más 6 derivados;
- `TerritorySummaryTest`: US-049-RPT, 2/2, más 2 derivados.

Suite: 518 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 15 casos, todos atrapados por su test. Las pantallas (la autorización y el resumen) son de la it. 29.

- **Autorización al Super Administrador** (US-042-SEC, R-SA-02):
  - el Administrador la ve, la otorga o la revoca en `GET · POST · DELETE /authorizations/super-admin`;
  - vale 30 días, hay una sola vigente a la vez, y otorgarla y revocarla quedan en el log de auditoría;
  - `LOCK TABLE` evita que dos clics dejen dos vigentes.
- **El Super Administrador reporta** desde el panel global, en `POST /admin/organizations/{id}/reports`, con el mismo reporte y las mismas reglas que la PWA. `StoreReportRequest` lee ese reporte para los dos, y cada uno queda en el log de auditoría.
- **Archivado mensual** (US-048-MNT, R-MNT-04): `ArchiveOldContracts` corre el día 1 a las 05:00, tras la sincronización y el cálculo de riesgo. Mueve a `archived_contracts` (base central, mismas columnas y mismo id) los contratos cerrados según SECOP hace más de 5 años que ninguna organización tiene en una ficha.
  - Si llega un reporte de uno archivado, `CreateReport` lo devuelve a la base principal antes de validar.
  - La sincronización nocturna, que trae todos los contratos del departamento, mantiene al día la copia archivada, y lo devuelve si SECOP lo reabre.
- **Resumen del territorio** (US-049-RPT): `GET /summary`, del Administrador, trae las obras por color, las evidencias por clasificación y por mes, y los veedores activos.

**Decisiones de la iteración, para confirmar:**
1. **El Super Administrador reporta a través de un miembro de sistema de la organización,** "Super Administrador de GovTrace" (`super-admin@govtrace.invalid`). Un reporte siempre pertenece a un miembro de la organización, y su seudónimo sale de ese miembro (D7).
   - Nadie puede entrar como él: no tiene contraseña, invitación ni rol, y nunca recibe un enlace para restablecer la contraseña.
   - Su reporte llega oculto a la bandeja, como cualquier otro, y el Administrador decide.
2. **Una segunda autorización mientras hay una vigente se rechaza,** no se renueva. Para extenderla, se revoca y se otorga otra.
3. **Qué se archiva:** los contratos "terminado" o "Cerrado" (R-SEC-07) con fecha de fin de hace más de 5 años.
   - Nunca uno en ejecución (sin cerrar, es de los que están en riesgo) ni uno anulado.
   - Tampoco uno que alguna organización, activa o suspendida, tenga en una ficha, aunque la ficha no tenga reportes: su vista pública lo muestra. Es más conservador que "sin evidencias".
4. **El que vuelve por un reporte pasa por las reglas de siempre.** Con la ventana de 12 meses, un contrato cerrado hace 5 años no es reportable: vuelve y el reporte se rechaza, y el archivado siguiente lo vuelve a sacar. Solo se acepta si el Super Administrador amplió la ventana (US-038-CFG).
5. **Qué cuenta el resumen:** todas las evidencias recibidas, publicadas u ocultas, salvo las rechazadas, porque no eran de la obra. Los meses van en la hora de Colombia. Los colores son los del mapa público. Los veedores activos excluyen a los desactivados y las invitaciones pendientes.
6. **Hallazgo, fuera del alcance:** el calendario de tareas corre en UTC, que es la zona de la aplicación. La "sincronización 02:00" (R-CFG-02, US-038-CFG) corre a las 21:00 de Colombia, y el cálculo de riesgo a las 22:00. Si la hora del parámetro es de Colombia, basta con `->timezone('America/Bogota')` en `NightlySchedule`. No lo cambié sin confirmarlo.

### Iteración 26 — Sitio público: mapa, vista de obra y línea de tiempo (la pantalla pública central)
**Entregable:** mapa Leaflet/OSM con pines por color; vista de obra con la tarjeta del contrato y la línea de tiempo (visor, lápidas, botón "Verificar Sello Blockchain"); aviso de organización suspendida.
**Done-when:** Vitest de los estados de US-027, US-029 y US-017 en verde (sin obras, carga, lápida, badge de anulado).
**Cubre:** US-017, US-027, US-029 (UI) · R-AUD-01 (UI).

**✅ Cumplido (2026-09-29, con Opus xhigh):** en verde, visto en rojo antes de implementar (componentes y rutas inexistentes):
- Vitest, 23 nuevos:
  - `Public/Map.test.js`: 6 (US-027: carga, error y reintento, sin obras, pines por color con su leyenda, tocar un pin, aviso de suspendida);
  - `Public/PinsMap.test.js`: 3 (teselas de OpenStreetMap, cada pin en su lugar con su color y su nombre accesible, qué pin se tocó);
  - `Public/Worksite.test.js`: 14. US-017, 3/3 de pantalla (tarjeta, datos tal cual de SECOP, badge de anulado), más la ficha agrupada. US-029: línea de tiempo, visor, coordenadas aproximadas y lápida, más el PDF y el sello. Además: carga, error, sin evidencias y aviso de suspendida;
- Pest: `PublicPagesTest`, 3 (las dos páginas sin sesión, y abiertas con aviso mientras la organización está suspendida, R-AUD-01).

Suite: 521 en verde. Vitest: 242 en verde. Cada regla de pantalla se comprobó rompiéndola a propósito: 6 casos. El que sobrevivía (la lápida) llevó a endurecer el test: ahora la tarjeta oculta fotos y comentario aunque una respuesta los trajera por error.

- **Rutas públicas:** el mapa está en `/`, que era un texto de prueba, y la vista de una obra en `/worksite/{id}`. Ninguna pide sesión.
- **Mapa:** Leaflet con OpenStreetMap, cargado solo al aparecer, como en la it. 18. Cada pin es un punto de color, accesible con teclado y con nombre para lectores de pantalla ("Obra en riesgo"), y hay una leyenda: Normal, Alerta, En riesgo.
- **Vista de obra:**
  - la tarjeta de cada contrato, con el valor en pesos ("$1.250.000.000"), el plazo en meses y "Ver original en SECOP" en una pestaña nueva (`noopener`);
  - la línea de tiempo, con miniaturas que cargan en diferido, visor a pantalla completa (se cierra con "Cerrar", tocando fuera o con Escape), el PDF para descargar, la ubicación aproximada y las lápidas.
- **El aviso de organización suspendida** (R-AUD-01) va en las dos páginas, que siguen abiertas.

**Decisiones de la iteración, para confirmar:**
1. **Tocar un pin abre la página de la obra,** que se puede compartir por su enlace, en lugar de un panel lateral sobre el mapa.
2. **"Verificar Sello Blockchain" despliega, en la tarjeta, el sello de la evidencia:** su recibo público (raíz, transacción, ledger, hora) con "Ver en Stellar Expert". La it. 27 le suma el validador, que comprueba el archivo en el navegador.
3. **Un solo formato de pesos en toda la aplicación,** el de US-017: "$1.250.000.000", sin el espacio que pone `Intl`. También cambia en el listado de contratos del Administrador.
4. **Sin filtros todavía** (US-028, it. 31). El mapa vacío usa el mensaje exacto de US-028.

### Iteración 27 — Validador público, recibo y descarga
**Entregable:** validador con tres modos (contextual, libre y con prueba adjunta); hash y recomposición de Merkle en el navegador; recibo público; botón de descarga.
**Done-when:**
- US-024 (14 casos) en verde con Vitest, usando los vectores de Merkle de la it. 13 y un Smart Contract simulado;
- UI de US-025 y US-026 en verde.

**Cubre:** US-024, US-025, US-026 (UI) · R-VER-01, R-VER-02, R-MNT-01.

**✅ Cumplido (2026-09-29, con Opus xhigh):** en verde, visto en rojo antes de implementar (módulos y rutas inexistentes):
- Vitest, 25 nuevos:
  - `lib/validator.test.js`: **US-024, 13 de sus 14 casos**. El que falta, "no exige registro", se prueba en Pest. Usa el sellado real de la red local de la it. 23 (`tests/fixtures/verify/sealed.json`) y los vectores de Merkle, a través del verificador independiente. Más 5 derivados;
  - `Public/Validator.test.js`: 5;
  - `Public/Worksite.test.js`: el modo contextual en la tarjeta;
  - `Public/Map.test.js`: el enlace al validador;
- Pest: `ValidatorDataTest`, 8, entre ellos "El validador no exige registro".

Suite: 529 en verde. Vitest: 267 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 11 casos, todos atrapados por su test.

- **Al abrir la iteración, US-024 se ajustó a Stellar:** "bloque" pasa a "ledger", Polygonscan a Stellar Expert, y "el nodo RPC de Polygon" a "el RPC de Stellar".
- **Un solo código para el veredicto:** el navegador usa la implementación del verificador independiente (`tools/verify/lib`), que es JavaScript puro (WebCrypto, `fetch`, `atob`). El script y la página dan el mismo veredicto con el mismo código. `lib/validator.js` solo agrega los tres modos, los formatos, el tamaño y los mensajes.
- **Página `/verify`,** sin sesión, con dos modos: un archivo solo (libre) o el archivo con su prueba de inclusión. El modo contextual está en el panel de sello de cada tarjeta. El mapa enlaza al validador.
- **`GET /public/proofs/{sha256}`:** la prueba de un archivo, buscada por su hash; el archivo nunca sale del navegador (R-VER-01). Dice si la evidencia está publicada, retirada o sin publicar. Con `?report=` busca solo en ese reporte, para el modo contextual.
- **Las páginas públicas llevan lo que el navegador necesita para leer la red** (`StellarForBrowser`): el RPC público, la red, los contratos (los de `tools/verify/contracts.json` más el configurado) y el explorador. Comprobé que el RPC de la testnet y el local responden con CORS abierto.

**Decisiones de la iteración, para confirmar:**
1. **El RPC que consulta el navegador es `STELLAR_PUBLIC_RPC_URL`.** Sale de la red: `https://soroban-testnet.stellar.org` en la testnet y `http://127.0.0.1:8100/rpc` en la local. En la red pública hay que configurar uno: sin él, el validador muestra el error de conexión.
2. **GovTrace da la prueba, pero la red decide.** Si la red no confirma una prueba de GovTrace, el modo libre responde "No encontrado" y el contextual "Alterado o Falso". Así lo que diga la base de GovTrace nunca basta para dar un "Auténtico".
3. **La prueba de una evidencia oculta o rechazada se entrega a quien tenga el archivo,** con la nota de US-024. Revela que esa evidencia existe, como pide la historia, pero solo a quien ya tiene sus bytes.
4. **El modo contextual compara contra los archivos de ESA evidencia.** Funciona también en una lápida, cuyo sello sigue disponible.
5. **Formato y tamaño se revisan antes de calcular el hash.** El mensaje para un formato no admitido (un .mp4) es propio, porque la historia no lo fija: "Formato no admitido: el validador acepta fotos JPG o PNG y documentos PDF."
6. **Si GovTrace no responde en el modo libre, el mensaje sugiere la prueba descargada:** con ella basta la red Stellar.
7. **El validador comprueba los archivos, no la hoja de metadatos,** porque el JSON de metadatos no se publica (decisión 7 de la it. 23). Sigue pendiente que resuelvas la inconsistencia entre R-PRIV-03 y R-PRIV-02 (it. 24, decisión 9).

### Iteración 28 — Veedor: Mis Reportes y recibo
**Entregable:** lista con estado técnico y editorial por separado, rechazo con motivo y recibo en la app.
**Done-when:** US-010 (10 casos) en verde (backend y Vitest), más la UI de US-023.
**Cubre:** US-010, US-023 (UI) · R-VC-02, R-USR-02.

**✅ Cumplido (2026-09-29, con Opus xhigh):** en verde, visto en rojo antes de implementar (ruta y pantalla inexistentes):
- Pest: `MyReportsTest`, **US-010, 10/10**, más 3 derivados (orden y contenido, retirado sin motivo, solo para veedores y la pantalla);
- Vitest: `Veedor/MyReports.test.js`, 9: US-010 en pantalla, más carga, error, vacío y pestañas; y la **UI de US-023, 3/3** (recibo sellado, en proceso de sellado, reenviado).

Suite: 542 en verde. Vitest: 276 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 5 casos, todos atrapados por su test.

- **`GET /me/reports`:** los reportes del veedor, solo los suyos (R-VC-02), los más recientes primero, con el estado técnico y el editorial por separado (R-USR-02).
  - El estado técnico usa `SealStatus::veedorLabel()`: "Falla de Sellado" se ve "En Cola" y el error nunca sale (US-021).
  - El motivo solo va si se rechazó.
- **Pantalla "Mis Reportes"** (`/my-reports`): cada reporte con su fecha, clasificación y obra, los dos estados por separado, el motivo del rechazo, y "Ver recibo", que muestra el Recibo de Inmutabilidad (US-023) o el mensaje de sellado en curso.
- **La app del veedor tiene dos pestañas abajo:** "Nuevo Reporte" y "Mis Reportes".
- **`ReceiptDetails`:** una sola forma de mostrar un recibo, para el veedor y para el sitio público.

**Decisiones de la iteración, para confirmar:**
1. **El veedor sigue entrando a "Nuevo Reporte" al iniciar sesión.** Lo pediste "al menos hasta la it. 28", y en la calle reportar es lo primero. "Mis Reportes" queda a un toque, en la pestaña de abajo. Si prefieres que entre a "Mis Reportes", es cambiar el destino de `/veedor/dashboard`.
2. **Cada reporte muestra su obra (la ficha), no un contrato.** Un reporte pertenece a la ficha completa (R-INT-05) y no guarda cuál de sus contratos eligió el veedor.
3. **Sin paginación por ahora.** Un veedor envía pocos reportes; si algún día son cientos, se pagina.
4. **La bandeja de salida con su contador** (US-018) es del modo sin conexión (it. 30).

### Iteración 29 — Paneles de P2 (administrador y Super Admin)
**Entregable:** pantallas de US-003a, US-006, US-007, US-014, US-038-CFG, US-039-USR, US-041-USR, US-042-SEC, US-043-MON, US-045-INT y US-049-RPT, más los banners de US-021.
**Done-when:** Vitest de cada pantalla con sus estados y mensajes exactos en verde.
**Cubre:** UI de las historias de P2 listadas.

**✅ Cumplido (2026-09-29, con Opus xhigh):** ocho de las once pantallas ya existían, con su Vitest, desde la iteración en que se hizo su backend:
- US-003a (suspender y reactivar, `SuperAdmin/Organizations`) y US-006 y US-041-USR (desactivar y reactivar veedores, `Admin/Observers`), de la it. 20;
- US-007 (`Admin/Organization`), US-038-CFG (`SuperAdmin/Parameters`) y US-043-MON (`Admin/Audit` y `SuperAdmin/Audit`), de la it. 21;
- US-014 (`SuperAdmin/SecopHealth`) y los banners de US-021 (`AdminLayout`), de la it. 22;
- US-039-USR (`Auth/ForgotPassword` y `Auth/ResetPassword`), de la it. 20.

Esta iteración hizo las tres que faltaban, en verde y vistas en rojo antes de implementarlas:
- `Admin/Summary` (US-049-RPT), en `/admin/summary`: obras por color, evidencias por clasificación, una tabla por mes ("septiembre de 2026") y veedores activos. Con carga, error, reintento y vacío. 3 tests;
- `Admin/SuperAdminAuthorization` (US-042-SEC), en `/admin/authorization`: explica qué permite, la otorga por 30 días, muestra la vigente y quién la dio, la revoca, y muestra el motivo de un rechazo del servidor. 4 tests;
- **agrupar contratos** (US-045-INT) en `Admin/Worksites`: nombre de la ficha, contratos uno por línea (limpios y sin repetir), "Agregar a la agrupación" en cada contrato, el mensaje de éxito y el de rechazo del servidor. La lista muestra el nombre de la ficha. 3 tests.
- "Organización" enlaza al resumen, a la autorización y al registro de auditoría. 1 test.
- Pest: `AdminPanelTest` sirve las dos pantallas nuevas solo al Administrador.

Suite: 544 en verde. Vitest: 287 en verde (11 nuevos). Cada regla de pantalla nueva se comprobó rompiéndola a propósito: 3 casos, todos atrapados por su test.

**Decisión de la iteración, para confirmar:** el resumen y la autorización son pantallas aparte, enlazadas desde "Organización", como ya lo estaba el registro de auditoría. La barra de abajo ya tiene seis pantallas y no cabe una séptima en un teléfono. Si prefieres el resumen como primera pantalla del panel, en lugar de la bandeja, es cambiar el destino de `/organization/dashboard`.

---

## Fase P3 — Semana 5 o posterior · Resiliencia de campo y reportes

### Iteración 30 — Modo sin conexión
**Entregable:** Service Worker e IndexedDB; bandeja de salida con límites (10 reportes / 50 MB) y vigencia de 7 días con aviso a las 24 h; modal al cerrar sesión; aceptación de un contrato anulado mientras esperaba.
**Done-when:** US-018 (14 casos) en verde: Vitest y **pruebas de extremo a extremo en un navegador real simulando pérdida de señal** (R-TST-03).
**Cubre:** US-018 · R-USR-03, R-TST-03.

**✅ Cumplido (2026-09-29, con Opus xhigh):** **US-018, 14/14**, en verde y visto en rojo antes de implementar:
- Vitest, 22 nuevos:
  - `lib/outbox.test.js`: 9 (guardado tal como se capturó, los 3 límites, las 3 vigencias, los mensajes exactos, el orden);
  - `lib/sync.test.js`: 4 (envío automático, falla al reenviar, qué se conserva y qué se descarta);
  - `Veedor/NewReport.test.js`: 3 (guardado sin conexión, sin intento si el teléfono sabe que no hay señal, almacenamiento lleno);
  - `Veedor/MyReports.test.js`: 4 (el contador, el envío al volver la señal, la falla al reenviar, el aviso de vencimiento);
  - `VeedorNav.test.js`: 2 (cerrar sesión con pendientes y sin ellos);
- Pest: `OfflineReportsTest`, 6 (lugar y hora congelados, contrato anulado mientras esperaba, captura después de la anulación, el momento de la anulación a lo largo de las sincronizaciones, anulado desde siempre, cerrar sesión);
- **Extremo a extremo** (R-TST-03), `make e2e`: Playwright en un Chromium de verdad, contra la app de `make up`. El teléfono pierde la señal al enviar; el reporte queda en IndexedDB; la app abre sin señal (Service Worker) y dice cuántos esperan; al volver la señal, el reporte sube solo y aparece en "Mis Reportes" con su estado. Pasó tres corridas seguidas. Jenkins lo corre en la etapa "E2E".

Suite: 554 en verde. Vitest: 309 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 10 casos, todos atrapados por su test.

- **Bandeja de salida** (`lib/outbox.js`): guarda el reporte tal como se capturó (campos, archivos y hashes) en IndexedDB, un almacén por subdominio.
  - Límites: 10 reportes y 50 MB.
  - Vigencia: 7 días desde la captura, con aviso a las 24 horas; después se descarta.
  - La lógica no sabe dónde guarda: IndexedDB en el navegador, un Map en Vitest.
- **Sincronización** (`lib/sync.js` y `composables/useOutbox.js`): sube el más antiguo primero, al abrir la app, al volver la señal y cada 5 minutos, una sola a la vez.
  - Si el servidor no responde, o no puede recibir el reporte por ahora (su error, una sesión vencida, la organización suspendida), el reporte se queda.
  - Si lo rechaza para siempre (422), se descarta y la app dice por qué.
- **"Nuevo Reporte"** guarda en la bandeja cuando no hay respuesta, o sin intentar si el teléfono sabe que no tiene señal. **"Mis Reportes"** muestra el contador, el aviso de vencimiento y el de falla.
- **Service Worker** (`public/sw.js`): las dos pantallas del veedor abren sin señal (primero la red, después la copia), y los archivos de Vite se sirven desde la caché.
- **Cerrar sesión:** `POST /logout` no existía. Ahora "Salir", en la app del veedor, avisa con el mensaje exacto si hay pendientes. Al confirmar, borra la bandeja y lo que guardó el Service Worker.
- **Contrato anulado mientras el reporte esperaba:** `contracts.cancelled_at` guarda cuándo vio GovTrace la anulación, y un reporte capturado antes entra, oculto y en cola.

**Hallazgos de la prueba de extremo a extremo.** Los tres primeros son bugs previos que los tests no veían, porque usan otra configuración, y rompían la aplicación fuera de ellos:
1. **Caché de base de datos** (`CACHE_STORE=database`, en desarrollo y producción). Crear una organización fallaba: su migración limpiaba la caché de permisos y la buscaba en la base de la organización. Además, la caché de Stancl v3 exige etiquetas, que ese almacén no tiene. Arreglo: `TenantCacheBootstrapper` usa etiquetas si el almacén las tiene (como antes, en los tests) y, si no, un prefijo por organización; la caché de base de datos va siempre en la conexión central. Test: `TenantCacheTest`.
2. **Cola de base de datos** (`QUEUE_CONNECTION=database`). Un trabajo despachado dentro de una organización se guardaba en su base, sin tabla `jobs`: **crear un reporte respondía 500**. Arreglo: la cola siempre en la conexión central (el trabajo lleva su organización). Test: `TenantQueueTest`.
3. **Frontend en los subdominios.** `asset()` mandaba `public/build` a la ruta de archivos de la organización (`/tenancy/assets/…`, que responde 404). En un navegador de verdad, las pantallas de las organizaciones cargaban sin JavaScript ni estilos. Arreglo: `asset_helper_tenancy => false` (el logo tiene su propia ruta). Test: `TenantAssetsTest`.
4. **La base de desarrollo no tenía DIVIPOLA.** Sin él no se puede configurar un territorio. La prueba lo carga; conviene que `make setup` también lo haga.
5. **Si registrar una organización falla a mitad de camino,** queda una organización sin dominio que ocupa el NIT. No lo cambié: con los arreglos de arriba ya no falla, pero `RegisterOrganization` debería deshacer lo creado.

**Decisiones de la iteración, para confirmar:**
1. **Va a la bandeja solo lo que no tuvo respuesta,** o lo que se envió sin señal. Un rechazo del servidor al enviar se muestra, como antes, para que el veedor lo corrija.
2. **Un reporte pendiente que el servidor rechaza con 422 se descarta,** con su motivo: reintentarlo no cambiaría la respuesta.
3. **Una anulación que GovTrace no vio ocurrir no lleva fecha:** un contrato que llegó ya anulado, o que estaba anulado antes de esta columna, no acepta reportes atrasados.
4. **El Service Worker guarda solo las dos pantallas del veedor y el frontend compilado.** Cerrar sesión borra esas copias, para que en un teléfono compartido el siguiente no vea las del anterior.
5. **Playwright corre en su contenedor oficial con la red del host,** porque Chrome resuelve `*.localhost` a 127.0.0.1, donde escucha el proxy. En el host no hace falta instalar nada.

### Iteración 31 — Obras cercanas y filtros del mapa
**Entregable:** sugerencia de hasta 5 obras a menos de 500 m con Haversine (D10), y filtros de estado, fechas, presupuesto y municipio.
**Done-when:** US-019 (7 casos) y US-028 (3) en verde.
**Cubre:** US-019, US-028.

**✅ Cumplido (2026-09-29, con Opus xhigh):** en verde, visto en rojo antes de implementar (rutas y pantallas inexistentes):
- Pest: `NearbyWorksitesTest`, **US-019, 7/7**, más 3 derivados (la distancia real y no un cuadrado, la geocerca configurada, posición válida y solo veedores);
- Pest: `MapFiltersTest`, **US-028, los 2 casos del servidor**, más 4 derivados (cada filtro solo y el presupuesto de una ficha agrupada, las fechas en la hora de Colombia, filtros desconocidos, los municipios para elegir);
- Vitest, 7 nuevos:
  - "Nuevo Reporte": US-019 en pantalla, con el orden y las distancias, sin obras cercanas y con el GPS denegado;
  - el mapa: **"Aplicar" deshabilitado sin filtros** (el tercer caso de US-028), los cuatro filtros, ninguna coincidencia, y los municipios con "Limpiar".

Suite: 570 en verde. Vitest: 316 en verde. Cada regla nueva se comprobó rompiéndola a propósito: 12 casos. El que sobrevivía (el radio exacto: sin él, la caja de búsqueda ya excluía a la de 650 m al norte) llevó a un test de esquina: 400 m al norte y 400 m al este son 566 m.

- **Obras cercanas** (`GET /worksites/nearby`, del veedor):
  - calcula Haversine en SQL (D10) dentro de una caja alrededor del veedor, sin PostGIS;
  - aplica las reglas de "Buscar Obra": territorio (R-VC-04) y contrato reportable hoy;
  - devuelve hasta 5 fichas, de la más cercana a la más lejana;
  - en "Nuevo Reporte", "📍 Obras cercanas" pide el GPS y las lista con su distancia, y tocar una la elige como en el buscador.
- **Filtros del mapa** (`GET /public/worksites` con `status`, `from`, `to`, `min_value` y `municipality`, validados): la respuesta sigue liviana (R-MAP-02). `GET /public/worksites/filters` da los municipios de las obras del mapa, con sus nombres legibles ("Ciénaga", no "CIÉNAGA"). El mapa tiene un panel "Filtrar obras", plegado para que el mapa quede primero en el teléfono.

**Decisiones de la iteración, para confirmar:**
1. **El radio de las obras cercanas es el de la geocerca** (500 m, configurable, US-038-CFG): se sugiere hasta donde un reporte sería aceptado.
2. **De cada ficha se sugiere uno de sus contratos reportables.** El reporte va a la ficha completa de todos modos (R-INT-05).
3. **Las obras cercanas se piden con un botón,** no al abrir la pantalla. Así la app no pide el GPS sin que el veedor lo busque, y el buscador sigue siendo lo primero para quien ya sabe la obra.
4. **Cómo se lee cada filtro:**
   - el estado es el color del pin (Normal, Alerta, En riesgo);
   - las fechas son las de las evidencias **publicadas**, en la hora de Colombia;
   - el presupuesto es la suma de los contratos de la ficha, estrictamente mayor;
   - el municipio es el de cualquiera de sus contratos.

### Iteración 32 — Operación de la cuenta patrocinadora y costos
**Entregable:** saldo en XLM de la patrocinadora cada 15 minutos, con alerta bajo el umbral de D12 (50 XLM); aviso antes de que venza la vigencia de la instancia o del código del contrato, para que la tesorería corra la extensión (D12); reporte de comisiones (XLM y COP) por mes y organización, con respaldo del último precio conocido de XLM; re-encolado de fallas de sellado. US-004 y US-022 se ajustan a Stellar al abrir la iteración.
**Done-when:** US-022 (~~4~~ 5 casos: se sumó el aviso de vigencia del contrato), US-004 (4) y US-047-MNT (2) en verde.
**Cubre:** US-004, US-022, US-047-MNT · R-VC-03, R-INT-03.

**✅ Cumplido (2026-09-29, con Opus xhigh):** al abrir la iteración, **US-004 y US-022 se ajustaron a Stellar**: Gherkin, criterios, historias y SPEC. Ahora hablan de XLM, cuenta patrocinadora, tesorería y COP, en vez de POL, Relayer y USD. **US-022 suma un escenario**, el aviso antes de que venza la vigencia del contrato, que pedía el entregable (D12), y pasa de 4 a 5 casos.

Todo en verde, y visto en rojo antes de implementar (clases, rutas y pantalla inexistentes):
- Pest: `SponsorAccountTest`, **US-022, 5/5**, más 19 derivados:
  - el umbral sale del parámetro y se compara al stroop, con decimales;
  - una alerta por cruce;
  - el saldo escrito como en Colombia;
  - solo Email si no hay webhook;
  - el RPC caído no cambia nada;
  - el aviso de vigencia en el límite de 30 días, por la instancia, y de nuevo tras una extensión;
  - el webhook y su caída;
  - el panel, también sin red.
- Pest: `SealingCostsTest`, **US-004, 4/4**, más 7 derivados:
  - se guarda el último precio;
  - sin precio nunca, solo XLM;
  - sellos sin comisión conocida;
  - solo cuentan los sellados;
  - la comisión se anota al confirmar y también al encontrar el sello tras un reenvío.
- Pest: `RequeueFailedSealsTest`, **US-047-MNT, 2/2**, más 8 derivados:
  - 5 intentos nuevos hasta "Sellada";
  - solo las que están en falla;
  - auditoría `seal.requeued`;
  - sin falsa alerta de cola estancada (US-021);
  - validación;
  - la lista de fallas de todas las organizaciones.
- Pest, grupo `stellar` (`make test-stellar`, red local), 3 nuevos:
  - **la comisión anotada es exactamente lo que bajó el saldo de la patrocinadora**, también cuando se encuentra el sello después;
  - el saldo;
  - la vigencia de la instancia y del código (176 días en la red local, tras `make contract-deploy`).
- Vitest, 16 nuevos: la pantalla "Sellado" (10: cuenta, umbral, vigencia, red caída, fallas y re-encolado) y sus comisiones (6). El menú del panel global suma "Sellado".

Suite: 615 en verde. Vitest: 332. `make test-stellar`: 11. Cada regla nueva se comprobó rompiéndola a propósito: 41 casos (33 del servidor y 8 de la pantalla). Uno sobrevivía, contar sellos que no están sellados: caían en una fila sin mes que el test no miraba. Ahora el test compara el reporte completo.

- **Saldo de la patrocinadora** (`CheckSponsorBalance`, cada 15 min): lo lee por el RPC de Stellar y lo compara con `sponsor_balance_alert_threshold_xlm` (D12, 50 XLM), exacto en stroops.
- **Vigencia del contrato** (`CheckContractLifetime`, cada día a las 12:00 UTC, 07:00 en Colombia):
  - lee el TTL de la instancia y, con el hash del WASM que esa instancia ejecuta, el del código;
  - con menos de 30 días en alguno, avisa para que la tesorería corra la extensión (`make testnet-extend` o su equivalente).
- Los dos chequeos no corren si no hay contrato configurado: un entorno sin `make contract-deploy` no falla cada 15 minutos.
- **Alertas críticas por Email y Webhook** (`SuperAdminAlerts`):
  - un correo a cada Super Administrador;
  - un solo POST al webhook (`ALERT_WEBHOOK_URL`) con `{"text", "content"}`, que leen tanto Slack como Discord;
  - un webhook caído se registra y no reintenta: el correo ya salió.
- **Comisión de cada sello** (`report_seals.fee_stroops`): el `feeCharged` del resultado del fee bump, ya con el reembolso de los recursos no usados. Se anota al confirmar el sello y también cuando un reenvío encuentra el sello ya en la red.
- **Reporte de comisiones** (`GET /admin/costs/data`):
  - agrupa por mes, en la hora de Colombia del ledger, y por organización;
  - da la cantidad de sellos, las comisiones en XLM y el costo en COP con el precio de CoinGecko;
  - cada precio obtenido se guarda en `xlm_price_quotes`, y si el API no responde se usa el último, con su fecha (R-INT-03).
- **Re-encolar** (`POST /admin/sealing/requeue`, solo el Super Administrador del panel global):
  - una o varias evidencias en "Falla de Sellado", de cualquier organización;
  - vuelven "En Cola" con 5 intentos nuevos y un `SealReport`;
  - queda un registro de auditoría por evidencia, con el error que tenía.
- **Pantalla "Sellado"** (`/admin/sealing`): cuenta patrocinadora, vigencia del contrato, fallas para elegir y re-encolar, y comisiones del mes.
- De paso: los tests que leían `schedule:list` suponían columnas de un ancho fijo; la tarea de las 12:00 las ensanchó. Ahora aceptan cualquier espacio.

**Decisiones de la iteración, para confirmar:**
1. **US-022 suma el escenario de la vigencia del contrato** (5 casos en vez de 4). El entregable lo pedía y así queda trazado en un `.feature`.
2. **Una alerta por cruce, no una cada 15 minutos.**
   - El saldo vuelve a alertar solo después de subir sobre el umbral y volver a caer.
   - La vigencia avisa una vez por cada fecha de vencimiento; tras una extensión, la siguiente vuelve a avisar.
   - Ese estado vive en la caché: si se borra, a lo sumo se repite una alerta.
3. **Aviso de vigencia a los 30 días**, con los días estimados a ~5 s por ledger. Si la red cerrara ledgers más rápido, quedarían algunos días menos de los que dice el aviso.
4. **El costo en pesos usa el precio de hoy** para todos los meses, no el de cada mes: es una estimación de lo que costaría recargar. CoinGecko gratuito, con una llave "demo" opcional (`COINGECKO_API_KEY`).
5. **Las comisiones de transacciones rechazadas no se suman.** Son raras y no son un sello. Los sellos anteriores a esta iteración no tienen comisión anotada: el reporte los cuenta como "sin comisión conocida".
6. **Re-encolar da 5 intentos nuevos y cuenta como "ya alertada"** para la alerta de cola estancada: se recibió hace horas, pero alguien la está mirando.
7. **Una sola pantalla "Sellado"** reúne US-022, US-047-MNT y US-004, en vez de las pantallas "Costos de gas" y "Relayer" de la SPEC (ya ajustada). Así el menú del panel queda en 5.
8. **Las alertas anteriores siguen solo por Email:** la de patrocinadora sin XLM (US-020b) y la de cola estancada (US-021). Propuesta: mandarlas también al webhook, con el mismo `SuperAdminAlerts`.

### Iteración 33 — Baja de organizaciones e invitaciones
**Entregable:** baja con doble confirmación, mapa fuera de línea y evidencias verificables; retención de 5 años de los archivos; reenviar y revocar invitaciones.
**Done-when:** US-003b (6 casos) y US-040-USR (2) en verde.
**Cubre:** US-003b, US-040-USR · R-AUD-02, R-AUD-03.

**✅ Cumplido (2026-09-29, con Opus xhigh):** US-003b se ajustó a Stellar al abrir la iteración: solo el nombre de la red. De paso se corrigió "Polygon" en un criterio de US-026, que ya se había construido contra Stellar.

Todo en verde, y visto en rojo antes de implementar (rutas y pantallas inexistentes):
- Pest: `DecommissionOrganizationTest`, **US-003b, 6/6**, más 12 derivados:
  - el subdominio mal escrito;
  - la primera confirmación vence a los 10 minutos;
  - el token de otra organización no sirve;
  - es definitivo: ni otra baja, ni suspender, ni reactivar;
  - también se da de baja una suspendida;
  - se revoca la autorización al Super Administrador (US-042-SEC);
  - solo el Super Administrador del panel global;
  - el listado dice "Dada de baja";
  - la descarga funciona hasta la purga y después dice por qué no;
  - se purga una sola vez, con auditoría;
  - nunca se purga una organización activa o suspendida;
  - la purga corre cada día.
- Pest: `ManageInvitationsTest`, **US-040-USR, 2/2**, más 7 derivados:
  - reenviar una invitación ya vencida;
  - revocar libera el correo para invitarlo de nuevo;
  - solo una invitación pendiente se reenvía o revoca;
  - solo el Administrador;
  - un Administrador no está entre los veedores.
- Vitest, 9 nuevos:
  - la baja con sus dos confirmaciones, cancelar la segunda, el rechazo del servidor y nada más que hacer en una dada de baja;
  - reenviar y revocar invitaciones, también vencidas;
  - la página del mapa fuera de línea.

Suite: 642 en verde. Vitest: 341. Cada regla nueva se comprobó rompiéndola a propósito: 34 casos (26 del servidor y 8 de la pantalla). Todos quedaron atrapados menos uno equivalente: quitar el filtro por estado "dada de baja" de la purga no cambia nada, porque solo una organización dada de baja tiene fecha de baja. El filtro se queda porque se lee mejor.

- **Baja con doble confirmación** (panel global):
  - `POST /admin/organizations/{id}/decommission/start`, la primera, dice lo que implica: evidencias selladas y hasta cuándo se conservan los archivos. Da un token que vale 10 minutos.
  - `POST /admin/organizations/{id}/decommission`, la segunda, pide ese token y el subdominio escrito.
  - La baja es lógica. Nada se borra y nada cambia en Stellar. Se desactivan sus usuarios, se anulan las invitaciones pendientes y la autorización al Super Administrador, y nadie vuelve a entrar: el inicio de sesión y cada petición lo rechazan con "La organización veedora fue dada de baja. Sus usuarios ya no tienen acceso.". Queda en el log de auditoría.
- **El mapa sale de línea, las evidencias siguen verificables:**
  - el mapa, la vista de cada obra y sus datos responden "fuera de línea": la pantalla `Public/Offline`, que lleva al validador, y 410 en los datos;
  - el validador, las pruebas de inclusión, los recibos y las descargas siguen.
- **Retención** (`PurgeDecommissionedEvidence`, cada día a las 06:00 UTC):
  - 5 años después de la baja borra los archivos de evidencia, una sola vez y con auditoría;
  - los hashes, las pruebas de inclusión y los sellos se conservan, así que una copia guardada sigue saliendo "Auténtico";
  - una descarga ya purgada responde 410 y dice por qué.
- **Reenviar o revocar una invitación** (`POST /observers/{id}/invitation/resend` y `/revoke`, del Administrador):
  - reenviar da un enlace nuevo con la vigencia configurada, y el anterior deja de valer;
  - revocar hace que el enlace se comporte como uno vencido;
  - las dos quedan en el log de auditoría.
  - `InvitationLink` es ahora el único lugar que emite un enlace: lo usan invitar y reenviar.
- **La pantalla de fijar contraseña** responde igual a una cuenta que ya no existe (una invitación revocada): "El enlace de invitación ha expirado o no es válido…", también al enviar el formulario. Antes daba 404.
- **Etiquetas del log de auditoría** para las acciones nuevas y para `seal.requeued` de la it. 32, que había quedado sin etiqueta.

**Decisiones de la iteración, para confirmar:**
1. **La doble confirmación la hace cumplir el servidor,** no solo la pantalla.
   - La primera da un token de 10 minutos.
   - La segunda exige ese token y el subdominio escrito.
   - Cancelar la segunda no llega al servidor: el token vence solo.
2. **La baja desactiva a todos sus usuarios y anula sus invitaciones,** además de bloquear el acceso por el estado de la organización. Es definitiva: no hay "reactivar una dada de baja".
3. **Qué sale de línea y qué no.**
   - Sale el mapa, la vista de cada obra, sus datos y las fotos del mapa.
   - Siguen el validador (`/verify`), las pruebas de inclusión, los recibos y las descargas de archivos mientras existan.
   - El subdominio sigue respondiendo, para que las evidencias sigan verificables.
4. **La retención cuenta 5 años exactos desde la baja,** y la revisión es diaria. Solo se borran los archivos de evidencia: el logo y la base de la organización se conservan.
5. **Revocar una invitación borra la cuenta invitada:** nunca tuvo contraseña ni reportes. Así el correo se puede invitar de nuevo. El log de auditoría conserva quién, cuándo y a qué correo.
6. **Se puede reenviar una invitación ya vencida,** no solo una pendiente: es el caso de "no atendida".

### Iteración 34 — Reportes y datos abiertos
**Entregable:** exportación CSV de la organización; estadísticas públicas; datos abiertos en CSV y JSON; resumen de uso; alerta de inactividad.
**Done-when:** US-050-RPT (3 casos), US-051-RPT (2), US-052-RPT (4), US-053-RPT (2) y US-054-RPT (4) en verde.
**Cubre:** US-050..054-RPT · R-PRIV-02, R-PRIV-03 (en los datos abiertos).

**✅ Cumplido (2026-09-29, con Opus xhigh):** al abrir la iteración, US-052-RPT pasó de "bloque" a "ledger" (Stellar). De paso, la tabla de historias de la SPEC dejó de nombrar Polygonscan en US-023 y US-025.

Todo en verde, y visto en rojo antes de implementar (rutas y pantallas inexistentes):
- Pest: `ExportCsvTest`, **US-050-RPT, 3/3**, más 3 derivados: UTF-8 con su marca para la hoja de cálculo, solo el encabezado si no hay nada, y solo el Administrador.
- Pest: `PublicStatsTest`, **US-051-RPT, 2/2**, más 5 derivados:
  - no cuentan las rechazadas ni las retiradas;
  - el mes es el de Colombia;
  - también está en riesgo una obra con el contrato vencido, como su pin;
  - un territorio sin evidencias;
  - las estadísticas salen de línea con el mapa tras una baja.
- Pest: `OpenDataTest`, **US-052-RPT, 4/4**, más 3 derivados:
  - descarga como archivo;
  - sigue disponible tras una baja;
  - ningún otro formato.
- Pest: `UsageSummaryTest`, **US-053-RPT, 2/2**, más 3 derivados:
  - no cuentan como activos un veedor desactivado, una invitación pendiente ni un Administrador;
  - aparecen todas las organizaciones, con su estado;
  - la pantalla.
- Pest: `InactivityAlertTest`, **US-054-RPT, 4/4**, más 6 derivados:
  - un minuto antes de los 30 días no alerta;
  - una alerta por período de inactividad;
  - una organización nueva cuenta desde su registro;
  - no alerta por una suspendida ni por una dada de baja;
  - la revisión corre cada día.
- Vitest, 9 nuevos:
  - las estadísticas públicas, con los datos abiertos;
  - el resumen de uso;
  - "Exportar" en el resumen del Administrador;
  - "Estadísticas del territorio" en el mapa;
  - "Uso" en el menú del panel global.

Suite: 677 en verde. Vitest: 350. Cada regla nueva se comprobó rompiéndola a propósito: 31 casos (23 del servidor y 8 de la pantalla). Uno sobrevivía, y era un error del test: "a los 29 días no alerta" en realidad miraba 28 días y 23 horas, porque agosto tiene 31. Ahora mira un minuto antes de los 30 días, y atrapa un umbral de 29.

- **Exportación CSV** (`GET /export.csv`, del Administrador):
  - una fila por archivo de evidencia, de cualquier estado editorial;
  - columnas: obra, contrato, municipio, fecha (hora de Colombia), clasificación, estado editorial, hash, comentario y seudónimo del veedor (R-PRIV-03).
- **Estadísticas públicas** (`/stats` y `GET /public/stats`, sin sesión):
  - obras en riesgo: los pines rojos del mapa;
  - evidencias publicadas por mes de captura;
  - contratos anulados cuya obra tiene evidencias publicadas.
  - Se llega desde el mapa, y salen de línea con él (US-003b).
- **Datos abiertos** (`GET /open-data.csv` y `/open-data.json`, sin sesión): un registro por evidencia publicada, con su sello en Stellar, las coordenadas aproximadas (R-PRIV-02) y el seudónimo del veedor. El JSON dice además la organización, la hora y la red.
- **Resumen de uso** (`/admin/usage`, Super Administrador): por organización, veedores activos, evidencias recibidas, publicadas, rechazadas y retiradas, y su última actividad.
- **Alerta de inactividad** (`CheckOrganizationActivity`, cada día a las 13:00 UTC, 08:00 en Colombia): la organización activa que lleva 30 días sin recibir ni publicar evidencias le llega al Super Administrador por Email.

**Decisiones de la iteración, para confirmar:**
1. **La exportación va en una fila por archivo de evidencia**, porque pide "el hash de la evidencia". Incluye todos los estados editoriales: el Administrador los ve todos. Va con la marca UTF-8, para que Excel lea bien las tildes.
2. **"Obras en riesgo" en las estadísticas son los pines rojos del mapa:** última evidencia de abandono o contrato vencido. No es la marca nocturna de US-034, que solo mira el vencimiento. Así la estadística coincide con lo que el visitante ve.
3. **Datos abiertos: un registro por evidencia publicada** (un reporte), con dos campos que la SPEC no pedía:
   - `reporte`, que lleva a su recibo público;
   - `contrato_de_sellado`, que con `raiz_merkle` basta para leer el sello en Stellar sin GovTrace.
   No incluye el JSON sellado, que tiene la ubicación exacta: es la inconsistencia R-PRIV-03 vs R-PRIV-02 pendiente de la it. 24.
4. **Tras una baja, los datos abiertos siguen y las estadísticas salen de línea con el mapa.**
5. **La actividad** es recibir (según la base de la organización) o publicar (según el log de auditoría). Así, una publicación que después se retira también cuenta. Sin actividad nunca, se cuenta desde el registro.
6. **Una alerta de inactividad por período,** y solo por Email, como dicen los criterios; no va al webhook de la it. 32. Solo organizaciones activas: una suspendida o dada de baja no se espera que trabaje.

### Iteración 35 — Operación y respaldo (bloquea la salida a producción)
**Entregable:**
- respaldos programados de PostgreSQL y MinIO, **cada hora**, con retención de 30 días;
- purga de la tabla de seudónimos a los 5 años;
- Jenkinsfile con `cargo test` del contrato y la prueba de humo en la testnet de Stellar antes de cada salida;
- ejecución y documentación de **una restauración de prueba**.

**Done-when:**
- existe el runbook `docs/restore.md`, con una restauración real ejecutada que registra **menos de 1 h de datos perdidos** y **menos de 4 h de recuperación**;
- el pipeline de Jenkins corre sus etapas en verde;
- test de la purga de seudónimos.

**Cubre:** R-BCK-01, R-BCK-02, R-BCK-03, R-BCK-04, R-BCK-05, R-MNT-03, R-TST-01, R-TST-02, R-CFG-01 (reglas sin Gherkin, O6).

**✅ Cumplido (2026-09-29, con Opus xhigh):**
- **Respaldos** (servicio `backup`, en el stack base):
  - una copia al arrancar y otra cada hora en punto (R-BCK-01), de cada base de PostgreSQL (la central y la de cada organización, en el formato de `pg_restore`) y del bucket de evidencias (R-BCK-03);
  - los archivos que no cambiaron se enlazan con la copia anterior, así 24 copias al día no ocupan 24 veces el bucket;
  - cada copia trae su manifiesto y el SHA-256 de cada volcado, y se borra a los 30 días (R-BCK-04);
  - una copia a medias nunca queda con el nombre de una completa;
  - el servicio está sano mientras su última copia tenga menos de 2 horas.
  - Comandos: `make backup-now` y `make backup-list`.
- **Restauración de prueba** (`make restore-drill`, R-BCK-05):
  - restaura la copia en un PostgreSQL vacío y descartable (`restore-pg`) y sus archivos en un bucket de prueba;
  - verifica que cada organización tiene su base y que cada evidencia tiene su archivo con el mismo SHA-256;
  - dice cuántos datos se habrían perdido y cuánto tardó.
  - `make backup-check` prueba además que falla ante un archivo alterado o una base que falta, la retención y los enlaces. Corre en una etapa nueva del pipeline, "Backup & Restore".
- **Runbook** `docs/restore.md`: qué se respalda, el procedimiento de restauración real paso a paso (probado sobre un servidor que ya tenía las bases), qué se pierde y qué no, y la **restauración real ejecutada**: la copia que el servicio tomó al arrancar, restaurada 14 minutos después, con **14 min 27 s de datos perdidos** (límite 1 h) y **3 s de recuperación** (límite 4 h), con los datos de desarrollo.
- **Purga de seudónimos** (`PurgeVeedorPseudonyms`, cada día a las 06:30 UTC, R-MNT-03):
  - borra la fila seudónimo→veedor de quien lleva 5 años sin reportar, en toda organización, con auditoría;
  - las exportaciones (US-050-RPT, US-052-RPT) ahora calculan el seudónimo sin guardarlo (`VeedorPseudonym::compute`), así nunca devuelven un vínculo ya purgado.
  - Pest `PseudonymRetentionTest`: 6 en verde, vistos en rojo antes de implementar.
- **El pipeline, en verde etapa por etapa.** Aquí no hay Jenkins, así que se corrieron los mismos comandos de cada etapa del `Jenkinsfile`, en orden:
  - en el entorno de desarrollo: Format, Backend (683), Frontend (350), E2E, Contract (6 de `cargo test`), Stellar (11 y el verificador independiente), Backup & Restore, Secrets, Monitoring y **Smoke Testnet**, un reporte hasta "Sellada" en la testnet real;
  - en un proyecto aislado, con el entorno que pone Jenkins (su propio proyecto, puertos, red y volúmenes), desde un `make setup` de cero: Build, los 7 servicios sanos, Format, Backend (683), Frontend (350), E2E, Contract, Stellar, Backup & Restore y Secrets, todas en verde. Después se desmontó solo ese proyecto.
  - La prueba de humo en testnet (R-TST-01) y el `cargo test` del contrato ya estaban en el `Jenkinsfile` desde las it. 12 y 14.
- **Arreglado de paso**, gracias a correr el pipeline en un proyecto de cero:
  - **`make setup` fallaba en un entorno nuevo, como el de Jenkins.** Esperaba a que todo estuviera sano antes de migrar, y en una base vacía el worker no arranca: la cola lee la tabla de la caché. Ahora levanta la app, migra y después espera al resto;
  - la E2E de Jenkins habría probado el puerto 8080 y no el suyo (18080): `run-e2e.sh` y `verify-stack.sh` ahora respetan el puerto del entorno, como `docker compose`;
  - la restauración de prueba ya no da por buena una verificación cuya consulta falla.

Cada regla nueva se comprobó rompiéndola a propósito: 8 casos de la purga de seudónimos, todos atrapados. Los respaldos se prueban solos: `make backup-check` altera una copia y quita una base, y la restauración de prueba tiene que fallar.

**Decisiones de la iteración, para confirmar:**
1. **El servicio de respaldo vive en el mismo stack** y guarda en un volumen. **En producción ese volumen tiene que estar en otro disco o en otra máquina,** y el bucket de evidencias fuera del servidor: está en el runbook, pero no se automatizó una copia fuera del sitio (por ejemplo, a otro bucket u otra región). Propuesta: decidirlo con la infraestructura de producción.
2. **Una copia cada hora en punto, más una al arrancar.** El peor caso de datos perdidos es justo 1 h (R-BCK-01).
3. **Volcados por base, no `pg_dumpall`:** se restaura una organización sola, y no dependen de roles de superusuario, que un PostgreSQL administrado no da.
4. **La restauración de prueba corre en cada ejecución del pipeline,** no solo antes de una salida: es rápida (~30 s) y así el procedimiento no se echa a perder sin que nadie lo note. R-BCK-05 pedía al menos una antes de producción.
5. **Los 5 años de un seudónimo cuentan desde el último reporte del veedor,** no desde que se creó el seudónimo: mientras reporta, su vínculo sigue.
6. **Hay que repetir la restauración de prueba en producción antes de salir** (R-BCK-05): los 3 s son con datos de desarrollo.
7. **Repetir `make setup` sobre un stack que ya está corriendo** puede fallar en `up --wait`: el proxy se marca enfermo mientras la app se reinicia. Pasa desde antes de esta iteración y no afecta a Jenkins, que parte de cero. Para un stack que ya existe, `make up` alcanza. Si molesta, se resuelve dándole más paciencia al healthcheck del proxy.

---

## Fase de cierre — Tras la auditoría (2026-09-29)

Salen de `/audit` (`specs/AUDIT.md`). Ninguna bloquea el PR; las dos bloquean la salida a producción.

### Iteración 36 — Trazabilidad y log de auditoría completos
**Entregable:**
- el log de auditoría registra también el alta de una organización, la asignación de su Administrador inicial y la invitación de un veedor (R-AUD-04), con sus etiquetas;
- el log es inmutable: una entrada no se edita ni se borra, con la misma guarda que reportes y sellos (R-MNT-03);
- el test negativo de un veedor que intenta invitar (R-VC-01);
- `RegisterOrganization` es atómico: si falla, no queda una organización a medio crear;
- los 66 tests de las it. 2 a 13 y 20 llevan el nombre de su escenario (convención de CLAUDE.md);
- la página del dominio central deja de mostrar texto provisional, y se limpian los comentarios desactualizados.

**Done-when:**
- tests de cada punto, vistos en rojo antes de implementar;
- el rastreo de escenarios encuentra los 249 nombres en sus tests;
- cada regla nueva, comprobada rompiéndola a propósito.

**Cubre:** R-AUD-04, R-MNT-03, R-VC-01, R-TST-04 · US-001, US-002, US-005.

**✅ Cumplido (2026-09-29, con Opus xhigh):** en verde y visto en rojo antes de implementar, salvo dos tests negativos que ya pasaban (abajo).
- **Log de auditoría completo (R-AUD-04).** Registran quién, cuándo y qué:
  - el alta de una organización (`organization.registered`: NIT, nombre y subdominio);
  - la asignación de su Administrador inicial (`organization.administrator_assigned`);
  - la invitación de un veedor (`observer.invited`, con la vigencia del enlace).
  - Un alta o una asignación rechazadas no se registran.
- **Log inmutable (R-MNT-03):** `AuditLog` no se edita ni se borra (`AuditLogIsAppendOnly`), con la misma guarda que reportes y sellos.
- **R-VC-01:** el test de que un veedor no puede invitar (403, nadie invitado, ningún correo). Ya pasaba: la ruta estaba protegida por rol y faltaba la prueba. La comprobación rompiéndolo a propósito confirma que atrapa sacar la ruta del grupo del Administrador.
- **Alta atómica:** si falla en el camino — antes de crear su base, al prepararla o al crear su dominio —, no queda la organización, ni su base, ni su dominio, y el mismo NIT y el mismo subdominio se registran después.
- **Trazabilidad:** 69 tests de las it. 2 a 13 y 20 (algunos escenarios tienen varios) llevan ahora el nombre de su escenario, y las dos secciones de `check-monitoring.sh` el de los de US-044-MON. **`make trace-check`**, nuevo y en la etapa Format Check del pipeline, comprueba que los 249 escenarios tienen un test con su nombre, y falla si uno lo pierde.
- **Página del dominio central:** dice qué es GovTrace, que cada organización publica su mapa en su subdominio (R-MAP-01) y lleva al panel global; ya no dice "GovTrace está listo para construirse.". Comentarios desactualizados, corregidos.
- **Decisiones registradas:** R-PRIV-03 enmendada en la SPEC, y D13, la red principal: RPC de un proveedor con dos endpoints y el presupuesto de la tesorería.

Suite: 694 en verde (11 nuevos). Vitest: 351 (1 nuevo). `make trace-check`: 249 de 249. Cada regla nueva se comprobó rompiéndola a propósito: 9 casos, todos atrapados. Además, el rastreo falla si a un test le falta el nombre de su escenario.

### Iteración 37 — Salida a la red principal (bloquea la salida a producción)

**Dividida el 2026-09-29, a pedido del usuario:** la información de producción no está todavía, y en estas semanas se configura testnet. La 37a hace ahora lo que no la necesita, probado contra testnet y la red local; la 37b espera a producción.

#### Iteración 37a — Preparar la salida, sin datos de producción
**Entregable:**
- **despliegue con la red como parámetro (R-CFG-01, D12, D13):** en testnet o en la red principal, el mismo camino:
  - la tesorería, ya fondeada, crea y fondea la selladora y la patrocinadora (en la red principal no hay friendbot);
  - despliega el contrato y extiende su vigencia;
  - su llave entra solo por el entorno, y las de la selladora y la patrocinadora ni entran: basta con sus direcciones (D11);
  - la extensión de la vigencia, igual, con la red como parámetro;
- **`.env.production.example`**, sin secretos, con los dos endpoints de D13;
- **la aplicación no arranca en la red principal** si falta `STELLAR_PUBLIC_RPC_URL` o si es el mismo RPC privado del servidor (D13);
- **respaldos fuera del sitio:** cada copia se replica a otro bucket, se borra allá a los 30 días, se puede restaurar desde allá, y el servicio deja de estar sano si la réplica se atrasa;
- **la restauración de prueba falla si la recuperación pasa de 4 h** (R-BCK-02);
- **`docs/go-live.md`**, con la lista de salida.

**Done-when:**
- el despliegue probado de punta a punta en testnet: un contrato nuevo, con cuentas creadas por la tesorería y un reporte hasta "Sellada" sobre él;
- tests de la verificación de arranque, vistos en rojo antes de implementar;
- `make backup-check` en verde, con la réplica fuera del sitio y el límite de 4 h;
- cada regla nueva, comprobada rompiéndola a propósito.

**Cubre:** R-CFG-01 (preparación), R-BCK-02 · D11 (a), D12, D13.

**✅ Cumplido (2026-09-29, con Opus xhigh):** cada parte, vista fallar antes de implementarla.
- **Despliegue con la red como parámetro** (`scripts/deploy-network.sh`, `make network-deploy NETWORK=testnet|mainnet`):
  - la tesorería, con su llave solo por el entorno (`STELLAR_ACCOUNT`, nunca como argumento ni en un archivo), crea la selladora (1,5 XLM) y la patrocinadora (`SPONSOR_STARTING_XLM`, 100 por defecto) si no existen;
  - despliega el contrato con la selladora y extiende su vigencia (D12);
  - de la selladora y la patrocinadora solo entran sus direcciones: sus llaves viven en el gestor de secretos (D11);
  - escribe solo valores públicos, en `.env.<red>.deploy`, fuera de git.
  - En la red principal se niega sin `CONFIRM_MAINNET=yes`, porque gasta XLM reales. Usa `STELLAR_MAINNET_RPC_URL`, no el RPC del contenedor, que es el de la red local. Antes de firmar nada comprueba con `getNetwork` que ese RPC sirve de verdad la red principal.
  - `extend-contract.sh` acepta la red principal y la llave de la tesorería por el entorno (`make network-extend`).
- **Probado de punta a punta en testnet** (`make network-deploy-check`, con cuentas y contrato desechables): la tesorería crea las cuentas, despliega y extiende; vuelve a extender con su llave por el entorno; no queda ninguna llave escrita ni en la salida; un reporte llega a "Sellada" sobre el contrato nuevo; y la red principal no se toca sin confirmarlo ni con un RPC de otra red.
  - La primera corrida destapó que `stellar ledger entry fetch` responde bien para una cuenta que no existe (con `entries` vacío): el script creía que ya existía. Ahora mira las entradas.
- **La aplicación no arranca en la red principal** si falta `STELLAR_PUBLIC_RPC_URL` o si es el mismo endpoint que `STELLAR_RPC_URL`, aunque esté escrito distinto: otra barra final, otras mayúsculas en el dominio (`MainnetConfiguration`, D13). Pest: 8 en verde.
- **`.env.production.example`**, sin secretos, con los dos endpoints de D13; `make secrets-check` la revisa, y ahora también la llave de la tesorería.
- **Respaldos fuera del sitio** (`BACKUP_OFFSITE_S3_URL`):
  - cada copia se replica al terminar: los volcados, por copia; los archivos, a un espejo;
  - allá se borran a los 30 días, por su fecha;
  - `fetch-offsite.sh` trae una copia para restaurarla;
  - el servicio deja de estar sano si la última réplica tiene más de 2 h.
  - `make backup-check` lo prueba con un segundo bucket de LocalStack: replica, borra la vieja y restaura desde allá.
- **La restauración de prueba falla si la recuperación pasa de 4 h** (`RESTORE_RTO_SECONDS`, R-BCK-02).
- **`docs/go-live.md`:** la lista de salida, con lo listo y lo que espera a la 37b.

Suite: 702 en verde (8 nuevos). Vitest: 351. `make backup-check`: 13 comprobaciones en verde. `make network-deploy-check` en testnet: 9 de 9. Cada regla nueva se comprobó rompiéndola a propósito: 12 casos (6 de la verificación de arranque, 4 de los respaldos y 2 de las salvaguardas del despliegue), todos atrapados.

**Decisiones de la iteración — ✅ aprobadas por el usuario el 2026-09-29:** el espejo con versionado y Lifecycle Rules de S3; los saldos de 1,5 y 100 XLM; y el `--rpc` propio del verificador independiente, que garantiza la descentralización (R-INT-04). Quedan en la SPEC (R-BCK-04, R-BLK-04, R-INT-04).
1. **La réplica fuera del sitio guarda los volcados por copia y los archivos en un espejo.** El espejo no guarda 30 días un archivo borrado; para eso, en producción, el bucket de la réplica necesita versionado y una regla de 30 días. Queda en la lista de salida.
2. **La selladora se crea con 1,5 XLM** (la reserva mínima y un margen: no paga comisiones), y **la patrocinadora con 100 XLM por defecto**, que se ajustan con `SPONSOR_STARTING_XLM`. Esos XLM salen de la tesorería, aparte de los 28 del contrato.
3. **El verificador independiente no usa el endpoint del navegador**, restringido al dominio: quien verifica desde su equipo indica su RPC con `--rpc`. En `tools/verify/contracts.json`, el `rpc` de la red principal queda en `null`.

**Prueba en testnet a pedido del usuario (2026-09-29).** Con el RPC de SDF en las dos variables, porque el bloqueo CORS del endpoint público se aplicará en la infraestructura de la red principal:
1. Una tesorería nueva, fondeada con friendbot (`GCQVAD3ZZ6RNO4MQWN2BGEFLWTMHGHJZUE57TGU54ZXSNBKEGZDKQDAD`), y `make network-deploy NETWORK=testnet`:
   - la tesorería creó la selladora `GCUCPDHRG3AVSYBBEHNB3A5QQ4NEFHAKLTGR74FQZJQQCEOMKPXD3NJM` con 1,5 XLM (tx `779145072394cc337df60ea14428716bc9cd76bd9d7637b98370a3d13614a3d4`);
   - creó la patrocinadora `GCOQRTRH6KYDSAGEGF4R7O4OUM5F5NZJD5ESVFVUALCZ7TOXSXR6S6KU` con 100 XLM (tx `ee17bf773f6c2945b5ce4808b38a48b2112d560a8e9d9c7ce060e8a1392121c6`);
   - desplegó el contrato **`CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY`** (tx `64e5c481160139c3801365c57078e7664121531a69e5f7de4516463a570574f5`);
   - extendió la vigencia de la instancia (tx `83770597c2496f2321a1599a4f324b9cab9f7cfafb36167323263478723589e4`) y del código (tx `b6fcf0826245257e2b318501c406018dc1d8f841ac5432a6c5f2def74358130b`).
2. **Dos reportes hasta "Sellada" por el endpoint privado**, con la aplicación: tx `b8ebde007360e24e73842576181c1002d9bc180aa8e9e2c278928959f5ffd03f` (ledger 4934763) y `caf4acdd4105cd25d47b0672f3605a8178e6d1a43dbbf577c603a93f333e70e8` (ledger 4934764).
   - Cada sello costó **0,269874 XLM**, un 11 % más que los 0,2425 medidos el 2026-09-28 en testnet. Con esa comisión, el umbral de 50 XLM son unos 185 sellos.
   - La selladora siguió con 1,5 XLM y la patrocinadora quedó en 99,460252 XLM: pagó solo los dos sellos (D5).
3. **Los dos sellos leídos como el validador del navegador**, con su misma biblioteca (`tools/verify/lib`) y el endpoint público: raíces `6c60b323…52551b` y `e6e53eda…525066`, en los ledgers 4934763 y 4934764.
4. **La separación de los endpoints, probada en el código** (`StellarEndpointsTest`, 3 casos), porque con la misma URL en las dos variables la red no puede mostrarla: el servidor consulta solo el privado, y el validador y la vista de una obra reciben solo el público. El privado no aparece en ninguna página.
- La prueba de humo de testnet ahora anota también el contrato y la raíz de cada sello, para auditarlos.
- El script decía "creada con 1 XLM" para la selladora (dividía en enteros); ahora dice 1,5.

**Testnet oficial (2026-09-29, a pedido del usuario):** el contrato `CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY` es el entorno oficial de pruebas previo a la red principal.
- `.env.testnet` (fuera de git) apunta a él y a su selladora y patrocinadora; el anterior, `CABXHM74…`, se guardó fuera del repositorio.
- `tools/verify/contracts.json` lo lista junto al anterior, cuyos sellos siguen valiendo (R-MNT-01).
- `make smoke-testnet` pasó con él: tx `4a4729362dc8304985dc32554f38e081903641581f064492f7bc2e32adb2b2bc` y `4bd1cd8113f211deb9a7a39ee1ee614949a513e3227ef7d9a134fff741d44483`, a 0,270728 XLM cada sello.
- **Queda para el usuario, que tiene acceso a Jenkins: actualizar sus tres credenciales de testnet.** No basta con el ID: el contrato nuevo solo acepta sellos de su propia selladora. Van `stellar-testnet-contract-id` (el ID de arriba), `stellar-testnet-sealer-secret` y `stellar-testnet-sponsor-secret`; las dos llaves salen de las identidades `govtrace-37a-sealer` y `govtrace-37a-sponsor`.
- **El umbral de alerta sigue en 50 XLM** (el usuario, 2026-09-29): con sellos de ~0,27 XLM son unos 185, días o semanas para fondear la hot wallet.

#### Iteración 37b — La salida, con producción
**Entregable:**
- la cuenta del proveedor de RPC, con sus dos endpoints (D13);
- **la firma remota (D11 opción b) con AWS KMS**, elegido por el usuario el 2026-09-29: la llave de la selladora no sale de KMS, detrás de la misma interfaz `SealingNetwork`.
  - Verificado en el modelo del API de KMS que trae la CLI de AWS: la llave es `KeySpec=ECC_NIST_EDWARDS25519` (no "ECC_ED25519"), con `KeyUsage=SIGN_VERIFY`.
  - Se firma con **`ED25519_SHA_512` y `MessageType=RAW`** sobre los 32 bytes del hash de la transacción, que es el Ed25519 puro que verifica Stellar. `ED25519_PH_SHA_512` es Ed25519ph y no sirve para Stellar.
  - Primer paso, la prueba de concepto: crear la llave, derivar su dirección G… de la llave pública, firmar una transacción de testnet y que la red la acepte. Necesita acceso a la cuenta de AWS;
  - no se puede emular en local (2026-09-29): LocalStack 3.8, el gratuito, no crea llaves Ed25519, y la versión actual exige licencia. La recomendación de cuenta, arquitectura y costos para la prueba en la nube está en `docs/estado-mvp.md`;
- el despliegue en la red principal con la tesorería fondeada (D13) y el contrato registrado en `tools/verify/contracts.json`;
- la lista de `docs/go-live.md` completa: credenciales de Jenkins, DIVIPOLA sembrada, respaldos fuera del sitio en su bucket real.

**Done-when:**
- un reporte llega a "Sellada" en la red principal;
- la restauración de prueba en producción, anotada en `docs/restore.md`, con menos de 1 h de datos perdidos y menos de 4 h de recuperación (R-BCK-05);
- `docs/go-live.md` con toda la lista marcada.

**Cubre:** R-CFG-01, R-BCK-05 · D11 (b), D13.

#### Iteración 38 — Demostración local en un comando
✅ **Cumplido (2026-09-29).** Pedida por el usuario: poder recorrer y mostrar en vivo toda la interfaz desde su equipo, con un solo comando. Cuatro tareas, que salieron de intentar recorrer el flujo a mano:
- **`make admin EMAIL=…`:** el primer Super Administrador. En una base nueva no había ninguno y ninguna pantalla lo crea. Pregunta la contraseña sin eco, o genera una y la muestra una vez; nunca va en la línea de comandos. Usa la misma regla de contraseña que los demás, y en la auditoría queda quién se creó (`super_admin.created`, actor `system`), sin contraseña. También sirve para producción (`docs/go-live.md`).
- **La DIVIPOLA dentro de `make setup`**, y `verify-stack.sh` comprueba que quedó sembrada. Cierra la deuda aceptada del mismo nombre.
- **`make invites`:** los enlaces de los correos de desarrollo. El correo sale a `storage/logs/mail.log`, un archivo propio (antes, al log de la aplicación, que llegó a pesar 345 MB); el comando lee solo su final y muestra a quién le llegó cada uno, el asunto y el enlace.
  - **Un error de fondo que salió al probarlo:** los tres enlaces de correo (bienvenida, invitación y recuperación de contraseña) eran siempre `http://<dominio>/…`, sin puerto ni `https`. En local el enlace no abría, y en producción habría sido `http`. Ahora salen de `APP_URL` (`TenantUrl`). `phpunit.xml` fija `APP_URL` para que los tests no dependan del `.env` de quien los corre.
- **`make demo`:** un solo comando desde un equipo con solo Docker y `make`. Levanta la aplicación (`make setup` la primera vez), la red Stellar local y el contrato si no están, y deja la organización `veeduria-demo`:
  - el Administrador y dos veedores con contraseña conocida, y un Super Administrador de demostración;
  - 7 contratos de Magdalena y 6 obras (una sin ubicación, una que agrupa dos contratos);
  - 9 reportes con foto que pasan por `CreateReport` como los de un veedor y se sellan de verdad en la red local; 6 publicados y 3 en la bandeja, para publicarlos en vivo.
  - Las fotos son imágenes de demostración rotuladas como tales, cada una con un comentario JPEG propio para que cada reporte selle bytes distintos (no es EXIF: R-PRIV-06 sigue valiendo).
  - Repetible: cada corrida deja la demostración como nueva, y solo borra la organización del subdominio `veeduria-demo`. **Nunca corre en producción.**
- `docs/local-environment-setup.md`: la demostración, un guion, el paso a paso a mano y cómo mostrarlo fuera del equipo.

**Hallazgo abierto: los sellos en ráfaga.** Al enviar los 9 reportes de golpe, la red aceptó uno y rechazó los demás con `txINSUFFICIENT_FEE` (`La red rechazó el sello (ERROR): AAAAAACDttH////3AAAAAA==`); con los reintentos a 1, 5 y 15 minutos, se sellaba uno por reintento. Por eso `make demo` envía cada reporte cuando el anterior ya está sellado. En un uso real (un veedor, un reporte) no se nota, pero dos veedores enviando a la vez sí esperarían minutos. No se investigó la causa (la comisión del *fee bump* frente al precio de la red con varias transacciones en el mismo ledger); ver la deuda. → **Resuelto en la it. 39:** no era la comisión, sino el número de secuencia de la selladora, y además dejaba evidencias en "Falla de Sellado".

**Prueba:**
- Suite: 741 en verde (39 nuevos: 12 de la demostración, 11 del Super Administrador, 7 de los enlaces de los correos y 6 de `SentLinks` y su comando; los enlaces también ajustaron 3 casos). Vitest sin cambios.
- `make demo` de cero en un proyecto aislado de Docker: sin errores; los 9 sellados en la red local, 6 publicados y 3 ocultos; el mapa público con 5 obras, las estadísticas y `/verify` responden 200; con la app en marcha, 101 s.
- `make invites` mostró el enlace de una invitación real y ese enlace abrió (200).
- Cada regla nueva se comprobó rompiéndola a propósito: 11 casos (contraseña sin regla, contraseña en la auditoría, enlace sin puerto o siempre `http`, correos en el orden equivocado, leer todo el archivo, la demostración borrando otras organizaciones, sin esperar cada sello, corriendo en producción, fotos iguales, publicándolo todo). Uno sobrevivió —leer todo el archivo en vez de su final— y se reforzó su test con un correo anterior al ruido.

**Cubre:** R-SA-01 (el primer Super Administrador), R-AUD-04 (su creación auditada), R-CFG-01 (la lista de salida) · sin historias nuevas: es herramienta de desarrollo.

**Decisión pendiente del usuario: mostrarlo a otras personas.** `make demo` funciona en el equipo de quien lo corre. Para que otras personas entren hay dos caminos, ninguno probado:
- **Un túnel** (ngrok, Cloudflare Tunnel): cada organización vive en su subdominio, así que tiene que aceptar subdominios comodín, y hay que cambiar `APP_URL`, `TENANCY_CENTRAL_DOMAINS` y `TENANCY_APEX_DOMAIN`. Con HTTPS, la cámara y el GPS del celular funcionan.
- **Un entorno intermedio (*staging*) en AWS, apuntando a la testnet:** el recorrido completo con dominio, HTTPS y correo reales, por cuenta de cada quien (`docs/estado-37b.md`).

#### Iteración 39 — Sellar varias evidencias a la vez
✅ **Cumplido (2026-09-29).** Pedida por el usuario con prioridad ("se podría presentar en producción"), a partir del hallazgo de la it. 38. Complejidad alta (fallas de un sistema distribuido y dinero real): con Opus 5.5 max.

**La causa, probada contra la red local** (sondas en la red standalone, sin la aplicación):
- **Stellar admite una sola transacción pendiente por cuenta.** Mientras la última de la selladora no entra en un ledger, la red rechaza otra:
  - con el **mismo número de secuencia** (el que la aplicación leía, porque la cuenta aún no avanzaba): `txINSUFFICIENT_FEE`, pidiendo 8.632.017 stroops, unas 10 veces la comisión. La red lo lee como un intento de *reemplazar* la pendiente, que exige pagar 10 veces más;
  - con el **número siguiente**: `TRY_AGAIN_LATER`;
  - con un **número ya gastado** (un RPC que no vio el último ledger): `txBAD_SEQ`, dentro del fee bump o por fuera, según haya o no una pendiente.
- **Cada rechazo contaba como una falla** (US-021). Los sellos que chocaban tenían el mismo retraso y **volvían a chocar en cada reintento**: se sellaba más o menos una por ronda (al instante, a 1, 6, 21 y 81 minutos). Con **6 o más evidencias a la vez, las que no alcanzaban quedaban en "Falla de Sellado" tras el quinto intento, para siempre**. Pasa con cualquier ráfaga: dos veedores a la vez, o los reportes guardados sin señal que se envían juntos al volver (it. 30).
- **El candado que debía dejar un sello a la vez no era global.** `WithoutOverlapping('stellar-sealer')` guardaba su llave en la caché, y dentro de una organización la caché lleva su prefijo (`govtrace-cache-tenant_<id>_…`, comprobado en el stack de desarrollo): cada organización tenía el suyo. Y aunque lo hubiera sido, soltaba el candado al enviar, no cuando la transacción entraba en un ledger.

**El arreglo:**
- **La selladora sella por turnos** (`SealerTurn`, tabla central `sealer_turns`): una fila por cuenta selladora, la misma para todas las organizaciones y todos los workers.
  - Se toma con `SELECT … FOR UPDATE NOWAIT`: nadie espera un candado; si otro proceso está en su turno, el sello vuelve en 3 s.
  - Una transacción toma el turno **desde que existe, antes de enviarla**, con su hash calculado localmente: un envío sin respuesta pudo haber llegado.
  - Lo suelta cuando entra en un ledger, cuando la red la rechaza o cuando vencen sus 4 minutos (más 30 s de margen): después, ya no puede entrar.
  - Mientras está tomado, se le pregunta a la red por la pendiente como mucho cada 2 s, sin importar cuántos sellos esperen.
- **"Ocupada" no es una falla** (`SealingNetworkBusy`): el sello vuelve a la cola en 3 s, con sus intentos intactos. `SendRefusal` reconoce las tres respuestas de la red que significan eso; cualquier otro rechazo sigue gastando un intento (US-021), y ahora dice su código (`txBAD_AUTH dentro del fee bump`), no solo el XDR.
- Si la red dice que la selladora tiene **otra pendiente que el turno no conoce** (enviada desde otra parte, o leída de un RPC atrasado), se la deja en paz un ledger (5 s).
- **Se quitó el candado de la caché** de `SealReport`: el turno lo reemplaza.
- `make demo` vuelve a enviar sus 9 reportes de golpe, y ahora es también una prueba de la ráfaga. Además reinicia siempre el worker, que guarda en memoria el código y el `.env` con que arrancó (y la lista de salida pide `queue:restart` tras cada despliegue).

**La capacidad, con una sola selladora:** una transacción por ledger, unos 5 s en testnet y la red principal: cerca de **10 a 12 sellos por minuto** (en testnet, 7 en 40 s). Una ráfaga se sella en orden, sin perder ninguna. Para más, habría que sellar desde varias cuentas de canal con la selladora firmando solo la autorización de Soroban; no se hizo (ver las decisiones).

**Prueba:**
- **Primero en rojo, contra la red local:** el escenario de 7 evidencias de dos organizaciones, con el código de antes, no llegó a sellarlas todas en 90 s.
- **En verde contra la red local** (`make test-stellar`, 17 casos, 6 nuevos): las 7 llegan a "Sellada" en ~26 s, ninguna gasta un intento y cada una entra en su propio ledger. Además:
  - un sello con otra pendiente espera sin armar ni enviar nada;
  - el turno se suelta en cuanto se ve la transacción en un ledger;
  - un envío sin respuesta deja el turno con esa transacción, que de verdad entra;
  - una transacción de la selladora enviada desde otra parte hace esperar al sello (la red la rechaza como "ocupada") y, pasado un ledger, el sello va;
  - un número de secuencia gastado, de un RPC atrasado, es "ocupada", no una falla.
- **Las respuestas de la red, tal cual** (`tests/fixtures/stellar`, 5 capturas del RPC): las tres de "ocupada" (con sus dos variantes de `txBAD_SEQ`) y una firma inválida, que sigue siendo una falla. Con otra pendiente, la red mira el turno antes que la firma: una firma mala llega como `txINSUFFICIENT_FEE` y recién sin la pendiente como `txBAD_AUTH`; una falla de verdad se cuenta igual, un turno después.
- Suite (`make test`): 761 en verde (20 nuevos). El turno se prueba también desde otra conexión, que sostiene el candado: el sello vuelve al instante.
- `make demo` de cero, en un proyecto aislado de Docker, con el worker de verdad (`--tries=3`): los 9 reportes enviados de golpe llegaron a "Sellada" en 27 s, uno cada ~3 s y cada uno en su ledger, sin gastar un intento; 0 trabajos fallidos, aunque el último esperó unos 27 s volviendo cada 3 s, más veces que las 3 de `--tries` (manda `retryUntil`); 6 publicados y 3 en la bandeja.
- **En testnet** (el contrato oficial `CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY`, a pedido del usuario): las 7 evidencias de dos organizaciones, en 40,6 s, **en 7 ledgers consecutivos** (4939156 a 4939162): una por ledger, el máximo con una selladora. Costó 1,9915952 XLM de la patrocinadora, 0,2845 por sello. Las transacciones, leídas de los eventos `sealed` del contrato:
  - `f6d4d858a8a193e28e08e4bd9166171852d40b7de70a1d2a21762db64ee68006` (4939156)
  - `056a588c98fd6c5a4ffd2bd643261f5362ce1402f14cdb7b10953efa09a3b2a4` (4939157)
  - `61a28f0781b9aae1ced63691781a76285dda6042ea1ea980a155a346d35f7976` (4939158)
  - `58d2efdbbfa2579382925b187d2ac37d43f2d601cea5809d3873484c92896f75` (4939159)
  - `e24699415c4be7f160c71553e423a73697793ec1838a56afa25f2cb8dc756883` (4939160)
  - `2076a321adfc878b444fc69607a43c4be38c8eb20c1448294265def00798e9ba` (4939161)
  - `a9a7031197b6389064b307189b95c973cf8a6f5ee629d2dd6197a5eae0e787df` (4939162)
- Cada regla nueva se comprobó rompiéndola a propósito: **15 casos, todos atrapados** —el trabajo contando "ocupada" como falla; el turno esperando el candado, por organización, preguntando a la red cada vez, sin vencer, soltando el de otra transacción o deshaciendo lo escrito; cada una de las respuestas de "ocupada" leída como falla, y todo rechazo leído como "ocupada"; la transacción sin tomar el turno antes de enviarse; el turno sin soltarse al ver la transacción en un ledger; la selladora sin descanso tras "otra pendiente"; y el adaptador sin reconocer "ocupada"—.

**Cubre:** US-021 (criterio y escenario nuevos: "Varias evidencias a la vez esperan su turno sin gastar intentos"), R-INT-01, R-BLK-04 · US-020b, US-018 (los reportes sin conexión que se envían juntos).

**Decisiones de la iteración — ✅ aprobadas por el usuario el 2026-09-29.** La enmienda de R-INT-01 ya está en la SPEC.
1. **Un criterio nuevo en US-021** (`specs/criterios/US-021.yaml` y su escenario en `features/US-021.feature`): varias evidencias a la vez esperan su turno, sin gastar intentos ni quedar en "Falla de Sellado". Y una enmienda a R-INT-01: el sello que encuentra la selladora ocupada espera su turno, sin gastar intentos.
2. **La congestión tampoco gasta intentos.** `txINSUFFICIENT_FEE` también llega si los ledgers van llenos y piden una comisión mayor. Se trata igual que la otra pendiente: el sello espera, sin quedar en "Falla de Sellado". Si durara horas, avisa la alerta de cola estancada (2 h, US-021). La oferta de inclusión del fee bump ya es alta (casi la comisión de recursos), así que es raro quedar por debajo.
3. **Una sola selladora alcanza para el MVP**: 10 a 12 sellos por minuto. Si el volumen lo pidiera, el camino son las cuentas de canal (varias transacciones por ledger). Es un cambio de la firma que conviene hacer junto con AWS KMS (37b), porque la selladora pasaría a firmar la autorización de Soroban y no la transacción.

#### Iteración 40 — Usable por cualquiera
⬜ **Propuesta el 2026-09-29, por aprobar.** Nació de probar la demo: el usuario notó que no hay botón de salir, que las pantallas confunden y que los estados del mapa quedan debajo del mapa. Pidió que fuera muy fácil de usar para gente muy diversa, incluidos adultos mayores. El análisis completo está en `docs/ux-analisis.md`: un recorrido con Playwright por las 26 vistas, en celular y escritorio, axe y mediciones de letra y botones.

Esta iteración estaba reservada para ese recorrido. El recorrido se hizo para el análisis, y ahora pasa a ser el punto de partida: la **40a** lo deja en el repositorio como el *checkpoint base*, junto con los flujos de cada rol de `docs/mapa-funcional.md`. Las otras tres partes corrigen lo encontrado. Un commit por parte.

**40a — Checkpoint base: el recorrido, axe y los flujos, como tests.** Pedido por el usuario el 2026-09-29: "definir un checkpoint base para mejorar todo lo que se deba de mejorar". Hoy hay más de 1.100 tests, cada uno de una historia sola, y solo 2 en un navegador. Ninguno recorre el flujo completo de un rol, y por eso pasaron los vacíos de `docs/mapa-funcional.md`.
- El tag `checkpoint-base` marca el código tal como se analizó.
- `make ux-check`: el recorrido de Playwright por las 27 vistas en los dos tamaños, con capturas (artefacto de Jenkins) y las mediciones de axe, letra y botones. Falla si algo empeora respecto de la línea base del análisis.
- Los flujos de punta a punta como tests de Playwright (`make e2e`): uno por rol (Super Administrador, Administrador, veedor, ciudadano) y uno entre roles, de la veeduría interesada al ciudadano que comprueba la evidencia.
  - Los pasos que hoy funcionan pasan.
  - Cada vacío queda como `test.fixme('V2: …')`, con su número de `docs/mapa-funcional.md`.
- Los dos, en el pipeline.

**Done-when de la 40a:**
- `make ux-check` y `make e2e` corren en local y en el pipeline;
- cada paso de la sección 2 de `docs/mapa-funcional.md` tiene su test: ✅ pasa, y ⬜ o ⚠️ es un `fixme` con su vacío;
- la línea base (vacíos pendientes, violaciones de axe, letra y botones) queda anotada en `docs/estado-mvp.md`.

✅ **40a cumplida (2026-09-29).** El usuario pidió seguir sin revisión hasta el día siguiente, con la interfaz como prioridad: "como si mañana fuera el día de la demo".
- **El fixture** (`tests/e2e/fixture.php`) deja el Super Administrador, la Administradora, el veedor y cinco reportes por el camino de un teléfono (`CreateReport`, la huella, el sello): dos publicados, dos en la Bandeja y uno rechazado con su motivo. Se sellan en la red en memoria de los tests, sin Stellar ni cola, en unos 3 segundos. Tampoco dispara la sincronización con SECOP.
- **Los flujos** (`tests/e2e/flujos/`): el de alta entre roles, con el enlace de cada invitación leído del correo de desarrollo, y uno por rol (Super Administrador, Administradora, veedor y ciudadano). **27 pasos pasan y 20 son `fixme`**, cada uno con su vacío (`V1`… `V15` y la Ley 1581). Corren en unos 40 segundos.
- **`make ux-check`** (`tests/ux/pantallas.spec.js`): 52 pantallas (26 por tamaño), con capturas, axe, letra y botones.
  - Falla si aparece una regla nueva de axe, si la letra o los botones empeoran más allá de la tolerancia (5 y 10 puntos), o si hay una pantalla sin línea base. `make ux-baseline` reescribe la línea base.
  - El sellado y la salud de SECOP se fotografían pero no se comparan: dependen de la red Stellar y del historial de SECOP.
- **Probado rompiéndolo:** una línea base con la letra "mejor" de lo que está, sin una regla de axe que sí aparece, y sin una pantalla. Los tres casos se atraparon, cada uno con su mensaje.
- **En el pipeline:** la etapa E2E corre los dos y guarda las capturas como artefacto.

**40b — Lo urgente.**
- Sesión:
  - "Salir" y el nombre del usuario en los paneles del Administrador y del Super Administrador;
  - la ruta de salida del dominio central (hoy no existe);
  - el "Salir" del veedor pasa a "Mi cuenta", siempre con confirmación.
- El nombre de la obra en cada tarjeta de la Bandeja.
- El estado de la obra y su motivo en la ficha pública, según las reglas de US-027.
- En el mapa: filtros que hacen de leyenda, arriba del mapa; íconos en los pines; los mismos nombres de estado en todas partes.
- "Enviar Reporte" dice qué falta.
- Arreglos de código:
  - las clases `sm:` pasan a `md:`: `sm` está apagado en `app.css` y 11 clases de escritorio nunca se aplicaban;
  - el `h1` es el título de cada pantalla;
  - "cancelled" y "Success" pasan a español.

**Done-when de la 40b:**
- Los `fixme` de V1 y V4 (la obra), en verde.
- Playwright:
  - "Salir" queda a dos toques o menos desde cada pantalla con sesión;
  - los estados del mapa se ven sin desplazarse, en 412×915 y en 1366×768.
- Pest: salir invalida la sesión, en el dominio central y en una organización.
- axe: cero violaciones de WCAG 2.2 AA en las 52 vistas.
- Ningún `sm:` en `resources/js`.

✅ **40b cumplida (2026-09-30).** Se hizo sin revisión del usuario, como pidió ("como si mañana fuera el día de la demo"). Las decisiones de abajo quedan para confirmar.
- **Salir (V1):** un menú de cuenta en la cabecera de todos los paneles (`AccountMenu.vue`) con el nombre, el rol, el correo, "Ver el sitio público" y "Salir".
  - "Salir" siempre pregunta antes. Al veedor además le avisa si quedan reportes sin enviar (US-018; el aviso pasó de la barra de abajo al menú).
  - El dominio central ya tiene su `POST /logout`.
  - Quién tiene la sesión abierta viaja en la prop compartida `account`.
- **La Bandeja (V4):** cada evidencia dice su obra, su municipio y qué veedor la envió (`ReviewInbox`, dos consultas para toda la bandeja).
- **El mapa:**
  - `h1` "Obras vigiladas" y una línea de qué hacer;
  - arriba del mapa, los tres estados con su signo (✓ ! ✕) y cuántas obras tiene cada uno; tocarlos filtra. La leyenda de abajo se quitó;
  - pines de 36 px con el mismo signo;
  - el resto de filtros, en "Más filtros";
  - el Resumen del Administrador usa los mismos nombres (Normal, Alerta, En riesgo).
- **La obra:** su estado y por qué, en palabras (`WorksiteCondition`, con las mismas reglas que el pin: `PinColor` y el contrato vencido en ejecución). Nombra cada razón que cuenta.
- **Nuevo reporte:** mientras no se puede enviar, junto al botón se lee "Para enviar falta:" y la lista: GPS, qué vio, al menos un archivo, el comentario largo.
- **Código:**
  - cada pantalla tiene su título en `h1`, y el nombre de la organización pasó a ser la marca de la cabecera;
  - las 11 clases `sm:`, a `md:` (y una más en Uso), con un test que vigila que no vuelvan;
  - la lista de Organización ya no incumple axe;
  - el inicio de sesión de una organización muestra su nombre y su logo, y "← Volver al mapa de obras";
  - varios enlaces pasaron a 44 px.
- **Spec:** un escenario nuevo en US-031, US-036, US-027, US-029 y US-008, primero en su YAML (requisitos de UX) y después en Gherkin. `make trace-check`: 255 de 255.

**Prueba:**
- Pest:
  - salir en los dos dominios;
  - quién tiene la sesión, incluida la pantalla pública sin nadie;
  - la obra y el veedor de la Bandeja;
  - el estado de la obra en 6 casos, incluido que coincide con su pin.
- Vitest: 369 de 369.
- `make e2e`: 30 pasan y 17 siguen pendientes. Se cerraron V1 en los dos paneles y V4, y los estados del mapa se ven sin desplazarse en 412×915 y en 1366×768.
- `make ux-check`:
  - ninguna pantalla empeoró, y la línea base se reescribió con las mejoras;
  - axe quedó en cero violaciones;
  - en la pantalla de inicio de sesión, el texto de menos de 16 px bajó del 72 al 36 %; en Nuevo reporte, del 57 al 32 %; en el mapa, del 87 al 53 %.

**Decisiones de la iteración (❓, por confirmar):**
1. **"Salir" siempre pide confirmación**, para todos los roles. Antes, el veedor salía de un toque si no tenía reportes pendientes.
2. **En Salud de SECOP se deja "Success".** El escenario de US-014 fija ese texto (`estado "Success"`); cambiarlo a "Correcta" es una enmienda que decide el usuario.
3. **El estado interno `cancelled`** se muestra en Contratos como "Anulado/Retirado en SECOP", como ya lo decía la tarjeta pública (US-017).
4. **Los cinco escenarios nuevos del spec** (lista de arriba).

**40c — Navegación y legibilidad.**
- Una cabecera común con el menú de cuenta.
- En celular, la barra de abajo con ícono y texto (máximo 5 destinos); en escritorio, una barra lateral.
- Letra con base de 16 px y botones de 44 px en los paneles.
- Componentes comunes y un set de íconos SVG empaquetado, sin tocar la CSP.
- El glosario de lenguaje claro del análisis, aplicado.
- Buscador en Contratos.
- Vista de lista en el mapa y agrupación de pines cercanos.

**Done-when de la 40c (Playwright):**
- ningún control por debajo de 44 px, salvo los enlaces dentro de un texto;
- como mucho el 5 % del texto por debajo de 14 px en cada pantalla;
- cada pantalla con un `h1` igual a su título.

✅ **40c cumplida (2026-09-30)**, sin revisión del usuario, como la 40b.
- **Navegación** (`PanelTabs.vue`, `PanelSidebar.vue`, con los íconos de Heroicons, MIT, empaquetados: la CSP no cambia):
  - en el celular, tres pestañas con ícono y nombre y "Más" con el resto; la pestaña activa, con una barra;
  - en el computador, una barra lateral por grupos (Revisar · Territorio y obras · Equipo · Organización);
  - la Bandeja cuenta cuántas evidencias esperan (la prop `inboxPending`, solo para el Administrador);
  - el veedor, con sus dos pestañas con ícono;
  - en el computador, la barra de abajo deja de ser fija.
- **Letra (R-UX-01):** en `app.css`, `text-xs` pasa a 14 px y `text-sm` a 16 px, en toda la app.
- **Botones (R-UX-02):**
  - las acciones de cada fila, los "Quitar" y los botones de 40 px pasan a 44 px;
  - el zoom del mapa, a 44 px.
- **Lenguaje claro (glosario de `docs/ux-analisis.md`):**
  - "Comprobar que es original" en lugar de "Verificar Sello Blockchain";
  - "Con sello digital · bloque N";
  - "Sello digital" y "Publicación" en lugar de estado técnico y editorial;
  - "¿Qué vio en la obra?", con una línea de explicación por opción;
  - el territorio explicado;
  - "Unir con otra obra";
  - el validador sin "huella" ni "red Stellar";
  - "Descargar para Excel (CSV)" y "Datos para programadores (JSON)".
- **Buscadores:**
  - Contratos, por objeto, contratista, número de proceso o id de SECOP (`q` en `/contracts`);
  - el mapa también como **lista** (`/public/worksites/list`, pedida al abrirla: la carga del mapa no cambia, R-MAP-02), con búsqueda por nombre sin distinguir tildes (V12).
- **"Cambiar contraseña"** en el menú de cuenta, en los dos dominios (V11): pide la actual y aplica las reglas de US-030, con un límite de 6 intentos por minuto. El inicio de sesión y esta pantalla tienen "Mostrar" en la contraseña.
- **Spec:**
  - escenarios nuevos en US-039-USR, US-015 y US-028 (YAML primero);
  - el botón del sello, enmendado en US-024 y US-029 (criterio y Gherkin);
  - `make trace-check`: 258 de 258.

**Prueba:**
- Pest:
  - cambiar la contraseña, en 6 casos: veedor, Super Administrador, la actual equivocada, la nueva débil o distinta, y sin sesión;
  - el buscador de Contratos;
  - la lista del mapa;
  - el contador de la Bandeja, probado rompiéndolo.
- Vitest: 382 de 382.
- `make e2e`: 32 pasan y 15 siguen pendientes (se cerraron V11 y V12).
- `make ux-check`:
  - axe sigue en cero;
  - en la pantalla típica, el texto de menos de 16 px bajó del 73 al 12,5 %, el de menos de 14 px del 25,5 al 0 %, y los botones de menos de 44 px del 66 al 0 %;
  - la medición deja la barra de abajo al final de la página antes de correr axe, porque en el celular tapaba un rato lo que pasa por debajo según el desplazamiento; la captura se toma como la ve la persona.

**Decisiones de la iteración (❓, por confirmar):**
1. **Los textos del glosario que el spec fijaba** ("Verificar Sello Blockchain" en US-024 y US-029) se enmendaron a "Comprobar que es original". El botón dejó de ser el elemento más llamativo de la tarjeta (US-029 lo pedía "destacado").
2. **Las explicaciones de Avance, Retraso y Abandono son provisionales:** las valida una veeduría, porque cambian el color del mapa.
3. **La escala de letra cambia en toda la app a la vez**, en lugar de pantalla por pantalla.

**40d — Orientación y confianza.**
- El inicio central con "¿Cómo funciona?" y el directorio de veedurías.
- La guía de primer uso del veedor.
- En Nuevo reporte: los pasos a la vista, y "Tomar foto" o "Galería".
- La auditoría del Administrador, en frases.
- El nombre corto de la ficha de obra.
- Unir contratos eligiéndolos de una lista.
- La prueba con 5 personas, al menos 2 mayores de 60.

**Done-when de la 40d:** en la prueba con personas, al menos 4 de 5 terminan cada una de las 6 tareas sin ayuda.

⚠️ **40d, primera parte cumplida (2026-09-30).** Es lo que la demo necesita, hecho sin revisión del usuario. El resto espera decisiones o personas.
- **El Inicio central** (V5): "Veeduría ciudadana de obras públicas", "¿Cómo funciona?" en tres pasos con ícono, y el directorio de veedurías.
  - El directorio (`PublicDirectory`) trae el nombre elegido, el territorio ("Vigila: Magdalena") y "Ver su mapa de obras".
  - Las suspendidas siguen, con su aviso; las dadas de baja, no.
  - El acceso de administradores de GovTrace va al pie.
- **"Entrar"** en la cabecera de las pantallas públicas de cada veeduría, para su gente (no en las de acceso ni en el dominio central).
- **El mapa:** "¿Cómo funciona?" plegado junto al título; "Validar un archivo" y "Estadísticas" debajo del mapa.
- **Nuevo reporte:** "Tomar foto" (cámara trasera, `capture="environment"`) y "Elegir de la galería o un PDF".
- **Spec:** escenarios nuevos en US-027 (el directorio) y US-009 (la cámara). `make trace-check`: 260 de 260.

**Prueba:**
- Pest: el directorio, con activas, suspendidas y dadas de baja, el nombre elegido y el directorio vacío.
- Vitest: 387 de 387.
- `make e2e`: 34 pasan y 13 siguen pendientes. Se cerró V5, en el flujo del ciudadano y en el de alta: la veeduría nueva aparece en el Inicio.
- `make ux-check`: sin regresiones, con la línea base reescrita.

**Decisiones de esta parte (❓):**
1. **El directorio de veedurías, publicado** (la decisión 2 de `docs/ux-analisis.md`). R-MAP-01 no lo impide: no mezcla mapas.
2. **El texto de "¿Cómo funciona?"**, en lenguaje claro.

**Queda de la 40d:**
- la guía de primer uso del veedor;
- los pasos a la vista en Nuevo reporte;
- la auditoría en frases;
- el nombre corto de la ficha de obra (❓ decisión 3);
- unir contratos eligiéndolos de una lista;
- la prueba con 5 personas (❓ decisión 6).

**Cubre:** las reglas R-UX-01 a R-UX-09 propuestas en `docs/ux-analisis.md` (sección 2), que entran a la SPEC si el usuario las aprueba · US-031 (iniciar sesión, y ahora salir), US-008, US-010, US-015, US-017, US-024, US-027, US-028, US-029, US-036, US-045-INT, US-049-RPT, US-051-RPT.

**Decisiones por confirmar (❓), en la sección 9 del análisis:**
1. las reglas R-UX en la SPEC, con WCAG 2.2 AA como meta;
2. el directorio de veedurías en el inicio central;
3. el nombre corto de la ficha de obra (respeta R-SEC-01: el nombre de SECOP se sigue viendo intacto);
4. la explicación de Avance, Retraso y Abandono, que valida una veeduría;
5. "sello digital" en lugar de "blockchain", y "comprobante" o "recibo";
6. conseguir a las 5 personas de la prueba.

**Modelo:** Sonnet medium. Solo una pieza toca el acceso, la ruta de salida del dominio central: es pequeña, estándar y va con sus tests.

#### Iteración 41 — Salir a internet con seguridad
✅ **Cumplido (2026-09-29).** Aprobada por el usuario ("continúa", sobre el orden de `docs/estado-mvp.md`), con la vara de que el MVP esté **listo para producción**. No necesita AWS: deja la aplicación lista para estar detrás de un proxy con TLS; el certificado y el servidor son de la it. 42 (staging).

**Entregable:**
1. **Laravel detrás del proxy:** confía en el proxy de la red privada (`TRUSTED_PROXIES`), y solo en `X-Forwarded-For` y `X-Forwarded-Proto`, que nginx sobrescribe. Nunca en `X-Forwarded-Host` ni `-Port`, que nginx deja pasar tal como los manda el visitante (con ellos se envenenarían los enlaces), y que ahora además borra. Detrás de TLS, la aplicación sabe que la visita llegó por HTTPS, y ve la IP real del visitante.
2. **Cabeceras de seguridad** en toda respuesta: una CSP con los dos únicos orígenes externos (las imágenes del mapa y el RPC público de Stellar), `Permissions-Policy` (cámara y ubicación solo para el sitio) y, por HTTPS, HSTS y `upgrade-insecure-requests`. Una respuesta que ya trae su CSP (el logo) la conserva.
3. **Límites de abuso:** el envío de reportes, por veedor y por hora; las API públicas, por visitante y por minuto; los datos abiertos, más estrecho. Pasado el límite, 429 con un mensaje en español. La PWA guarda el reporte en la bandeja de salida y lo envía sola más tarde.
4. **Auditoría de dependencias** (`make audit`) y su etapa en el pipeline.
5. **Logs diarios con retención** en la plantilla de producción.
6. **La aplicación en español:** `APP_LOCALE=es` y `lang/es`, para los correos de Laravel y los mensajes de validación por defecto. Cierra esa deuda aceptada.
7. **La plantilla de producción vigilada por un test.**

**Done-when:**
- Pest: detrás del proxy, con `X-Forwarded-Proto: https`, la petición es segura, las redirecciones salen en https y va HSTS; desde otra IP, esas cabeceras se ignoran; `X-Forwarded-Host` y `-Port`, siempre.
- CSP y `Permissions-Policy` en páginas y JSON; el logo conserva la suya.
- Un veedor pasado el límite recibe 429 y otro veedor no se ve afectado; la API pública limita por visitante detrás del proxy.
- Un correo de GovTrace sin frases en inglés.
- Vitest: un 429 guarda el reporte en la bandeja de salida.
- Playwright: las pantallas públicas y del veedor, con la CSP puesta, sin ninguna violación en un Chromium de verdad.
- `make audit` en verde, y en el pipeline.

**Hallazgo al diseñarla:** nginx sobrescribe `X-Forwarded-For` y `X-Forwarded-Proto`, pero deja pasar `X-Forwarded-Host` y `X-Forwarded-Port` tal como los manda el visitante. Si Laravel hubiera confiado en todas las cabeceras del proxy, un atacante habría podido envenenar los enlaces que se arman con el host de la petición, como el de la recuperación de contraseña del panel global. Por eso se confía solo en las dos primeras, y nginx ahora borra las otras.

**Prueba:**
- Suite: 785 en verde (24 nuevos). Vitest: el caso del 429, que ahora deja el reporte en la bandeja de salida (antes quedaba en el formulario, y se perdía si el veedor salía de la pantalla).
- **Playwright, en un Chromium de verdad, con la CSP puesta:** el mapa público (que pide sus imágenes a OpenStreetMap), la vista de una obra, las estadísticas, el validador, el inicio de sesión, el Service Worker, la búsqueda, el GPS, la vista previa de una foto y "Mis Reportes", **sin una sola violación**. La E2E sin conexión sigue pasando.
- `make audit`: sin vulnerabilidades conocidas (composer audit y npm audit de producción), en 3 s.
- **Cada regla, rota a propósito: 16 casos, todos atrapados.** Los cubiertos:
  - confiar también en el host y el puerto reenviados;
  - confiar en cualquiera, o en nadie;
  - HSTS por HTTP;
  - pisar la CSP del logo;
  - la CSP sin el RPC, o con su URL y su token;
  - el servidor de Vite en producción;
  - el límite de reportes sin la organización en la llave;
  - un solo límite público para todos;
  - los datos abiertos con el límite general;
  - el envío sin límite;
  - el límite por la IP del proxy;
  - el correo sin una traducción;
  - la pantalla sin guardar el reporte ante el 429;
  - la CSP sin las imágenes del mapa.
- **Que el detector del navegador funcione, probado aparte.** La última mutación la atrapaba la comprobación de la cabecera, no el navegador. Así que se probó una CSP sin `'unsafe-inline'` en los estilos: el navegador reportó los estilos en línea que inyecta la aplicación, y el test los listó y falló. Eso prueba el detector, y además prueba que ese permiso hace falta. Los estilos no ejecutan código; los scripts siguen solo desde el propio sitio.

**Decisiones de la iteración — a confirmar por el usuario:**
1. **Los números de los límites:** 30 reportes por veedor y por hora (la bandeja de salida de la PWA guarda 10: cabe entera), 120 consultas públicas y 10 descargas de datos abiertos por visitante y por minuto. Se cambian por el entorno, sin tocar código.
2. **HSTS con los subdominios** (`includeSubDomains`, un año): cada organización es un subdominio, y todos quedan obligados a HTTPS. No se pidió la precarga en los navegadores (`preload`), que es difícil de deshacer.
3. **El español por defecto**, también en la plantilla de producción.

#### Iteración 42a — El stack de producción, probado en local con TLS
✅ **Cumplido (2026-09-29).** Aprobada por el usuario ("continúa"), con la vara de "listo para producción". La mitad de staging que no necesita AWS; la 42b lo apunta a una cuenta de AWS.

**Por qué, además:** al diseñarla apareció un defecto que la it. 41 no podía ver, porque sus tests no pasan por Apache. En el contenedor de la aplicación, `mod_remoteip` cambia la IP del proxy por la del visitante antes de que PHP la vea. En producción, Laravel vería la IP pública del visitante, no la del proxy, y no le creería el `X-Forwarded-Proto: https`: no sabría que la visita llegó por HTTPS. Los enlaces al CSS y al JavaScript saldrían con `http://` dentro de una página `https://`, el navegador los bloquearía y la aplicación se rompería. Solo se ve con el stack entero: nginx con TLS, Apache y PHP.

**Entregable:**
1. `docker-compose.prod.yml`: la imagen inmutable (etapa `qa`, el código adentro), nginx con TLS, PostgreSQL sin puerto publicado, el worker, el calendario y los respaldos con S3 de verdad. Sin montar el código, sin LocalStack, sin la red Stellar local. Los secretos, por el entorno del proceso que lo levanta; ninguno en un archivo.
2. nginx de producción: TLS 1.2 y 1.3 con un certificado comodín (`DOMAIN` y `*.DOMAIN`: cada organización es un subdominio), HTTP a HTTPS, las cabeceras del proxy como en la it. 41.
3. Apache: la visita que llegó por HTTPS al proxy lo es también para PHP.
4. `deploy/deploy.sh`: construye la imagen, migra (central y organizaciones), siembra la DIVIPOLA, levanta todo, optimiza y comprueba `/up` por HTTPS. Se puede repetir.
5. `.env.staging.example`: la plantilla de staging (testnet, S3 y SES), sin secretos.
6. `make staging-check`: todo lo anterior en un proyecto aislado de Docker, con un certificado de prueba de una CA propia y LocalStack haciendo de S3.

**Done-when (`make staging-check`):**
- HTTPS responde con el certificado de la CA de prueba; TLS 1.1 no.
- HTTP redirige a HTTPS.
- Por HTTPS, la aplicación sabe que es HTTPS: HSTS, redirecciones y enlaces a los recursos en `https://`.
- Una organización responde en su subdominio por HTTPS.
- Cookies `Secure`; sin depuración a la vista en una página de error; `X-Forwarded-Host` del visitante, ignorado.
- El worker, el calendario y los respaldos, sanos. Desplegar dos veces no rompe nada.

**Lo que encontró, además del defecto de Apache (los tres, arreglados):**
1. **Apache y el HTTPS** (el motivo de la iteración). Reproducido con las condiciones de producción: con un visitante que no es de confianza, sin HSTS, las redirecciones a `http://` y el CSS enlazado por `http://` (`href="http://…/build/assets/app-….css"`), que el navegador bloquea como contenido mixto. Arreglado en `vhost.conf`: `SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on`. Se cree sin mirar quién lo manda porque solo el proxy llega al contenedor, y nginx siempre sobrescribe esa cabecera.
2. **La imagen inmutable no compilaba desde la it. 27.** El validador del navegador importa `tools/verify/lib`, y la etapa de assets no la copiaba. Nadie lo notó porque el pipeline usa la imagen de desarrollo. Ahora `make staging-check` la construye en cada PR (etapa "Production Stack").
3. **Un error de la propia prueba:** la plantilla de staging pisaba sus puertos, y la primera versión corrió publicada en todas las interfaces (`0.0.0.0:80` y `:443`). Se bajó al verlo. Ahora la prueba usa nombres propios y publica solo en `127.0.0.1` (`PUBLISH_IP`).

**Qué quedó:**
- `docker-compose.prod.yml`: cada variable de la aplicación se nombra sin valor y llega del entorno de `deploy/deploy.sh`; una comprobación vigila que no falte ninguna de la plantilla. Los logs diarios, en un volumen que el usuario de la aplicación puede escribir (la imagen deja `storage/logs` con ese dueño). Los logs de los contenedores rotan (20 MB, 5 archivos).
- nginx: TLS 1.2 y 1.3 con los cifrados "intermediate" de Mozilla, HTTP/2, HTTP a HTTPS con el mismo host, y el `Host` validado contra `server_name` con el puerto público si no es el 443.
- `deploy/deploy.sh`: construye, migra antes de cambiar los contenedores (los que corren siguen atendiendo), levanta todo, guarda la configuración y las rutas en caché y comprueba `/up` por HTTPS. Vuelve a la versión anterior con `APP_IMAGE=<la anterior> SKIP_BUILD=1`.
- `.env.staging.example`, sin secretos (`make secrets-check` ahora la revisa).
- **Una organización nueva responde por HTTPS en su subdominio, con el certificado comodín.**

**Prueba:**
- `make staging-check`: **20 de 20**. Las 18 del criterio, más dos: los tres contenedores de la aplicación escriben en el volumen de logs, y los logs sobreviven al despliegue siguiente. Unos 4 minutos, con la imagen en caché.
- **El stack, roto a propósito: 4 casos, todos atrapados**, cada uno con sus fallas exactas:
  - Apache sin reconocer el HTTPS del proxy: 5 fallas, las de producción (sin HSTS, redirección a `http://`, el CSS por `http://`);
  - nginx sin decir que la visita llegó por HTTPS: las mismas 5;
  - la imagen sin `storage/logs`: ni la app, ni el worker, ni el calendario podían escribir sus logs;
  - el despliegue sin la configuración en caché.
- **`make demo` sobre un stack ya levantado se caía:** si Docker recreaba la app, el proxy de desarrollo se marcaba enfermo (su chequeo pasaba por la app) y `up --wait` abortaba. Ahora el proxy responde su propio `/healthz`, como el de producción. Cierra la deuda aceptada del "proxy impaciente".

#### Iteración 43 — Flujos completos
⬜ **Propuesta el 2026-09-29, por aprobar.** Cierra los vacíos de `docs/mapa-funcional.md` que no caben en la it. 40. Casi todos son historias nuevas: según el marco, pasan antes por un `/discovery` corto, en modo asesor, con sus criterios y su Gherkin. Las decisiones de la sección 5 del mapa lo alimentan.

**43a — El gobierno de las organizaciones.**
- V2, V3 y V16: cada organización muestra en el panel global sus administradores y el estado de su invitación. El Super Administrador reenvía la invitación, asigna un administrador nuevo o desactiva uno.
- V7: la pantalla para reportar en nombre de una organización que lo autorizó. La regla ya existe (US-042-SEC).
- V8: la razón social, editable junto con el NIT (US-011).

**Modelo:** toca quién controla cada organización, es decir, el acceso: **Opus xhigh**.

⚠️ **43a, V2 y V16 cumplidos (2026-09-30)**, sin revisión del usuario y con Opus.
- **Sin `/discovery` nuevo:** cabe en historias que ya existían. US-002 dice que el Administrador inicial se asigna "tras el alta, o en un paso consecutivo", y el reenviar y revocar es el de US-040-USR, ahora desde el panel global.
- **El panel global** (`OrganizationAdministrators` y `OrganizationAdministratorController`):
  - cada organización muestra su Administrador y el estado de su invitación: activo, pendiente, vencida o inactivo;
  - el Super Administrador reenvía una invitación sin responder (el enlace anterior deja de servir) o la revoca si el correo estaba mal;
  - a una organización sin Administrador le asigna uno;
  - cada cambio va al log de auditoría como `super_admin`.
- **No se permite** asignar un segundo Administrador ni reemplazar uno activo: responde 409 y explica que primero hay que revocar la invitación. Eso es la decisión V3.
- **Spec:** dos escenarios nuevos en US-002. `make trace-check`: 262 de 262.

**Prueba:**
- Pest: 7 casos. Estado de la invitación, reenviar (enlace nuevo, auditado, segundo correo), revocar, asignar sin Administrador, no asignar un segundo, 404 para un veedor y solo para el Super Administrador.
- Vitest: 391 de 391.
- `make e2e`: 35 pasan y 12 siguen pendientes. En el flujo de alta, el Super Administrador reenvía la invitación antes de que el Administrador la use. Se comprueba que el primer enlace ya no deja activar la cuenta y que la activación funciona con el nuevo.

**Queda de la 43a:**
- V3 (❓ varios administradores o reemplazarlo);
- V7 (la pantalla para reportar en nombre de una organización);
- V8 (la razón social).

**43b — Llegar y volver.**
- V6: la app del veedor, instalable, con un manifiesto por veeduría (su nombre y su logo) y los íconos.
- V10: la solicitud de alta en el Inicio, que llega al Super Administrador para que apruebe o rechace (sin autorregistro).
- V9: el aviso diario al administrador con las evidencias por revisar.
- V13, V14 y V15: el contacto de la veeduría, el enlace al script de verificación y "Sincronizar ahora".

**Modelo:** Sonnet medium.

⚠️ **43b, V6, V14 y V15 cumplidos (2026-09-30)**, sin revisión del usuario.
- **V6, la app instalable:**
  - cada veeduría tiene su manifiesto (`/manifest.webmanifest`), con su nombre ("GovTrace · …"), que abre en "Nuevo Reporte";
  - los íconos, en PNG de 192 y 512 px y en SVG, van en `public/pwa/`: Apache reserva `/icons/` para los suyos;
  - las páginas de cada veeduría lo enlazan, y el panel global no;
  - si el navegador lo permite, el menú de cuenta ofrece "Instalar la app en este celular".
- **V14:** el validador enlaza el verificador independiente del repositorio (`APP_VERIFIER_URL`, US-046-INT).
- **V15:** "Sincronizar ahora" en la salud de SECOP II. Pone en cola la sincronización de todas las organizaciones; otra pedida en los 5 minutos siguientes responde 429.
- **Spec:** escenarios nuevos en US-018 (instalar) y US-014 (sincronizar), y un requisito de UX en US-046-INT. `make trace-check`: 264 de 264.

**Prueba:**
- Pest:
  - el manifiesto: nombre, inicio e íconos; enlazado solo en las veedurías;
  - el verificador;
  - sincronizar: en cola, espera de 5 minutos y solo para el Super Administrador.
- Vitest: 394 de 394.
- `make e2e`: 38 pasan y 9 siguen pendientes (V3, V7, V8, V9, V10, V13 y la Ley 1581).

**Queda de la 43b:**
- V9 (❓ el aviso diario);
- V10 (❓ la solicitud de alta);
- V13 (❓ el contacto).

**Done-when de las dos:** los `fixme` de sus vacíos, en verde.

**43c — El ensayo de la demostración.** El usuario pidió "lo mínimo para mostrar". Se ensayó el guion de `docs/local-environment-setup.md` de punta a punta sobre `make demo`, en un Chromium de verdad, con el sellado y el validador reales. Los seis momentos pasaron; se corrigió lo que se veía mal en vivo.

✅ **43c cumplida (2026-09-30)**, sin revisión del usuario.
- **US-026, "Descargar archivo original":** la tarjeta de una evidencia publicada solo ofrecía descargar los PDF. Las fotos no tenían botón, aunque el backend (it. 23) ya entregaba su `download_url` y su `proof_url`, y el E2E del ciudadano los pedía por el API. Ahora:
  - cada archivo tiene **Descargar archivo original** y **Descargar su prueba**;
  - numerados ("1 de 2") cuando son varios;
  - una lápida no tiene ninguno de los dos.

  Con eso, el validador se muestra entero con clics: la foto original da ✅; alterada, da ⚠️ "no encontrado" con **Un archivo** y ❌ "Alterado" con **Archivo y su prueba**.
- **La cuenta de la Bandeja** en la navegación (it. 40c) no bajaba al publicar o rechazar: se quedaba en 3 hasta cambiar de pantalla. Ahora, cada decisión de "Por revisar" vuelve a pedir `inboxPending` al servidor (una recarga parcial de Inertia).
- **`make demo`:** los contratos de demostración no tenían valor. La ficha de la obra mostraba "Valor: —" y el filtro de valor mínimo del mapa no tenía qué filtrar. Ahora cada contrato lleva uno.
- ❓ **Decisión por defecto:** "Descargar su prueba" es el nombre del JSON de la prueba de inclusión, igual que el modo "Archivo y su prueba" del validador.

**Prueba:**
- Vitest: 398 de 398:
  - la foto con sus dos descargas;
  - la numeración;
  - la lápida sin descargas;
  - la Bandeja pide su cuenta otra vez.
- Pest: el test de `make demo` exige el valor de cada contrato.
- `make e2e`: 38 pasan y 9 siguen pendientes; el ciudadano ahora descarga con los botones.
- `make ux-check`: sin retroceso frente a la línea base.
- `make trace-check`: 264 de 264.
- El ensayo mismo, fuera del repositorio (`storage/framework/testing/ux/demo/`):
  - el Inicio, el mapa y la lista;
  - el validador con la foto descargada, alterada y con su prueba;
  - el Administrador publica, rechaza e invita;
  - el veedor invitado activa su cuenta, reporta y llega a "Sellado" (unos 10 s en la red local);
  - el Super Administrador crea una organización y reenvía la invitación, y sincroniza SECOP.

**43d — La demostración en el lugar de la presentación.** La demostración será en un portátil, en Medellín. El servidor rechaza un reporte hecho a más de 500 m de su obra (la geocerca), y todas las obras de `make demo` están en Magdalena. Por eso el veedor no podía reportar en vivo. El ensayo de la 43c no lo mostró porque simulaba el GPS en Santa Marta.

✅ **43d cumplida (2026-09-30).**
- `make demo LUGAR="6.2442,-75.5812"` lleva la obra de la Calle 30 a ese punto, con sus reportes a unos metros.
- Las demás obras se quedan en Magdalena.
- Al terminar, dice qué obra quedó y dónde.
- Un LUGAR que no es «latitud,longitud», o que está fuera de rango, se rechaza antes de tocar nada.
- Las reglas no se relajan: la geocerca y la precisión de 50 m (US-008) siguen iguales. Si el WiFi del portátil no da esa precisión, el guion dice cómo simular la ubicación con DevTools.

**Prueba:**
- Pest:
  - la obra anclada, con sus reportes dentro de 100 m;
  - las demás en Magdalena;
  - lo que dice el comando;
  - tres LUGAR inválidos: un nombre, un solo número y fuera de rango.
- El ensayo con el GPS en Medellín: el veedor encuentra la Calle 30 en «Obras cercanas», la reporta y llega a «Sellado».

## Fase v1 — Alinear con el proceso actual (2026-09-30)

La revisión documental del control social de hoy (`docs/proceso-actual.md`, PR #60) comparó GovTrace con la ley. Encontró que GovTrace termina en el mapa, un paso antes del proceso formal, y que nuestro "En riesgo" se confunde con la "obra inconclusa" de la ley.

**Decisiones del usuario (2026-09-30):**
- A1 a A5 entran en el MVP v1.
- A2 exige correo verificado.
- A3 lleva las plantillas, que citan el sello de Stellar como "Prueba Pericial Criptográfica"; sale en PDF, y cada descarga queda registrada para medir su uso.
- A5 se destraba sin validar en campo: el NIT pasa a opcional, con el número de la resolución o el acta y la entidad de registro como alternativa.

**Orden:** A1 → A3 → A4 → A5, y después A2.

### Iteración 44 — Del mapa al proceso formal

**44a — A1: el estado es una alerta, y los canales de la Contraloría** (US-055-LEG, EPIC-008).
- Bajo el estado de cada obra: es una alerta de GovTrace, no la decisión de una autoridad.
- En una obra "En riesgo": no es una "obra inconclusa" (Ley 2020 de 2020).
- En cada obra, "¿Sabe de un problema en esta obra?", con los canales oficiales de la Contraloría y la aclaración de que, con recursos locales, puede ser competente la contraloría territorial.
- El "¿Cómo funciona?" del mapa y las estadísticas, con la misma aclaración.
- **Done-when:** los 6 escenarios de `features/US-055-LEG.feature`, en verde; `make ux-check` sin retroceso.
- **Modelo:** Opus. El riesgo no está en el código sino en la precisión de un texto legal público.

✅ **44a cumplida (2026-09-30).**
- Los canales se tomaron el mismo día de la página de denuncias de la Contraloría. Viven en `resources/js/lib/oversight.js`: si cambian, se cambian ahí.
- Esa página dice que la Contraloría General "atiende denuncias fiscales relacionadas con recursos nacionales". Por eso la sección nombra a la contraloría territorial.
- **Prueba:**
  - Vitest: los 6 escenarios, y que una obra que no está en riesgo no habla de "obra inconclusa";
  - `make e2e`: el ciudadano ve la aclaración y los canales en una obra;
  - `make ux-check`: sin retroceso;
  - `make trace-check`: 270 de 270.

**44b — A3: el expediente de una obra** (US-056-LEG, EPIC-008).
- El Administrador descarga, desde la obra, un ZIP con:
  - el expediente en PDF: el contrato de SECOP II y cada evidencia, con su SHA-256, su raíz de Merkle y su transacción en Stellar;
  - las plantillas en PDF, pre-llenadas: el derecho de petición a la entidad (Ley 1755 de 2015) y la denuncia ante la Contraloría (Ley 1757 de 2015, art. 69);
  - los archivos originales, byte a byte, con sus pruebas de inclusión.
- Cada descarga va al log de auditoría: es la telemetría para validar el uso de la función.
- **Modelo:** Opus xhigh. El expediente presenta evidencia criptográfica en un documento legal.

**44c — A4: los impedimentos del veedor** (US-057-LEG, EPIC-006).
- Al activar su cuenta, el veedor declara no estar en los impedimentos del art. 19 de la Ley 850 de 2003.
- El que ya tiene cuenta lo declara antes de su próximo reporte.
- La declaración queda auditada, y el Administrador ve quién la hizo.
- **Modelo:** Opus. Es una condición para reportar.

**44d — A5: una veeduría sin NIT** (enmiendas a US-001 y US-011, EPIC-009).
- El NIT pasa a opcional.
- La alternativa es el número de la resolución o el acta de inscripción y su entidad de registro (personería o cámara de comercio).
- Una organización tiene una de las dos, o ambas.
- **Modelo:** Opus xhigh. Cambia el esquema y la regla que evita suplantaciones (US-001).

**44e — A2: "Informar a esta veeduría"** (US-058-LEG). Va después de 44d.
- El ciudadano, con su correo verificado por un código, informa a la veeduría (Ley 850, art. 18 a)).
- No se sella ni se publica.
- **Modelo:** Opus xhigh. Es un endpoint público, con verificación de correo, spam y datos personales.

**Done-when de la 44:** cada sub-iteración, con sus escenarios en verde y su PR.

## Pivote a Stellar (2026-09-28)

El proyecto participa en **Stellar Apex**, así que la blockchain pasa de EVM/Polygon a **Stellar**, con Smart Contracts en **Soroban (Rust)**:
- La red local es la *standalone* de Stellar CLI en Docker.
- No hay relayer de terceros: el backend patrocina las comisiones con *fee bump* (D4, D5).
- En la SPEC se reescribieron el resumen, las decisiones de arquitectura, R-BLK-01 a 04 (más la nueva R-BLK-06, finalidad sin reorganizaciones), R-CFG-01/02, R-INT-01/04, R-TST-01, R-SA-01, R-TA-02 y R-VC-03.

Qué cambia además, frente al diseño EVM:
- **La hora del sello la pone la red** (hora y número del ledger), no el servidor.
- **"Sellada" no espera confirmaciones:** un ledger cerrado es definitivo.
- **El contrato no se puede actualizar:** no tiene `upgrade`, y esa es la garantía de inmutabilidad en Soroban.
- **La referencia de la obra on-chain es un hash de «organización:ficha»:** el `uint256` de ID de obra se repetía entre organizaciones.

Historias con Gherkin y criterios todavía redactados para Polygon; cada una se ajusta al abrir su iteración:

| Historia | Qué cambia | Se ajusta en |
|---|---|---|
| US-020a | Soroban, cuenta selladora, hora del ledger | ✅ ya ajustada (it. 12) |
| US-008 | el mensaje de éxito dice "…en la red Stellar." | ✅ ya ajustada |
| US-020b | fee bump y XLM en vez de relayer y gas; "Sellada" sin 3 confirmaciones ni auditoría de reorganizaciones (R-BLK-06) | it. 13 |
| US-038-CFG | el umbral de saldo pasa a 50 XLM, `sponsor_balance_alert_threshold_xlm` (D12) | ✅ ya ajustada (it. 21) |
| US-021 | red o RPC de Stellar caídos, o patrocinadora sin saldo, en vez de relayer caído | it. 22 |
| US-023, US-025 | Recibo: TxID, número y hora del ledger, enlace a un explorador de Stellar | it. 23 |
| US-024, US-046-INT | el validador y el script leen el sello del contrato por el RPC de Stellar; cómo leer un sello archivado | it. 23 y 27 |
| US-004, US-022 | comisiones en XLM (no gas en POL), precio de XLM en COP, saldo de la patrocinadora | ✅ ya ajustadas (it. 32) |
| US-003b, US-037 | solo el nombre de la red en los textos | ✅ ya ajustadas (US-037 no nombra la red; US-003b, en la it. 33) |

## Carga real por fase (sin rebalancear, como se decidió)

| Fase | Semanas | Historias | Iteraciones | Casos ejecutables* |
|---|---|---|---|---|
| **P1** | 1-2 | 21 (20 + US-016 adelantada) | **19** (1-19) | 184 |
| **P2** | 3-4 | 22 | **10** (20-29) | 121 |
| **P3** | 5+ | 13 | **6** (30-35) | 57 |
| **Total** | | 56 | **35** | 362 |

\* Filas de `features/*.feature`, contando cada fila de los *Esquemas*.

**Lectura honesta:** la carga no está donde se esperaba.
- **P1 concentra la mitad del trabajo**: 19 de las 35 iteraciones y cerca del 50 % de los casos, en 2 semanas. Incluye además las piezas de mayor riesgo técnico: el Smart Contract, Merkle y el sellado en testnet (it. 12-14). El pivote a Stellar le suma una curva nueva: Rust y Soroban.
- P2 creció por los huecos de Completitud, pero con 10 iteraciones es más manejable que P1.
- P3 cabe en una semana solo si P1 y P2 terminan a tiempo.

Si hay que recortar, estas palancas no rompen ninguna regla:
1. Aceptar P3 como **post-MVP**: el MVP publicable es el fin de P2.
2. Unir las iteraciones de UI de administración (18-19 y 29) en pantallas funcionales mínimas.
3. Mover la iteración 14 (testnet) al inicio de P2, dejando P1 contra la red local de Stellar.

La decisión es tuya: el plan no la toma.

## `/audit` — hallazgos y cierre
<!-- Tras /audit: gaps encontrados, cuáles se cierran como iteraciones nuevas y cuáles se aceptan como deuda. -->

**2026-09-29, sobre `main` en `527c3f4`.** El reporte completo, regla por regla, está en `specs/AUDIT.md`.

- **Cobertura técnica:**
  - Pest 683; Vitest 350;
  - contrato 6; red local 11 + 1; E2E 1; respaldos 8 comprobaciones; prueba de humo en testnet en verde;
  - los 7 servicios en `healthy`;
  - un commit por iteración, de la 1 a la 35.
  - Había **un test intermitente** (`SuperAdminAuthorizationTest`, dependía del segundo en que corría): se arregló congelando el reloj.
- **Cobertura funcional:**
  - los 249 escenarios tienen su test; 66, de las it. 2 a 13 y 20, sin el nombre del escenario;
  - de las 66 reglas, **58 cubiertas** con código y test negativo, y **8 parciales**: R-VC-01, R-PRIV-03, R-CFG-01, R-AUD-04, R-MNT-03, R-BCK-02, R-BCK-05 y R-TST-04.
- **Stubs y fakes:** ninguno en producción. Queda el texto provisional de la página del dominio central.
- **Se cierran con iteraciones nuevas:**
  - la 36: log de auditoría completo e inmutable, test negativo de R-VC-01, alta atómica y nombres de los escenarios;
  - la 37: red principal, firma remota, respaldos fuera del sitio y la restauración de prueba en producción.
- **✅ Aprobada por el usuario (2026-09-29) y aplicada en la SPEC:** la enmienda de R-PRIV-03. Se propuso así: enmendar R-PRIV-03 a *"el ID del veedor se reemplaza por un seudónimo antes de sellar; **se publica el hash** de ese JSON, no el JSON, que trae la ubicación exacta (R-PRIV-02)"*. Así la regla dice lo que ya hace el código, y lo que aprobó el usuario el 2026-09-29, del lado de la privacidad.
- **Recomendación:** listo para PR. No listo para producción hasta cerrar las it. 36 y 37.
- **El usuario aprobó el 2026-09-29 las iteraciones 36 y 37 y la deuda técnica aceptada,** y resolvió la red principal (D13).

## Deuda técnica aceptada
<!-- Hallazgos que se dejan conscientemente, con la razón y qué haría falta para retomarlos. -->

No bloquean ningún criterio de aceptación. **Aceptada por el usuario el 2026-09-29.**

| Deuda | Por qué se acepta | Qué haría falta |
|---|---|---|
| ✅ *Cerrada en la it. 41:* `APP_LOCALE=en`: los mensajes por defecto de Laravel (`required`, `email`) salen en inglés si alguien se salta la pantalla | Las pantallas validan antes, en español (aceptada el 2026-09-28) | Traducir `lang/es` |
| El calendario corre en UTC: la sincronización de las 02:00 son las 21:00 en Colombia | Cada tarea dice su hora en Colombia en `routes/console.php` | `->timezone('America/Bogota')` en cada tarea, o `schedule_timezone` |
| ✅ *Cerrada en la it. 42a:* repetir `make setup` sobre un stack que ya corre puede fallar en `up --wait`: el proxy se marca enfermo mientras la app reinicia | Jenkins parte de cero; para un stack existente basta `make up` | Más paciencia en el healthcheck del proxy |
| El nombre de un veedor invitado es la parte local de su correo | Ninguna historia pide el nombre; todo lo público usa el seudónimo | Una historia de perfil del veedor |
| ✅ *Cerrada en la it. 38:* `make setup` no siembra la DIVIPOLA en desarrollo (la E2E la siembra sola) | Solo afecta a configurar territorios en una base de desarrollo nueva | `db:seed --class=DivipolaSeeder` en `make setup`; para producción entra en la it. 37 |
| ✅ *Cerrada en la it. 39:* varios sellos enviados a la vez: la red acepta uno y rechaza los demás con `txINSUFFICIENT_FEE`; se reintentan a 1, 5 y 15 min (hallazgo de la it. 38) | Un veedor envía un reporte a la vez; solo se nota con varios reportes en el mismo segundo, y todos terminan sellados | Averiguar por qué (la comisión del *fee bump* con varias transacciones en el mismo ledger) y, si hace falta, subirla o reintentar pronto; antes de una salida con muchos veedores |
