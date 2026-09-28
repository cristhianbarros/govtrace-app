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

✅ Confirmadas D1 a D10. D4 y D5 se reformularon por el **pivote a Stellar** (2026-09-28, ver la sección "Pivote a Stellar"). D11 y D12 quedaron resueltas el 2026-09-28 (secretos inyectados, patrocinadora como *hot wallet*, umbral de 50 XLM y la tesorería paga la vigencia del contrato). No quedan decisiones técnicas abiertas.

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
| D11 | Custodia de las llaves (R-BLK-04) | ✅ **Resuelta (2026-09-28).** Para el MVP y testnet, **(a)**: las llaves de la selladora y la patrocinadora se inyectan como variables de entorno al arrancar, desde un gestor de secretos (en CI, las credenciales de Jenkins); nunca en el repositorio, su historial ni `.env.example`. La patrocinadora es una **hot wallet**: tiene el saldo de unos días de sellos y la recarga seguido una cuenta de **tesorería** fría, que no vive en el servidor. La selladora no tiene fondos. **Mejora futura, antes de la red principal: (b)** firma remota Ed25519 sin que la llave salga del servicio (p. ej. HashiCorp Vault Transit), detrás de la misma interfaz `SealingNetwork`. | it. 14 |
| D12 | Umbral de saldo de la cuenta patrocinadora (US-021, US-022, US-038-CFG) | ✅ **Resuelta (2026-09-28).** Umbral de alerta de **50 XLM** (`sponsor_balance_alert_threshold_xlm`, ~200 sellos de 0,2425 XLM); reemplaza al parámetro en POL de la it. 6. La **tesorería** paga el despliegue y la extensión de la vigencia de la instancia y del código del contrato, así la hot wallet solo paga sellos. Se mantiene la vigencia máxima por sello (se revisa en la it. 23). Detalle en la it. 14. | it. 21 |
| D10 | Proximidad (US-019) | Haversine en SQL, sin PostGIS | it. 31 |

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
- **Observación:** `APP_LOCALE=en`. Los mensajes por defecto de Laravel (`required`, `email`, `confirmed`) salen en inglés si alguien se salta la pantalla; las pantallas validan antes, en español. Traducir `lang/es` queda para cuando se toque la configuración regional.

### Iteración 18 — Panel del Administrador de Organización (P1)
**Entregable:**
- bandeja de entrada (publicar, rechazar, retirar);
- invitar veedores;
- territorio con buscador;
- contratos;
- corrección de ubicación en mapa Leaflet/OSM (D8).

**Done-when:** Vitest de cada pantalla con sus estados (carga, error, vacío, éxito) y los mensajes de US-036, US-037, US-005, US-012, US-015 y US-035 en verde.
**Cubre:** US-005, US-012, US-015, US-035, US-036, US-037 (UI).

### Iteración 19 — Panel global del Super Administrador (P1)
**Entregable:** alta de organización, Administrador inicial y datos legales.
**Done-when:** Vitest de los formularios de US-001, US-002 y US-011 en verde, con sus mensajes de error.
**Cubre:** US-001, US-002, US-011 (UI).

---

## Fase P2 — Semanas 3-4 · Verificación pública y operación

### Iteración 20 — Ciclo de vida de cuentas y organizaciones
**Entregable:**
- suspender y reactivar organizaciones, con el mapa visible y aviso, y los reportes offline conservados; **reactivar despacha `SyncSecopContracts::dispatch($tenant->id)`** y cierra el `todo` de US-013 que quedó en `SyncSecopContractsTest` (it. 7);
- desactivar y reactivar veedores;
- restablecer contraseña.

**Done-when:** US-003a (6 casos), US-006 (3), US-041-USR (2) y US-039-USR (5) en verde.
**Cubre:** US-003a, US-006, US-039-USR, US-041-USR · R-AUD-01, R-USR-03, R-VC-01.

### Iteración 21 — Perfil, parámetros y consulta de auditoría
**Entregable:**
- nombre y logo de la organización, con limpieza de SVG;
- pantalla de parámetros globales (usa el historial de la it. 6);
- consulta del log de auditoría por alcance.

