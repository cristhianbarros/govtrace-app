# Auditoría de implementación — GovTrace MVP

**Fecha:** 2026-09-29, sobre `main` en `527c3f4` (iteración 35). Agente `calidad`, comando `/audit`.
**Alcance:** las 66 reglas de `specs/SPEC.md`, los 249 escenarios de `features/`, las suites de pruebas, el entorno Docker y el historial.

## Veredicto

**Listo para PR: sí.** Todas las suites están en verde, el stack levanta sano y hay un commit identificable por iteración. De las 66 reglas, **58 están cubiertas con código y test**, incluido el caso negativo. **8 están parciales**, y ninguna rompe lo ya construido.

**Listo para producción: todavía no.** Faltan dos cosas:
- **Completar el log de auditoría (R-AUD-04, R-MNT-03):** hoy no registra el alta de una organización, la asignación de su Administrador inicial ni la invitación de un veedor, y ninguna guarda impide editar o borrar una entrada.
- **Preparar la red principal de Stellar (R-CFG-01):** no hay un procedimiento de despliegue en la red principal ni un contrato registrado para ella. A esto se suman los estándares de go-live que definiste: respaldos fuera del sitio y el simulacro de restauración en producción.

Se proponen como las iteraciones 36 y 37 de `specs/PLAN.md`.

Durante la auditoría apareció **un test intermitente**, arreglado en esta misma rama: `SuperAdminAuthorizationTest › shows the Administrador the authorization in force` comparaba una vigencia calculada al autorizar con otra recalculada al verificar, y fallaba si entre las dos cambiaba el segundo. Rompía el hook de pre-commit al azar. Ahora congela el reloj, y pasó 3 de 3 veces.

## Cobertura técnica

| Qué | Resultado |
|---|---|
| `./vendor/bin/pest` (`make test`) | **683 tests.** En la corrida de la auditoría, 682 en verde y 1 intermitente, ya arreglado. |
| `npm run test` (`make test-front`) | **350 en verde**, en 48 archivos |
| `cargo test` del contrato (`make contract-test`) | 6 en verde, con rustfmt, clippy y la interfaz exacta del WASM |
| Contra la red local de Stellar (`make test-stellar`, `make verify-check`) | 11 + 1 en verde |
| Navegador real (`make e2e`) | 1 en verde |
| Respaldos (`make backup-check`) | 8 comprobaciones en verde, restauración de prueba incluida |
| Prueba de humo en testnet (`make smoke-testnet`) | En verde: un reporte llegó a "Sellada" en la testnet real (it. 35) |
| `make up` y `make ps` | **7 servicios base en `healthy`**: app, proxy, pgsql, scheduler, storage, worker y backup. `/up` responde 200 (`tests/infra/verify-stack.sh`). |
| Pipeline | Las etapas del `Jenkinsfile` corrieron en verde en un proyecto aislado, con el entorno de Jenkins y desde un `make setup` de cero (it. 35) |
| Historial | **Un commit `feat: iteración N — …` por cada iteración, de la 1 a la 35.** Además hay 5 `fix:` (VPN, estados de SECOP, D12, EXIF en PDF, redirección del veedor) y los commits de docs del plan y del pivote. |

### Stubs, fakes y `TODO` en código de producción

- **Ningún stub ni fake en producción.** El sellado usa la implementación real, `StellarSealingNetwork`. `FakeSealingNetwork` vive en `tests/Support/` y en `app/` solo se menciona en un comentario.
- **Ningún `TODO` ni `FIXME`.**
- **Texto provisional visible:** la página del dominio central (`resources/js/Pages/Home.vue`) dice "GovTrace está listo para construirse.".
- **Comentarios desactualizados:**
  - "Placeholders hasta que it. 18/it. 19 construyan los paneles reales." en `routes/tenant.php:112`, y su equivalente en `app/Domain/Organization/RoleBasedDashboard.php:9`: los paneles ya existen;
  - "El panel del veedor es "Nuevo Reporte" hasta "Mis Reportes" (it. 28)": que el veedor entre a "Nuevo Reporte" quedó decidido en el fix #19.
- **Marcador de dato conocido:** el nombre de un veedor invitado es la parte local de su correo (`InviteObserver`), porque ninguna historia lo pide.

## Cobertura funcional

### Escenarios

Los **249 escenarios tienen su test** (362 casos contando las filas de los esquemas). Revisé uno por uno los 66 cuyos tests no llevan el nombre del escenario, y todos cubren lo que el escenario pide. Siguen sin llevar el nombre porque son de las iteraciones 2 a 13 y 20, antes de que la convención de CLAUDE.md ("todo Scenario tiene un test nombrado por el escenario") se aplicara de forma sistemática. Por historia:

