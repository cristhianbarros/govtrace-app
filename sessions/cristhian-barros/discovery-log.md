# Bitácora del discovery — GovTrace

| Fecha | Fase | Qué se cubrió |
|---|---|---|
| 2026-09-26 | Arranque | Caso aportado (pegado en el chat) y guardado en `caso.md`. Sesión creada para Cristhian Barros. |
| 2026-09-26 | Épicas | Nombre confirmado. Inicio de fase Épicas: P1 criterio de agrupación. |
| 2026-09-26 | Épicas | P1: agrupación por flujo (5 épicas candidatas). El usuario pregunta por la gestión de cuentas → se aborda como P2. |
| 2026-09-26 | Épicas | P1 y P2 confirmados: 6 épicas (5 por flujo + EPIC-006 cuentas y roles). Ideas por explorar: alcance geográfico del veedor, radicación de denuncias. |
| 2026-09-26 | Épicas | P3: +3 épicas (moderación, denuncias, tenants) → 9 épicas. Se abre iteración sobre el boundary del tenant (ciudad / departamento / ONG). |
| 2026-09-26 | Épicas | P4 confirmado: tenant = Organización; contratos SECOP centrales. Lista cerrada en 9 épicas. Inicia puntuación VUIFED. |
| 2026-09-26 | Épicas | EPIC-001 puntuada y confirmada (57 / 9.5). Creado specs/epicas/EPIC-001.md. |
| 2026-09-26 | Épicas | EPIC-002 confirmada (55 / 9.17; Dependencias 10→5 por nota asesor). Creado specs/epicas/EPIC-002.md. |
| 2026-09-26 | Épicas | EPIC-003 confirmada (47 / 7.83). Creado specs/epicas/EPIC-003.md. |
| 2026-09-26 | Épicas | EPIC-004 confirmada (49 / 8.17; Dependencias 7→5 por nota asesor). Creado specs/epicas/EPIC-004.md. |
| 2026-09-26 | Épicas | EPIC-005 confirmada (49 / 8.17). Creado specs/epicas/EPIC-005.md. |
| 2026-09-26 | Épicas | EPIC-006 confirmada (52 / 8.67; Esfuerzo 10→5 por nota asesor). Creado specs/epicas/EPIC-006.md. |
| 2026-09-26 | Épicas | EPIC-007 confirmada (46 / 7.67; Dependencias 10→5 por nota asesor). Creado specs/epicas/EPIC-007.md. |
| 2026-09-26 | Épicas | EPIC-008 confirmada (49 / 8.17). Creado specs/epicas/EPIC-008.md. |
| 2026-09-26 | Épicas | EPIC-009 confirmada (54 / 9.0). Creado specs/epicas/EPIC-009.md. Las 9 épicas puntuadas → decisión de MVP. |
| 2026-09-26 | Épicas | MVP confirmado (D): 7 épicas; fuera 007 y 008 con justificación. Nota asesor aceptada: retiro por revocación lógica, no borrado en BD. mvp_included escrito en specs/epicas/. Checkpoint de fase. |
| 2026-09-26 | Historias | Checkpoint de Épicas aceptado. El usuario define 4 actores (nuevo: Super Administrador; el Verificador público no se registra). P1: orden de descomposición. |
| 2026-09-26 | Historias | EPIC-009: 5 historias (US-001, 002, 003a, 003b, 004) + 2 restricciones del Super Admin. Pendiente: aclarar "organización aprobada" (US-002). |
| 2026-09-26 | Historias | EPIC-009 cerrada en borrador (5 historias INVEST ok): alta = aprobación. Inicia EPIC-006. |
| 2026-09-27 | Historias | EPIC-006: 2 roles de tenant, US-005..US-010 ubicadas por épica. Nota asesor aceptada: NIT no editable por el tenant → US-007 ajustada, nueva US-011, regla R-TA-03. INVEST de este bloque. |
| 2026-09-27 | Historias | US-012 (territorio de la organización) confirmada; nace R-VC-04 (veedor solo reporta obras del territorio). Bloque EPIC-006 cerrado. Inicia EPIC-001. |
| 2026-09-27 | Historias | EPIC-001: US-013..US-017 + R-SEC-01/02 confirmadas. Nota asesor (SECOP sin coordenadas) → decisión First-Touch Anchoring (R-GEO-01). Se revisa conflicto con R-SEC-01. |
| 2026-09-27 | Historias | EPIC-002 cerrada en borrador (6 historias). Nota asesor aceptada: hash SHA-256 calculado en el teléfono al capturar (R-HASH-01). Inicia EPIC-003. |
| 2026-09-27 | Historias | EPIC-003: US-020..US-023 + R-BLK-01..04 confirmadas (AccessControl on-chain y custodia KMS de la llave). Inicia EPIC-005. |
| 2026-09-27 | Historias | EPIC-005: US-024..US-026 + R-VER-01/02. Nota asesor aceptada: sanitización EXIF en origen antes del hash (R-PRIV-01). Pendiente: EPIC-004 y ranking antes de Criterios. |
| 2026-09-27 | Historias | EPIC-004: US-027..US-029 + R-MAP-01/02 confirmadas. Publicación sin moderación → a Criterios. El usuario aporta un documento externo con numeración distinta → se pide definir fuente de verdad. |
| 2026-09-27 | Historias | Fuente de verdad = sesión. Candidatas integradas: US-030..US-034; C6 absorbida en US-001 (+R-SA-03). Total: 35 historias en el MVP. Sigue ranking. |
| 2026-09-27 | Historias | US-020 dividida (020a/020b). 36 historias con INVEST y prioridad escritas en specs/historias/. Checkpoint de fase. |
| 2026-09-27 | Criterios | Checkpoint de Historias aceptado. Inicia fase Criterios: se propone el ritmo. |
| 2026-09-27 | Criterios | B1 escrito (5 YAML). Pendiente precisar dígito de verificación del NIT para cerrar SMART de US-001/US-011. |
| 2026-09-27 | Criterios | B1 completo (mensaje DV). B2 escrito: US-007 (pendiente regla 128x128) y US-012. |
| 2026-09-27 | Criterios | B2 completo. B3 completo (US-031, 005, 030, 006). 11 historias con criterios. |
| 2026-09-27 | Criterios | El usuario reabre el modelo de participación (ciudadano abierto vs. veedores invitados). B4 en pausa hasta decidir. |
| 2026-09-27 | Criterios | Modelo de participación: CERRADO (solo veedores invitados), definitivo. Se retoma B4. |
| 2026-09-27 | Criterios | B4 escrito (US-013, 032, 033, 014) con 4 pendientes; conflicto detectado: contratos en BD de tenants vs. BD central confirmada. |
| 2026-09-27 | Criterios | B4 completo: BD central, id_contrato, anulados se conservan, mensaje con tiempo variable. 15 historias con criterios. |
| 2026-09-27 | Criterios | B5 completo: cascada territorial DIVIPOLA; Terminados/Liquidados reportables 12 meses (nota asesor). 18 historias con criterios. |
| 2026-09-27 | Criterios | B6 completo (US-008, 009). Nota asesor: ancla First-Touch errónea + geocerca bloquearía la obra → nace US-035 (Admin corrige ubicación). 20 historias con criterios. |
| 2026-09-27 | Criterios | US-035 creada (P1, EPIC-004). Decisión: ficha de obra por organización. Total: 37 historias (P1 = 18). |
| 2026-09-27 | Criterios | B7: sellado por raíz de Merkle (R-BLK-05). Pendiente menor: hoja de metadatos. |
| 2026-09-27 | Criterios | B7 y B8 completos. Nacen US-036 y US-037 (P1). Total: 39 historias (P1 = 20); 28 con criterios. |
| 2026-09-27 | Criterios | B9 completo (validador con dos modos). 32 de 39 historias con criterios. Se combinan B10 y B11. |
| 2026-09-27 | Criterios | B10 y B11 completos. 37 de 39 historias con criterios. Último bloque: offline y proximidad. |
| 2026-09-27 | Criterios | Fase completa: 39/39 historias con YAML y SMART completo. Checkpoint de transición a Completitud. |
| 2026-09-27 | Completitud | Checkpoint de Criterios aceptado. Inicia Completitud: área CFG. |
| 2026-09-27 | Completitud | CFG explorada: red por entorno (R-CFG-01), parámetros (R-CFG-02), gap US-038-CFG. Sigue USR. |
| 2026-09-27 | Completitud | USR explorada: 3 reglas (R-USR-01..03), 3 gaps (US-039..041-USR), ajustes en US-003a, 005, 010, 018, 036. Sigue SEC (privacidad). |
| 2026-09-27 | Completitud | SEC parte 1 (privacidad): R-PRIV-02..05; gaps USR clasificados; aviso offline 24 h. Propuesta de optimizar fotos antes del hash, por confirmar. |
| 2026-09-27 | Completitud | SEC parte 2: R-SEC-03..06, R-PRIV-06, R-SA-02 precisada, gap US-042-SEC. Pendientes: mensaje de bloqueo, umbral de horas. Sigue AUD. |
| 2026-09-27 | Completitud | AUD explorada: R-AUD-01..05; US-042-SEC ⚡P2. Pendientes: texto de aviso de suspensión, conflicto 4B vs R-SEC-02. Sigue MON. |
| 2026-09-27 | Completitud | MON explorada: R-AUD-06 (R-SEC-02 intacta), R-MON-01/02, gaps US-043-MON y US-044-MON. Sigue INT. |
| 2026-09-27 | Completitud | INT explorada: R-INT-01..05, gap US-045-INT. Ajustados US-004, 013, 014, 021, 026. Sigue MNT. |
| 2026-09-27 | Completitud | MNT explorada: R-MNT-01..04; gaps US-046-INT, US-047-MNT, US-048-MNT. US-045-INT ⚡P2. Sigue RPT. |
| 2026-09-27 | Completitud | RPT explorada: 6 gaps (US-049..054-RPT). Gaps previos clasificados. Nota asesor: tamaño de P2. Sigue BCK. |
| 2026-09-27 | Completitud | BCK explorada: R-BCK-01..05 (RPO 1 h, RTO 4 h, respaldo de archivos, 30 días, sin prueba de restauración). Sigue TST (cierre). |
| 2026-09-27 | Completitud | TST explorada: R-TST-01..04; R-BCK-05 ajustada (restauración de prueba antes de producción). Las 10 áreas recorridas. |
| 2026-09-27 | Completitud | 10 áreas recorridas; 17 gaps escritos (56 historias). Rondas de cierre para completar SMART de 8 gaps y 3 cabos abiertos. |
| 2026-09-27 | Completitud | Cierre A: 3 cabos cerrados; SMART completo en 044, 045, 048; faltan mensajes en 039 y 042. |
| 2026-09-27 | Completitud | Cierre B: 56/56 historias con criterios y SMART completo. Checkpoint de fase. |
| 2026-09-27 | Completitud | Checkpoint aceptado: 10/10 áreas, 17 gaps, 64 reglas, 56/56 historias con SMART completo. |
| 2026-09-27 | Gherkin | 56 features generados (248 escenarios, 136 negativos, 36 edge), validados con el parser oficial; trazabilidad historia↔feature y mensajes exactos verificados. Se agregaron 4 negativos de roles faltantes. Borrado ejemplo_formato.feature. DQS-lite escrito. SPEC.md consolidado. Discovery COMPLETO. |