**Done-when:** US-007 (15 casos), US-038-CFG (10) y US-043-MON (3) en verde.
**Cubre:** US-007, US-038-CFG, US-043-MON · R-SEC-04, R-VC-03, R-CFG-02, R-AUD-04 (consulta).

### Iteración 22 — Robustez del sellado y monitoreo
**Entregable:**
- reintentos con backoff hasta 5, "Falla de Sellado" con banner, alerta de cola estancada a las 2 h y red de Stellar (RPC) caída o patrocinadora sin saldo (US-021 se ajusta a Stellar al abrir la iteración);
- panel de salud de SECOP;
- monitoreo externo de caídas de más de 5 minutos, por Email y Webhook.

**Done-when:**
- US-021 (5 casos) y US-014 (3) en verde;
- US-044-MON (2 casos) verificado con la configuración de la herramienta externa y un test del receptor de webhook.

**Cubre:** US-014, US-021, US-044-MON · R-INT-01, R-MON-01.

### Iteración 23 — Recibos, descarga con prueba y script independiente
**Entregable:**
- Recibo de Inmutabilidad privado y público;
- descarga del archivo exacto con su prueba de inclusión;
- script de verificación en `tools/verify/`, que consulta todas las direcciones históricas del contrato.

**Done-when:** US-023 (3 casos), US-025 (2), US-026 (3) y US-046-INT (4) en verde. El script se prueba contra la red local *standalone* con los vectores de la it. 13. US-023, US-025 y US-046-INT se ajustan a Stellar al abrir la iteración. El recibo lleva TxID, número y hora del ledger y el enlace a un explorador de Stellar (hay que elegir cuál). Hay que decidir cómo se lee un sello archivado por TTL.
**Cubre:** US-023, US-025, US-026, US-046-INT · R-INT-04, R-MNT-01, R-MNT-02.

### Iteración 24 — Mapa y línea de tiempo (datos)
**Entregable:**
- pines livianos con reglas de color (evidencia publicada más reciente, peor estado de la ficha, Terminados en ventana);
- línea de tiempo bajo demanda con coordenadas aproximadas;
- agrupación de contratos en una ficha.

**Done-when:** US-027 (12 casos), US-029 (5) y US-045-INT (3) en verde.
**Cubre:** US-027, US-029, US-045-INT · R-MAP-01, R-MAP-02, R-PRIV-02, R-INT-05.

### Iteración 25 — Autorización al Super Admin, archivado y resumen
**Entregable:** autorización de 30 días, revocable; archivado mensual con retorno si llega evidencia; resumen del territorio.
**Done-when:** US-042-SEC (5 casos), US-048-MNT (4) y US-049-RPT (2) en verde.
**Cubre:** US-042-SEC, US-048-MNT, US-049-RPT · R-SA-02, R-MNT-04.

### Iteración 26 — Sitio público: mapa, vista de obra y línea de tiempo (la pantalla pública central)
**Entregable:** mapa Leaflet/OSM con pines por color; vista de obra con la tarjeta del contrato y la línea de tiempo (visor, lápidas, botón "Verificar Sello Blockchain"); aviso de organización suspendida.
**Done-when:** Vitest de los estados de US-027, US-029 y US-017 en verde (sin obras, carga, lápida, badge de anulado).
**Cubre:** US-017, US-027, US-029 (UI) · R-AUD-01 (UI).

### Iteración 27 — Validador público, recibo y descarga
**Entregable:** validador con tres modos (contextual, libre y con prueba adjunta); hash y recomposición de Merkle en el navegador; recibo público; botón de descarga.
**Done-when:**
- US-024 (14 casos) en verde con Vitest, usando los vectores de Merkle de la it. 13 y un Smart Contract simulado;
- UI de US-025 y US-026 en verde.

**Cubre:** US-024, US-025, US-026 (UI) · R-VER-01, R-VER-02, R-MNT-01.

### Iteración 28 — Veedor: Mis Reportes y recibo
**Entregable:** lista con estado técnico y editorial por separado, rechazo con motivo y recibo en la app.
**Done-when:** US-010 (10 casos) en verde (backend y Vitest), más la UI de US-023.
**Cubre:** US-010, US-023 (UI) · R-VC-02, R-USR-02.