| Historia | Escenarios sin su nombre en el test | Dónde están cubiertos |
|---|---|---|
| US-001 | 6 | `RegisterOrganizationTest` |
| US-002 | 2 | `AssignInitialAdministratorTest` |
| US-005 | 4 | `InviteObserverTest` |
| US-008 | 7 | `CreateReportTest`, `FirstTouchRaceTest` |
| US-009 | 4 | `ReportEvidenceTest`, `lib/evidence/*.test.js` |
| US-011 | 4 | `UpdateOrganizationLegalDataTest` |
| US-012 | 2 | `ConfigureTerritoryTest` |
| US-013 | 7 | `SyncSecopContractsTest`, `ProcessSecopContractRowTest`, `MunicipalityMatcherTest` |
| US-015, US-016 | 3 | `ListTerritoryContractsTest`, `SearchSelectableContractsTest` |
| US-020b | 7 | `SealReportTest` |
| US-031 | 2 | `LoginTest` |
| US-032, US-033 | 5 | `ProcessSecopContractRowTest` |
| US-034, US-035 | 4 | `CalculateWorksitesAtRiskTest`, `CorrectWorksiteLocationTest` |
| US-036, US-037 | 7 | `ReviewInboxTest`, `WithdrawEvidenceTest` |
| US-044-MON | 2 | `tests/infra/check-monitoring.sh`, por secciones |

### Reglas

✅ código y test, con caso negativo · ⚠️ parcial

| Regla | Estado | Evidencia (código → test) |
|---|---|---|
| R-SA-01 | ✅ | Guardas de inmutabilidad de contratos, reportes y sellos para cualquier actor → `ProcessSecopContractRowTest` (edición y borrado rechazados), `WithdrawEvidenceTest`, test Rust `nadie_puede_modificar_ni_borrar_un_sello_registrado`, `DecommissionOrganizationTest` (la baja no toca la cadena) |
| R-SA-02 | ✅ | `SuperAdminAuthorizations`, `CreateReportOnBehalf` → `SuperAdminAuthorizationTest` (sin autorización, rechazado) |
| R-SA-03 | ✅ | `RegisterOrganization` → `RegisterOrganizationTest` (subdominio ocupado, mal formado o reservado; nunca cambia) |
| R-TA-01 | ✅ | → `InviteObserverTest` (desde una organización no se registra otra) |
| R-TA-02 | ✅ | `EvidenceIsImmutable` → `WithdrawEvidenceTest` |
| R-TA-03 | ✅ | → `UpdateOrganizationLegalDataTest`, `OrganizationProfileTest` |
| R-USR-01 | ✅ | → `InviteObserverTest` (el mismo correo en otra organización; duplicado en la misma, rechazado) |
| R-USR-02 | ✅ | → `ReviewInboxTest` (rechazo sin motivo, rechazado), `MyReportsTest` |
| R-USR-03 | ✅ | → `SuspendOrganizationTest` (reactivada dentro de los 7 días), `outbox.test.js` |
| R-VC-01 | ⚠️ | Rutas protegidas por rol → `AdminPanelTest`, `DeactivateObserverTest`, `ManageInvitationsTest`, `ReviewInboxTest`. **Ningún test prueba que un veedor no puede invitar** (`POST /observers/invite`). |
| R-VC-02 | ✅ | → `MyReportsTest` ("Solo veo mis propios reportes"), `SealReceiptTest` (recibo privado solo para su veedor) |
| R-VC-03 | ✅ | → `SealingCostsTest` (el veedor, rechazado), `OrganizationProfileTest` |
| R-VC-04 | ✅ | → `CreateReportTest`, `SearchSelectableContractsTest`, `NearbyWorksitesTest` |
| R-SEC-01 | ✅ | `Contract::fromSecop` → `ProcessSecopContractRowTest`, `CalculateWorksitesAtRiskTest` |
| R-SEC-02 | ✅ | → `SyncSecopContractsTest` |
| R-SEC-07 | ✅ | → `ProcessSecopContractRowTest`, `SearchSelectableContractsTest`, `CreateReportTest` |
| R-GEO-01 | ✅ | → `CreateReportTest` (First-Touch, sin ubicación), `FirstTouchRaceTest` |
| R-HASH-01 | ✅ | → `prepare.test.js`, `ReportEvidenceTest` (archivo alterado, no se encola) |
| R-BLK-01 | ✅ | → `SealReportTest` (nunca pide cuenta, billetera ni XLM) |
| R-BLK-02 | ✅ | Contrato Soroban → tests Rust, `SealReportTest` |
| R-BLK-03 | ✅ | `require_auth` → test Rust `una_cuenta_que_no_es_la_selladora_no_puede_sellar`, `make contract-smoke` |
| R-BLK-04 | ✅ | → `SealReportTest`, `StellarSealingNetworkTest` (fee bump), `check-secrets.sh` |
| R-BLK-05 | ✅ | → `SealReportTest`, `MerkleTreeTest`, `ValidatorDataTest` |
| R-BLK-06 | ✅ | → `SealReportTest` (sigue "Transmitiendo" sin ledger cerrado) |
| R-VER-01 | ✅ | → `lib/validator.test.js`, `Validator.test.js` |
| R-VER-02 | ✅ | → `ValidatorDataTest`, `PublicPagesTest`, `EvidenceDownloadTest`, `OpenDataTest`, `PublicStatsTest` |
| R-PRIV-01 | ✅ | → `photos.test.js`, `prepare.test.js` |
| R-PRIV-02 | ✅ | `GeoPoint::approximate` → `PublicMapTest`, `WorksiteViewTest`, `OpenDataTest` (nada con más de 3 decimales) |
| R-PRIV-03 | ⚠️ | Seudónimo en el JSON sellado → `SealReportTest`, `OpenDataTest`, `ExportCsvTest`, `PseudonymRetentionTest`. **El texto de la regla dice "ese JSON se publica"**, y ese JSON trae la ubicación exacta (contra R-PRIV-02). Se publica solo su hash, como aprobaste. Falta enmendar el texto. |
| R-PRIV-04 | ✅ | → `pdf.test.js` |
| R-PRIV-05 | ✅ | → `ReportEvidenceTest` (la foto byte a byte) |
| R-PRIV-06 | ✅ | → `photos.test.js` (1920 px, JPEG al 80 %) |
| R-MAP-01 | ✅ | → `PublicMapTest` |
| R-MAP-02 | ✅ | → `PublicMapTest`, `WorksiteViewTest` |
| R-SEC-03 | ✅ | → `LoginTest` (bloqueo de 15 min tras 5 intentos) |
| R-SEC-04 | ✅ | `SvgSanitizer` → `OrganizationProfileTest` |
| R-SEC-05 | ✅ | → `CreateReportTest`, `ReviewInboxTest` |
| R-SEC-06 | ✅ | → `SealReportTest` (ignora la raíz del teléfono) |
| R-CFG-01 | ⚠️ | Red local (`make test-stellar`) y testnet (`TestnetSmokeTest`) en verde. **Nada preparado para la red principal:** ni script de despliegue y extensión, ni RPC público elegido, ni su contrato en `tools/verify/contracts.json`, ni plantilla de entorno de producción. |
| R-CFG-02 | ✅ | → `ParametersTest`, `ParametersPanelTest` (los fijos no se configuran) |
| R-AUD-01 | ✅ | → `SuspendOrganizationTest` |
| R-AUD-02 | ✅ | `EnsureMapIsOnline` → `DecommissionOrganizationTest` |
| R-AUD-03 | ✅ | `PurgeDecommissionedEvidence` → `DecommissionOrganizationTest` (4 años y 11 meses, y 5 años y 1 día) |
| R-AUD-04 | ⚠️ | Registradas y probadas: suspender, reactivar, dar de baja; NIT; publicar, rechazar y retirar; ubicación; parámetros; desactivar y reactivar; reenviar y revocar invitaciones; territorio; autorizaciones. **No se registran el alta de una organización, la asignación de su Administrador inicial ni la invitación de un veedor.** |
| R-AUD-05 | ✅ | → `CreateReportTest` (el radio vigente al capturar) |
| R-AUD-06 | ✅ | → `SyncSecopContractsTest` |
| R-MON-01 | ✅ | `CheckSealingQueue` → `SealingRetriesTest` |
| R-MON-02 | ✅ | → `ReviewInboxTest` |
| R-INT-01 | ✅ | → `SealingRetriesTest`, `SealReportTest` (pausa sin saldo) |
| R-INT-02 | ✅ | → `PinsMap.test.js` |
| R-INT-03 | ✅ | → `SealingCostsTest` (último precio con su fecha), `ProcessSecopContractRowTest`, `SecopHealthTest` |
| R-INT-04 | ✅ | → `EvidenceDownloadTest`, `IndependentVerificationTest`, `tools/verify` |
| R-INT-05 | ✅ | → `GroupContractsTest`, `CreateReportTest` |
| R-MNT-01 | ✅ | `tools/verify/contracts.json` → tests de `tools/verify` |
| R-MNT-02 | ✅ | → `DecommissionOrganizationTest` (prueba verificable tras la purga) |
| R-MNT-03 | ⚠️ | Seudónimos a los 5 años → `PseudonymRetentionTest`. **El log de auditoría no tiene una guarda contra la edición o el borrado**, como sí la tienen reportes y sellos, ni un test que lo pruebe. |
| R-MNT-04 | ✅ | → `ContractArchiveTest` |
| R-BCK-01 | ✅ | `backup` cada hora → `restore-drill.sh` (falla si la copia tiene 1 h o más), `check-backup.sh` |
| R-BCK-02 | ⚠️ | La restauración de prueba **mide** la recuperación (3 s), pero **no falla** si pasa de 4 h |
| R-BCK-03 | ✅ | → `check-backup.sh`, `restore-drill.sh` (SHA-256 de cada evidencia) |
| R-BCK-04 | ✅ | → `check-backup.sh` (una copia de 31 días se borra) |
| R-BCK-05 | ⚠️ | Hecha en desarrollo (`docs/restore.md`) y en cada ejecución del pipeline. **Falta la de producción**, que exige salir con datos reales. |
| R-TST-01 | ✅ | Etapas Test Contract, Test Stellar y Smoke Testnet del `Jenkinsfile` |
| R-TST-02 | ✅ | `tests/fixtures/secop` → `SecopClientTest`, `ProcessSecopContractRowTest`, `MunicipalityMatcherTest` |
| R-TST-03 | ✅ | `tests/e2e/offline.spec.js`, `outbox.test.js` |
| R-TST-04 | ⚠️ | Cada regla nueva desde la it. 22 se comprobó rompiéndola a propósito. Falta el caso negativo de R-VC-01, y 66 tests no llevan el nombre de su escenario. |