### Iteración 29 — Paneles de P2 (administrador y Super Admin)
**Entregable:** pantallas de US-003a, US-006, US-007, US-014, US-038-CFG, US-039-USR, US-041-USR, US-042-SEC, US-043-MON, US-045-INT y US-049-RPT, más los banners de US-021.
**Done-when:** Vitest de cada pantalla con sus estados y mensajes exactos en verde.
**Cubre:** UI de las historias de P2 listadas.

---

## Fase P3 — Semana 5 o posterior · Resiliencia de campo y reportes

### Iteración 30 — Modo sin conexión
**Entregable:** Service Worker e IndexedDB; bandeja de salida con límites (10 reportes / 50 MB) y vigencia de 7 días con aviso a las 24 h; modal al cerrar sesión; aceptación de un contrato anulado mientras esperaba.
**Done-when:** US-018 (14 casos) en verde: Vitest y **pruebas de extremo a extremo en un navegador real simulando pérdida de señal** (R-TST-03).
**Cubre:** US-018 · R-USR-03, R-TST-03.

### Iteración 31 — Obras cercanas y filtros del mapa
**Entregable:** sugerencia de hasta 5 obras a menos de 500 m con Haversine (D10), y filtros de estado, fechas, presupuesto y municipio.
**Done-when:** US-019 (7 casos) y US-028 (3) en verde.
**Cubre:** US-019, US-028.

### Iteración 32 — Operación de la cuenta patrocinadora y costos
**Entregable:** saldo en XLM de la patrocinadora cada 15 minutos, con alerta bajo el umbral de D12 (50 XLM); aviso antes de que venza la vigencia de la instancia o del código del contrato, para que la tesorería corra la extensión (D12); reporte de comisiones (XLM y COP) por mes y organización, con respaldo del último precio conocido de XLM; re-encolado de fallas de sellado. US-004 y US-022 se ajustan a Stellar al abrir la iteración.
**Done-when:** US-022 (4 casos), US-004 (4) y US-047-MNT (2) en verde.
**Cubre:** US-004, US-022, US-047-MNT · R-VC-03.

### Iteración 33 — Baja de organizaciones e invitaciones
**Entregable:** baja con doble confirmación, mapa fuera de línea y evidencias verificables; retención de 5 años de los archivos; reenviar y revocar invitaciones.
**Done-when:** US-003b (6 casos) y US-040-USR (2) en verde.
**Cubre:** US-003b, US-040-USR · R-AUD-02, R-AUD-03.

### Iteración 34 — Reportes y datos abiertos
**Entregable:** exportación CSV de la organización; estadísticas públicas; datos abiertos en CSV y JSON; resumen de uso; alerta de inactividad.
**Done-when:** US-050-RPT (3 casos), US-051-RPT (2), US-052-RPT (4), US-053-RPT (2) y US-054-RPT (4) en verde.
**Cubre:** US-050..054-RPT · R-PRIV-02, R-PRIV-03 (en los datos abiertos).

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

---

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
| US-038-CFG | el umbral de saldo pasa a 50 XLM, `sponsor_balance_alert_threshold_xlm` (D12 ✅, ya sembrado) | it. 21 |
| US-021 | red o RPC de Stellar caídos, o patrocinadora sin saldo, en vez de relayer caído | it. 22 |
| US-023, US-025 | Recibo: TxID, número y hora del ledger, enlace a un explorador de Stellar | it. 23 |
| US-024, US-046-INT | el validador y el script leen el sello del contrato por el RPC de Stellar; cómo leer un sello archivado | it. 23 y 27 |
| US-004, US-022 | comisiones en XLM (no gas en POL), precio de XLM en COP, saldo de la patrocinadora | it. 32 |
| US-003b, US-037 | solo el nombre de la red en los textos | al abrir su iteración |

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

## Deuda técnica aceptada
<!-- Hallazgos que se dejan conscientemente, con la razón y qué haría falta para retomarlos. -->