## Gaps por severidad

**Alta (bloquea el PR):** ninguna abierta. El test intermitente se arregló en esta rama.

**Media (una regla de la SPEC sin cumplir del todo, o bloquea la salida a producción):**
1. **R-AUD-04:** faltan en el log el alta de una organización, la asignación del Administrador inicial y la invitación de un veedor.
2. **R-MNT-03:** el log de auditoría no es inmutable ni hay un test que lo pruebe.
3. **R-CFG-01:** nada preparado para la red principal de Stellar. D11 dejó para antes de la red principal la firma remota (opción b).
4. **Estándares de go-live que definiste:** respaldos fuera del sitio, automatizados, y la restauración de prueba en producción (R-BCK-05).

**Baja:**
5. R-VC-01: falta el test negativo de un veedor que intenta invitar.
6. R-BCK-02: la restauración de prueba no falla si la recuperación pasa de 4 h.
7. R-TST-04 y trazabilidad: 66 tests sin el nombre de su escenario.
8. El texto provisional en la página del dominio central, y los comentarios desactualizados.
9. `RegisterOrganization` deja una organización a medio crear si falla a mitad de camino. Ya estaba anotado.
10. R-PRIV-03: enmendar el texto de la regla ("su hash se publica"). Es una decisión de spec, no de código.

## Propuesta

- **Iteración 36, "Trazabilidad y log de auditoría completos":** cierra los gaps 1, 2, 5, 7, 8 y 9. Bloquea producción, no el PR.
- **Iteración 37, "Salida a la red principal":** cierra los gaps 3, 4 y 6, y la lista de go-live. Bloquea producción.
- **Deuda técnica aceptada:** lo que no bloquea ningún criterio (ver `specs/PLAN.md`).
- **Decisión de spec pendiente:** el texto de R-PRIV-03 (gap 10).

## Seguimiento

- **2026-09-29, decisiones:** el usuario aprobó la enmienda de R-PRIV-03, ya aplicada en la SPEC; las iteraciones 36 y 37; la deuda técnica aceptada, y la red principal (D13 en `specs/PLAN.md`).
- **2026-09-29, iteración 36:** quedan cerrados los gaps 1, 2, 5, 7, 8 y 9, y el 10 con la enmienda. R-AUD-04, R-MNT-03, R-VC-01, R-PRIV-03 y R-TST-04 pasan a ✅. Siguen abiertos R-CFG-01, R-BCK-02 y R-BCK-05, que son de la iteración 37.
