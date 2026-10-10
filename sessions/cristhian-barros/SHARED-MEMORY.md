# SHARED-MEMORY — Discovery GovTrace

> Estado vivo del discovery. Se actualiza tras cada respuesta confirmada. Es la fuente para reanudar con `/discovery resume`.

```yaml
session:
  slug: cristhian-barros
  usuario: "Cristhian Barros"
  iniciada: 2026-09-26
  caso: sessions/cristhian-barros/caso.md
  modo_descubrimiento: asesor
  preferencia_validacion_vuifed: "confirmar una sola vez al cerrar cada épica (pedido por el usuario)"

project_state:
  current_phase: completed       # epicas | historias | criterios | completitud | gherkin | completed
  last_question: "Discovery cerrado (fase 5 Gherkin + DQS-lite completada)"
  next_step: "/plan — generar specs/PLAN.md desde specs/SPEC.md y features/*.feature"

epicas_phase:
  epicas_identificadas:          # confirmadas por el usuario
    - {id: EPIC-001, nombre: "Sincronización de contratos (SECOP II)"}
    - {id: EPIC-002, nombre: "Recolección de evidencia"}
    - {id: EPIC-003, nombre: "Sellado criptográfico (blockchain invisible)"}
    - {id: EPIC-004, nombre: "Visualización geoespacial (mapa)"}
    - {id: EPIC-005, nombre: "Verificación de integridad"}
    - {id: EPIC-006, nombre: "Gestión de cuentas y roles"}
    - {id: EPIC-007, nombre: "Moderación de reportes"}
    - {id: EPIC-008, nombre: "Radicación de denuncias"}
    - {id: EPIC-009, nombre: "Gestión de organizaciones (tenants)"}
  mvp:
    incluidas: [EPIC-001, EPIC-002, EPIC-003, EPIC-004, EPIC-005, EPIC-006, EPIC-009]
    excluidas: [EPIC-007, EPIC-008]
  ideas_por_explorar:            # supuestos del usuario, NO son reglas confirmadas todavía
    - "Alcance geográfico del veedor — PARCIALMENTE resuelto: las obras elegibles se limitan al territorio de su organización (US-012, R-VC-04). Sigue abierto: ¿se exige además presencia física (GPS cerca de la obra) al capturar? — explorar en Criterios"
    - "Radicación de denuncias → confirmada como EPIC-008 (alcance a definir en Historias)"
    - "Tenant = Organización ✅ (decisión de arquitectura confirmada en P4)"
    - "Ciudadano que colabora con más de una organización — explorar en Historias / Completitud (USR)"
    - "Relación entre el territorio configurado por la organización y el alcance geográfico del veedor — explorar en Criterios"
    - "Revocación lógica de evidencias (soft delete / estado de revocación) desde el Día 1: quién revoca, qué ve el público, qué queda registrado, relación con el sello — explorar en Historias / Completitud (AUD)"
    - "Duda del usuario: ¿las cuentas van en el MVP o al final? — se resuelve con VUIFED (mvp_included) y el ranking de Historias"
  escala_vuifed: "🔴/A=10 · 🟠/B=7 · 🟡/C=5 · 🟢/D=3 (valor salido de la opción elegida; 'Otra' se traduce con el usuario)"
  vuifed:                        # EPIC-XXX: {valor, usuarios, impacto, factibilidad, esfuerzo, dependencias}
    EPIC-001: {valor: 10, usuarios: 10, impacto: 10, factibilidad: 10, esfuerzo: 10, dependencias: 7, suma: 57, promedio: 9.5}   # ✅ confirmado
    EPIC-002: {valor: 10, usuarios: 10, impacto: 10, factibilidad: 10, esfuerzo: 10, dependencias: 5, suma: 55, promedio: 9.17}   # ✅ confirmado (Dependencias ajustada 10→5 tras nota asesor)
    EPIC-003: {valor: 10, usuarios: 10, impacto: 10, factibilidad: 7, esfuerzo: 5, dependencias: 5, suma: 47, promedio: 7.83}   # ✅ confirmado
    EPIC-004: {valor: 7, usuarios: 10, impacto: 7, factibilidad: 10, esfuerzo: 10, dependencias: 5, suma: 49, promedio: 8.17}   # ✅ confirmado (Dependencias ajustada 7→5 tras nota asesor)
    EPIC-005: {valor: 10, usuarios: 10, impacto: 10, factibilidad: 7, esfuerzo: 7, dependencias: 5, suma: 49, promedio: 8.17}   # ✅ confirmado
    EPIC-006: {valor: 10, usuarios: 7, impacto: 10, factibilidad: 10, esfuerzo: 5, dependencias: 10, suma: 52, promedio: 8.67}   # ✅ confirmado (Esfuerzo ajustado 10→5 tras nota asesor)
    EPIC-007: {valor: 7, usuarios: 7, impacto: 7, factibilidad: 10, esfuerzo: 10, dependencias: 5, suma: 46, promedio: 7.67}   # ✅ confirmado (Dependencias ajustada 10→5 tras nota asesor)
    EPIC-008: {valor: 10, usuarios: 7, impacto: 10, factibilidad: 10, esfuerzo: 7, dependencias: 5, suma: 49, promedio: 8.17}   # ✅ confirmado
    EPIC-009: {valor: 10, usuarios: 7, impacto: 10, factibilidad: 10, esfuerzo: 7, dependencias: 10, suma: 54, promedio: 9.0}   # ✅ confirmado
  completed: true               # checkpoint aceptado; el usuario avanzó a Historias

historias_phase:
  actores:                       # definidos por el usuario al iniciar Historias (amplían los 3 del caso)
    - {id: SUPER_ADMIN, nombre: "Super Administrador", descripcion: "Controla la plataforma global, provisiona nuevos tenants (organizaciones/veedurías) y supervisa los nodos RPC y costos de gas", origen: "nuevo (no estaba en el caso)"}
    - {id: TENANT_ADMIN, nombre: "Administrador de Organización", descripcion: "Líder de la ONG, Cámara de Comercio o Veeduría local; gestiona su propio equipo de veedores y supervisa sus proyectos asignados", origen: "caso"}
    - {id: VEEDOR, nombre: "Veedor Ciudadano", descripcion: "Usuario de campo: camina la obra, toma la foto con su teléfono, la geoposiciona y la sube", origen: "caso"}
    - {id: VERIFICADOR, nombre: "Verificador Anónimo / Público (ciudadano o periodista)", descripcion: "Navega el mapa, revisa contratos SECOP II y arrastra evidencias para verificar su inmutabilidad, sin registrarse", origen: "caso (renombrado desde Auditor/Periodista)"}
  priorizacion_metodo: "Por niveles: P1 = semanas 1-2, P2 = semanas 3-4, P3 = semana 5 o posterior"
  prioridades:
    P1: [US-001, US-002, US-011, US-012, US-005, US-030, US-031, US-013, US-015, US-032, US-033, US-008, US-009, US-020a, US-020b, US-017, US-034, US-035, US-036, US-037]
    P2: [US-003a, US-007, US-006, US-014, US-010, US-016, US-021, US-023, US-024, US-025, US-026, US-027, US-029]
    P3: [US-003b, US-004, US-018, US-019, US-022, US-028]
  orden_descomposicion: [EPIC-009, EPIC-006, EPIC-001, EPIC-002, EPIC-003, EPIC-005, EPIC-004]   # A) cimientos primero, luego por dependencia
  preferencia_validacion: "respuestas de opción inequívocas se confirman implícitamente; respuestas abiertas (historias) se validan explícitamente"
  historias:                     # 36 historias, INVEST revisado; detalle en specs/historias/US-XXX.md
    - {id: US-001, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Alta de organización validando datos legales (NIT, nombre) y asignando su subdominio, para habilitar acceso controlado y prevenir suplantación/spam", estado: borrador_invest_ok, nota: "el alta con datos válidos deja la organización aprobada y activa; el subdominio lo asigna el Super Admin aquí (candidata C6) para evitar colisiones"}
    - {id: US-002, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Asignar el Administrador inicial de una organización aprobada para transferirle la gestión de sus veedores", estado: borrador_invest_ok, nota: "aprobada = dada de alta (US-001): el alta con datos válidos deja la organización aprobada y activa; no hay paso de aprobación separado"}
    - {id: US-003a, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Suspender o reactivar una organización activa para bloquear o restablecer temporalmente su acceso ante impagos o revisiones de seguridad", estado: borrador_invest_ok}
    - {id: US-003b, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Dar de baja de forma definitiva a una organización para retirar del sistema a entidades inoperantes aplicando políticas de retención de datos", estado: borrador_invest_ok}
    - {id: US-004, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Ver reporte de consumo de transacciones y gas en Polygon agrupado por organización", estado: borrador_invest_ok, nota: "depende en orden de EPIC-003"}
    - {id: US-005, epica: EPIC-006, actor: TENANT_ADMIN, titulo: "Invitar nuevos usuarios por correo asignándoles el rol de veedor de campo para conformar el equipo local", estado: borrador}
    - {id: US-006, epica: EPIC-006, actor: TENANT_ADMIN, titulo: "Desactivar el acceso de un veedor de campo para revocar sus permisos si deja la organización o comete infracciones", estado: borrador}
    - {id: US-007, epica: EPIC-009, actor: TENANT_ADMIN, titulo: "Actualizar el nombre de fantasía y el logo de la veeduría para mantener su identidad visual", estado: borrador, nota: "ajustada tras nota asesor: el NIT y los datos legales NO son editables por el Admin de Organización"}
    - {id: US-008, epica: EPIC-002, actor: VEEDOR, titulo: "Crear un reporte de evidencia vinculado a un contrato público del SECOP para registrar una anomalía o avance de obra en terreno", estado: borrador}
    - {id: US-009, epica: EPIC-002, actor: VEEDOR, titulo: "Adjuntar fotografías y documentos PDF con metadatos automáticos para respaldar la veeduría", estado: borrador, nota: "MVP: solo fotografías y PDF (sin video). 'Metadatos automáticos' se precisa en Criterios"}
    - {id: US-010, epica: EPIC-002, actor: VEEDOR, titulo: "Consultar el listado y estado de mis propios reportes enviados y su hash de sellado en la blockchain", estado: borrador}
    - {id: US-012, epica: EPIC-009, actor: TENANT_ADMIN, titulo: "Configurar desde mi panel las ciudades y/o departamentos que vigila mi organización para que el mapa y las obras disponibles correspondan a nuestro territorio", estado: borrador, nota: "confirmada con 'y las obras disponibles': el territorio también limita qué obras puede elegir el veedor"}
    - {id: US-013, epica: EPIC-001, actor: SISTEMA, titulo: "Tarea programada que consulta la API SECOP II filtrando solo por las ciudades/departamentos configurados por organizaciones activas, para mantener actualizados los contratos de interés sin almacenamiento innecesario", estado: borrador}
    - {id: US-014, epica: EPIC-001, actor: SUPER_ADMIN, titulo: "Panel de salud de la sincronización (última ejecución exitosa, contratos insertados, errores/caídas de la API)", estado: borrador}
    - {id: US-015, epica: EPIC-001, actor: TENANT_ADMIN, titulo: "Listado de contratos sincronizados en mi territorio (ordenable por fecha o valor) para planificar a qué obras enviar a mis veedores", estado: borrador}
    - {id: US-016, epica: EPIC-002, actor: VEEDOR, titulo: "Buscar contratos activos de mi territorio por palabras clave (obra, contratista, número de proceso) para seleccionar la obra exacta al subir evidencia", estado: borrador, nota: "ubicada en EPIC-002 (paso de selección de obra del flujo de recolección)"}
    - {id: US-017, epica: EPIC-004, actor: VERIFICADOR, titulo: "Ver datos clave del contrato oficial (entidad, contratista, valor, plazo, link al SECOP) en la vista de la obra para contrastar fondos públicos con la evidencia", estado: borrador, nota: "ubicada en EPIC-004 (vista pública de la obra)"}
    - {id: US-018, epica: EPIC-002, actor: VEEDOR, titulo: "Guardar reportes y evidencias localmente sin señal y sincronizarlos automáticamente al recuperar conectividad, para cero pérdida de datos", estado: borrador, nota_tecnica: "PWA: Service Worker + IndexedDB (localforage), cola de POST hasta evento 'online'"}
    - {id: US-019, epica: EPIC-002, actor: VEEDOR, titulo: "Detectar mis coordenadas y sugerirme primero las obras en un radio de proximidad (ej. 500 m) para minimizar la búsqueda y evitar errores de asignación", estado: borrador, nota_tecnica: "tabla govtrace_worksites (lat, lng); distancia con Haversine en la query o paquete geoespacial de Eloquent, sin PostGIS en el MVP"}
    - {id: US-020a, epica: EPIC-003, actor: SISTEMA, titulo: "Desplegar el Smart Contract de sellado en Polygon con control de acceso (solo RELAYER_ROLE sella)", estado: invest_ok}
    - {id: US-020b, epica: EPIC-003, actor: SISTEMA, titulo: "Encolar el hash validado y registrarlo en el Smart Contract vía el relayer gestionado (firma y paga el gas)", estado: invest_ok, nota_tecnica: "Job Queue de Laravel; RPC tipo Alchemy/Infura"}
    - {id: US-021, epica: EPIC-003, actor: SISTEMA, titulo: "Reintentos con retraso exponencial si falla el RPC, hay congestión o la transacción queda pending/dropped, para que ningún reporte quede sin sello", estado: borrador}
    - {id: US-022, epica: EPIC-003, actor: SUPER_ADMIN, titulo: "Dashboard con el saldo MATIC/POL de la hot wallet del Relayer y alertas (webhook Slack/Email) bajo un umbral (ej. 1 MATIC) para recargar a tiempo", estado: borrador}
    - {id: US-023, epica: EPIC-003, actor: VEEDOR, titulo: "Ver en el detalle de mi evidencia confirmada un 'Recibo de Inmutabilidad' (TxID, número de bloque, enlace a Polygonscan) como prueba independiente de que nadie puede alterarla", estado: borrador, nota: "el usuario indica que luego aplica también al Verificador Público → cubrir en EPIC-005"}
    - {id: US-024, epica: EPIC-005, actor: VERIFICADOR, titulo: "Arrastrar una foto o documento a la herramienta de Validación para que mi navegador recalcule su SHA-256 y consulte el Smart Contract en Polygon, con veredicto Auténtico/Alterado independiente de la BD de GovTrace", estado: borrador}
    - {id: US-025, epica: EPIC-005, actor: VERIFICADOR, titulo: "Ver públicamente el Recibo de Inmutabilidad (TxID, bloque, timestamp de red, enlace a Polygonscan) junto a cada evidencia confirmada", estado: borrador, nota: "parte pública de US-023"}
    - {id: US-026, epica: EPIC-005, actor: VERIFICADOR, titulo: "Descargar el archivo de evidencia sellado (exactamente el que se hasheó: ya sanitizado en origen, sin recompresión ni marcas de agua) para peritaje forense propio o como prueba en denuncias", estado: borrador, nota: "ajustada tras nota asesor: nunca se publica el archivo crudo del sensor"}
    - {id: US-027, epica: EPIC-004, actor: VERIFICADOR, titulo: "Mapa interactivo con pines de obras que cambian de color automáticamente (ej. rojo vencidos, amarillo suspendidos, verde terminados) para detectar de un vistazo los proyectos en riesgo o abandono", estado: borrador}
    - {id: US-028, epica: EPIC-004, actor: VERIFICADOR, titulo: "Filtrar los pines por variables del contrato (estado, rango de presupuesto, municipio) para enfocar el análisis", estado: borrador}
    - {id: US-029, epica: EPIC-004, actor: VERIFICADOR, titulo: "Al hacer clic en un pin, ver además de los datos del contrato (US-017) una línea de tiempo cronológica con todas las evidencias aprobadas, para comprobar la evolución o el estancamiento de la obra", estado: borrador}
    - {id: US-030, epica: EPIC-006, actor: VEEDOR, titulo: "Aceptar la invitación y crear mi contraseña para activar mi cuenta", estado: borrador, origen: "candidata C1 (documento externo)"}
    - {id: US-031, epica: EPIC-006, actor: "SUPER_ADMIN, TENANT_ADMIN, VEEDOR", titulo: "Iniciar sesión para acceder a mis herramientas", estado: borrador, origen: "candidata C2"}
    - {id: US-032, epica: EPIC-001, actor: SISTEMA, titulo: "Traer solo contratos de tipo 'Obra' para descartar los irrelevantes (ej. papelería)", estado: borrador, origen: "candidata C3"}
    - {id: US-033, epica: EPIC-001, actor: SISTEMA, titulo: "Sincronizar solo contratos nuevos o modificados para no duplicar registros", estado: borrador, origen: "candidata C4"}
    - {id: US-034, epica: EPIC-004, actor: SISTEMA, titulo: "Un Job/observer diario marca 'en riesgo' las obras con fecha de fin vencida (fecha actual vs fecha fin del contrato) para colorear su pin", estado: borrador, origen: "candidata C5 ajustada", nota: "el estado calculado vive en la ficha de obra de GovTrace, no en el contrato (R-SEC-01)"}
    - {id: US-035, epica: EPIC-004, actor: TENANT_ADMIN, titulo: "Corregir la ubicación oficial de una obra dejando registro del cambio, para que una ubicación First-Touch errónea no bloquee los reportes", estado: invest_ok, prioridad: P1, origen: "nota asesor en Criterios B6 (opción 3B)"}
    - {id: US-036, epica: EPIC-004, actor: TENANT_ADMIN, titulo: "Revisar las evidencias ocultas y publicarlas para decidir qué se muestra en el mapa público", estado: invest_ok, prioridad: P1, origen: "publicación manual (Criterios B8)"}
    - {id: US-037, epica: EPIC-004, actor: TENANT_ADMIN, titulo: "Retirar una evidencia publicada dejando una lápida visible, para cumplir las políticas sin borrar el rastro", estado: invest_ok, prioridad: P1, origen: "publicación manual (Criterios B8)"}
    - {id: US-011, epica: EPIC-009, actor: SUPER_ADMIN, titulo: "Actualizar el NIT y los datos legales de una organización desde el panel global, a solicitud formal de la organización (fusión, cambio de razón social)", estado: borrador, nota: "derivada de la decisión sobre el NIT"}
  roles_tenant:                  # EPIC-006 — MVP con 2 roles (decisión del usuario, evitar sobreingeniería)
    - TENANT_ADMIN
    - VEEDOR
  restricciones:                 # "NO puede" aportados por el usuario → reglas / escenarios negativos
    - {id: R-SA-01, actor: SUPER_ADMIN, regla: "No puede modificar, borrar ni falsificar hashes de evidencias o contratos ya sellados en Polygon"}
    - {id: R-SA-02, actor: SUPER_ADMIN, regla: "No puede crear reportes ni subir evidencias en nombre de una organización sin su consentimiento"}
    - {id: R-SA-03, actor: SUPER_ADMIN, regla: "El subdominio de cada organización lo asigna el Super Administrador al darla de alta (US-001), evitando colisiones"}
    - {id: R-TA-01, actor: TENANT_ADMIN, regla: "No puede dar de alta otras organizaciones (exclusivo del Super Administrador)"}
    - {id: R-TA-02, actor: TENANT_ADMIN, regla: "No puede alterar ni borrar evidencias ya selladas en Polygon"}
    - {id: R-TA-03, actor: TENANT_ADMIN, regla: "No puede editar el NIT ni los datos legales de validación inicial; solo el Super Administrador, a solicitud formal"}
    - {id: R-VC-04, actor: VEEDOR, regla: "Solo puede reportar sobre obras dentro del territorio (ciudades/departamentos) configurado por su organización"}
    - {id: R-SEC-01, actor: TODOS, regla: "Nadie (ni el Super Administrador) puede editar, modificar o borrar datos de un contrato descargado; SECOP es la única fuente de verdad y los errores del Estado se muestran tal cual"}
    - {id: R-SEC-02, actor: SISTEMA, regla: "No se consultan ni guardan contratos de territorios que no pertenezcan a ninguna organización activa"}
    - {id: R-GEO-01, actor: VEEDOR, regla: "Al crear un reporte (US-008) se capturan las coordenadas GPS del dispositivo y se guardan en la Evidencia. Si la obra no tiene coordenadas (Spatial-Null), las del primer reporte fijan su ubicación (First-Touch Anchoring, patrón human-in-the-loop). La ubicación vive en una FICHA DE OBRA propia de GovTrace, separada del contrato SECOP (que queda intacto, R-SEC-01 se cumple)", nota: "decisión del usuario ante la falta de coordenadas en SECOP II"}
    - {id: R-HASH-01, actor: SISTEMA, regla: "El SHA-256 de cada archivo se calcula en el teléfono en el momento de la captura (Web Crypto API) y se guarda junto al archivo en IndexedDB. Al recibirlo, el backend recalcula el hash y lo compara antes de encolar el sellado", nota: "decisión del usuario tras nota asesor (ventana de alteración offline)"}
    - {id: R-BLK-01, actor: TODOS, regla: "Ningún Veedor ni Verificador instala wallets, maneja llaves privadas ni ve conceptos de gas; si aparece un prompt criptográfico, es un fallo de UX"}
    - {id: R-BLK-02, actor: SISTEMA, regla: "El Smart Contract solo recibe [ID_Obra (uint256), Hash_Evidencia (bytes32), Timestamp (uint256)]; nunca imágenes, PDFs, nombres ni textos largos"}
    - {id: R-BLK-03, actor: SISTEMA, regla: "La función de sellado del Smart Contract no es pública: solo la puede invocar el rol RELAYER_ROLE (AccessControl de OpenZeppelin); cualquier otra billetera es rechazada on-chain"}
    - {id: R-BLK-04, actor: SISTEMA, regla: "La llave privada del Relayer nunca se guarda en el servidor ni en .env: la custodia un servicio de relayer/KMS gestionado; Laravel solo envía la orden por API"}
    - {id: R-VER-01, actor: SISTEMA, regla: "La validación (US-024) nunca envía el archivo al backend: el hash se calcula solo en la memoria del navegador (Web Crypto API)"}
    - {id: R-VER-02, actor: VERIFICADOR, regla: "Descargar evidencias y usar el validador no exige registro, inicio de sesión ni tokens"}
    - {id: R-PRIV-01, actor: SISTEMA, regla: "El archivo crudo del sensor nunca se publica. La PWA purga los metadatos EXIF en el teléfono ANTES de calcular el SHA-256; lo que se sube, se sella y se publica es ese archivo sanitizado", nota: "decisión del usuario; el protocolo completo se diseña en Completitud (SEC)"}
    - {id: R-MAP-01, actor: SISTEMA, regla: "No existe un mapa global que mezcle evidencias de varias organizaciones: cada tenant tiene su propio mapa público aislado en su subdominio y asume la responsabilidad curatorial y legal de sus pines"}
    - {id: R-MAP-02, actor: SISTEMA, regla: "La carga inicial del mapa solo devuelve [{id, lat, lng, color_pin}]; datos del contrato y línea de tiempo se piden bajo demanda al hacer clic en un pin"}
    - {id: R-BLK-05, actor: SISTEMA, regla: "Se sella una raíz de Merkle por reporte; cada archivo conserva su prueba de inclusión, que se publica para que el navegador del Verificador recomponga la raíz y la compare con la cadena"}
    - {id: R-VC-01, actor: VEEDOR, regla: "No puede invitar, aprobar ni gestionar cuentas de otros usuarios de la organización"}
    - {id: R-VC-02, actor: VEEDOR, regla: "No puede ver ni modificar reportes creados por otros veedores (en el MVP cada uno gestiona sus propios reportes)"}
    - {id: R-VC-03, actor: VEEDOR, regla: "No tiene acceso a la configuración ni a los reportes de consumo de gas de la organización"}
  fuente_de_verdad: "registro de sesión (sessions/cristhian-barros/ + specs/epicas/). Documento externo ~/Downloads/govtrace_decisiones_de_dise_o_funcional_fase_1_y_2.md NO es fuente; solo aporta candidatas"
  sondas_para_completitud:       # notas internas del guía; NO se enuncian al usuario, se sondean en Completitud
    - "[RESUELTA en AUD] AUD: qué pasa con las evidencias/sellos de una organización suspendida o dada de baja (US-003a/b); qué dicen las 'políticas de retención de datos' frente a la promesa de inmutabilidad"
    - "[RESUELTA] SEC/USR: qué significa y cómo se registra el 'consentimiento' de R-SA-02"
    - "[RESUELTA] AUD: R-TA-02 (no borrar evidencias selladas) vs. revocación lógica decidida en Épicas — ¿quién puede revocar?"
    - "[RESUELTA/PARCIAL en AUD-MON] AUD/MNT: R-SEC-02 + organización suspendida/dada de baja — ¿qué pasa con los contratos ya guardados de un territorio que queda sin organizaciones activas?"
    - "[RESUELTA en Criterios] hash que no coincide → mensaje en US-009"
    - "[RESUELTA] SEC (límite residual): el hash calculado en el teléfono protege el transporte, pero quien controle el dispositivo podría alterar foto y hash juntos antes de subirlos; sondear si se acepta ese riesgo o se mitiga (p. ej. marca de tiempo de captura, firma)"
    - "[RESUELTA en INT] INT: el relayer gestionado es una dependencia externa más del sellado — ¿qué pasa si ese servicio falla o cambia? (verificar vigencia del proveedor elegido)"
    - "[RESUELTA en SEC-1] SEC/Privacidad: EXIF se purga en origen (R-PRIV-01), pero ¿rostros y placas visibles en la foto? ¿los PDFs también llevan metadatos (autor, software)?"
    - "[RESUELTA en SEC-1] SEC/Privacidad: las coordenadas GPS de cada evidencia (R-GEO-01) se guardan en BD — ¿se publican? ¿con qué precisión? (misma exposición que el EXIF)"
    - "[RESUELTA en Criterios] publicación manual (US-036/037)"
    - "[RESUELTA en CFG] testnet en desarrollo, mainnet en producción (R-CFG-01)"
    - "[RESUELTA en AUD] AUD (derivada de CFG): cambios de parámetros globales por el Super Admin — ¿quedan registrados? ¿aplican retroactivamente (p. ej. geocerca)?"
    - "[RESUELTA en AUD] AUD (US-003a/b): ¿el mapa público y la verificación de una organización suspendida o dada de baja siguen visibles? ('resta visibilidad en la interfaz' vs. promesa de evidencia pública verificable)"
    - "[RESUELTA en USR] USR (US-003a): reportes en cola offline de un veedor cuya organización fue suspendida — ¿se pierden al recibir 403?"
    - "[RESUELTA en AUD] AUD (US-011): qué registra exactamente el log de auditoría global (quién, cuándo, valor anterior y nuevo, soporte de la solicitud)"
    - "[RESUELTA] SEC (US-007): el logo admite SVG — un SVG puede contener scripts; ¿se sanea o se restringe?"
    - "[RESUELTA/PARCIAL en AUD-MON] AUD (US-012): al quitar una ciudad, sus evidencias salen del mapa de los veedores; ¿y del mapa PÚBLICO? ¿y los contratos ya guardados de ese municipio (R-SEC-02)?"
    - "[RESUELTA en USR] USR: no hay historia de recuperar/restablecer contraseña olvidada"
    - "[RESUELTA en USR] USR: US-002 exige correo único en todo el sistema para el Admin; US-005 solo lo exige dentro de la organización → ¿un ciudadano puede ser veedor en dos organizaciones con el mismo correo? (idea abierta desde Épicas)"
    - "[RESUELTA] SEC (US-031): intentos fallidos repetidos de inicio de sesión (fuerza bruta) — no se mencionó límite ni bloqueo"
    - "[RESUELTA en USR] USR: ¿se puede reenviar o revocar una invitación pendiente? ¿se puede reactivar un veedor desactivado?"
    - "[RESUELTA en MNT] MNT: los contratos anulados sin evidencia se conservan como 'cancelled' para siempre — ¿crecimiento de la BD central a largo plazo?"
    - "[RESUELTA en Criterios] US-019 aplica las mismas reglas de US-016"
    - "[RESUELTA en INT] INT/AUD: disponibilidad de las pruebas de inclusión si GovTrace no las entrega"
    - "[RESUELTA en MNT] MNT: evidencias en 'Falla de Sellado' — ¿quién y cómo las vuelve a encolar? (US-021 dice 'intervención del soporte técnico' sin historia)"
    - "[RESUELTA en MNT] MNT: nueva versión del Smart Contract — ¿los sellos del contrato anterior siguen verificables?"
    - "[RESUELTA en MNT] MNT: retención del log de auditoría y de la tabla de seudónimos del veedor"
    - "[RESUELTA en MNT] MNT/BCK: tras borrar los archivos a los 5 años (R-AUD-03), ¿se conservan las pruebas de inclusión para que las copias descargadas sigan verificables?"
    - "[RESUELTA/PARCIAL en AUD-MON] MON/RPT: el log de auditoría existe (R-AUD-04) pero ninguna historia dice quién lo consulta"
    - "[RESUELTA] 17 gaps escritos en specs/historias/ y specs/criterios/ (US-038-CFG … US-054-RPT); SMART incompleto en 8: 039, 042, 044, 045, 048 (P2) y 050, 052, 054 (P3)"
    - "[RESUELTA en cierre A] US-012: al quitar una ciudad, ¿sus evidencias salen también del mapa PÚBLICO?"
    - "[RESUELTA] SEC (US-020b/024): ¿el servidor recalcula también la raíz de Merkle antes de sellar (como hace con cada hash, R-HASH-01)? ¿qué pasa si GovTrace no entrega la prueba de inclusión (disponibilidad)?"
    - "[RESUELTA en SEC-1] Privacidad (US-020b): la hoja de metadatos incluye el ID del veedor y sus coordenadas; ¿se publica ese JSON para verificarlo? (solo la raíz va on-chain, R-BLK-02 se cumple)"
    - "[RESUELTA en cierre A] Mapa: ¿qué color tiene una obra Terminada/Liquidada dentro de la ventana de 12 meses?"
    - "[RESUELTA en SEC-1] SEC/AUD (decisión de principio del usuario): el validador libre debe responder 'Auténtico' ante una evidencia Oculta/Rechazada que sí se selló → implica que el API entrega pruebas de inclusión también de evidencias no publicadas; afinar privacidad y mensaje"
    - "[RESUELTA en USR] USR (US-010): el veedor ve 'En Revisión' también cuando su evidencia fue Rechazada → ¿queda esperando para siempre? ¿se le informa alguna vez?"
    - "[RESUELTA en INT] INT (US-004): conversión POL/USD depende de un API de precios externo (CoinGecko) — ¿qué pasa si falla?"
    - "[RESUELTA en USR] USR (US-018): un reporte pendiente se descarta a los 7 días — ¿se avisa al veedor antes o después? (se pierde evidencia)"
    - "[RESUELTA] SEC (US-018): el timestamp congelado viene del reloj del teléfono, que el usuario puede cambiar (riesgo residual del dispositivo comprometido)"
    - "[RESUELTA] Formatos: el validador acepta JPG/PNG/PDF, pero US-009 no fija formatos de foto; iPhone captura en HEIC → ¿se convierte en el teléfono antes del hash?"
    - "[RESUELTA en USR] Detalle de rechazo: al 'Rechazar' en la bandeja (US-036), ¿se pide motivo y queda en el log como en US-037?"
    - "[RESUELTA en INT] INT (US-035): el mapa interactivo usa un proveedor externo (Mapbox/Google Maps) — costo, llaves de API, qué pasa si falla"
    - "[RESUELTA en cierre A] Coherencia (US-021): el mensaje de falla definitiva dice 'por congestión de red' aunque la causa pueda ser otra (relayer, saldo)"
    - "[RESUELTA en INT] INT (US-012/013): SECOP II identifica territorio por nombre de departamento/ciudad; el territorio se configura con códigos DIVIPOLA → ¿cómo se mapea uno al otro? (nombres con tildes, variantes)"
    - "[RESUELTA] SEC/AUD: First-Touch Anchoring — un GPS falso o un error del primer veedor fija para siempre la ubicación de la obra; ¿se puede corregir? ¿quién? ¿queda rastro?"
    - "[RESUELTA: US-027 'solo tienen pin las obras con ubicación'] Mapa: obras sin anclar (Spatial-Null) no tienen pin hasta el primer reporte; 'obra cercana' solo funciona para obras ya ancladas (US-016 búsqueda por palabra clave cubre el resto)"
    - "[RESUELTA en INT] Modelo: ¿obra = contrato? Una obra puede tener varios contratos (obra + interventoría) — sondear en Criterios de EPIC-001/004"
    - "[RESUELTA] trazabilidad del territorio con US-012"
    - "[RESUELTA en SEC-1] Alcance resuelto: US-009 = fotos + PDF. Sondear en Criterios qué metadatos/geolocalización aplica a un PDF (no trae GPS como una foto)"
  completed: true               # checkpoint aceptado

criterios_phase_completed: true   # checkpoint aceptado 2026-09-27

criterios_phase:
  ritmo: "C) por bloques de historias relacionadas"
  bloques:
    B1: {nombre: "Alta y gobierno de organizaciones", historias: [US-001, US-002, US-011, US-003a, US-003b], estado: completo}
    B2: {nombre: "Configuración de la organización", historias: [US-007, US-012], estado: completo}
    B3: {nombre: "Acceso y equipo", historias: [US-031, US-005, US-030, US-006], estado: completo}
    B4: {nombre: "Sincronización SECOP", historias: [US-013, US-032, US-033, US-014], estado: completo}
    B5: {nombre: "Contratos para los usuarios", historias: [US-015, US-016, US-017], estado: completo}
    B6: {nombre: "Captura de evidencia", historias: [US-008, US-009, US-035], estado: completo}
    B7: {nombre: "Sellado", historias: [US-020a, US-020b, US-021], estado: completo}
    B8: {nombre: "Mapa y publicación", historias: [US-034, US-027, US-029, US-028], estado: completo}
    B9: {nombre: "Verificación pública", historias: [US-024, US-026, US-036, US-037], estado: completo}
    B10: {nombre: "Recibos y seguimiento", historias: [US-010, US-023, US-025], estado: completo}
    B11: {nombre: "Monitoreo y costos", historias: [US-022, US-004], estado: completo}
    B12: {nombre: "Offline y proximidad", historias: [US-018, US-019], estado: completo}
  historias_con_criterios: "39/39 del discovery + 17 gaps = 56/56, todas con SMART completo (specs/criterios/)"
  completed: true

completitud_phase:
  ritmo: "una ronda por área, con varias preguntas cortas (mismo estilo de bloques de Criterios)"
  areas_exploradas: [CFG, USR, SEC, AUD, MON, INT, MNT, RPT, BCK, TST]   # las 10 áreas recorridas        # CFG USR SEC AUD MON INT MNT RPT BCK TST
  backlog_total: {historias: 56, P1: 20, P2: 23, P3: 13}
  gaps:
    - {id: US-038-CFG, titulo: "Super Admin ajusta desde el panel global los parámetros operativos: radio de geocerca, ventana de Terminados/Liquidados, vigencia de invitaciones, umbral de saldo del Relayer y hora de la sincronización", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-039-USR, titulo: "El usuario restablece su contraseña él mismo con un enlace recibido por correo", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-040-USR, titulo: "El Admin de Organización reenvía o revoca una invitación pendiente", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-041-USR, titulo: "El Admin de Organización reactiva a un veedor desactivado", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-042-SEC, titulo: "El Admin de Organización autoriza en el sistema al Super Admin para crear reportes/subir evidencias en nombre de su organización, con registro (y el Super Admin solo puede hacerlo con esa autorización)", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-043-MON, titulo: "Consultar el log de auditoría: el Super Admin ve todo y cada Admin de Organización ve el de la suya", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-044-MON, titulo: "El Super Admin recibe alertas de una herramienta de monitoreo externa (errores de la app, caídas, disponibilidad)", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-045-INT, titulo: "El Admin de Organización agrupa varios contratos de tipo Obra en una misma ficha de obra", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-046-INT, titulo: "Script de verificación independiente en el repositorio público para verificar archivo + prueba solo contra Polygon", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-047-MNT, titulo: "El Super Admin vuelve a encolar desde su panel evidencias en 'Falla de Sellado' (una o varias)", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-048-MNT, titulo: "Job que archiva fuera de la base principal los contratos sin evidencias cerrados hace más de 5 años", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-049-RPT, titulo: "El Admin de Organización ve un resumen de su territorio: obras por color, evidencias por clasificación y por mes, veedores activos", clasificacion: "⚡ Importante", prioridad: P2}
    - {id: US-050-RPT, titulo: "El Admin de Organización exporta sus obras y evidencias en CSV", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-051-RPT, titulo: "Estadísticas públicas del territorio: obras en riesgo, evidencias por mes, contratos anulados con evidencias", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-052-RPT, titulo: "Datos abiertos descargables (CSV/JSON) de las evidencias publicadas y sus sellos", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-053-RPT, titulo: "El Super Admin ve un resumen de uso por organización: veedores activos, evidencias recibidas, publicadas, rechazadas y retiradas", clasificacion: "💡 Mejora", prioridad: P3}
    - {id: US-054-RPT, titulo: "El Super Admin recibe alertas de organizaciones sin actividad durante 30 días", clasificacion: "💡 Mejora", prioridad: P3}
  reglas_nuevas:
    - {id: R-CFG-01, regla: "Red de sellado: testnet de Polygon en desarrollo y pruebas; red principal (mainnet) al salir a producción"}
    - {id: R-CFG-02, regla: "Fijos en el código: precisión GPS 50 m, archivos por reporte (5 fotos o 1 PDF, 10 MB), vigencia offline 7 días. Configurables por el Super Admin (globales): geocerca 500 m, ventana 12 meses, invitación 48 h, umbral 5 POL, sincronización 02:00. Ninguno es configurable por organización"}
    - {id: R-USR-01, regla: "Un mismo correo puede ser veedor en varias organizaciones: una cuenta independiente por organización"}
    - {id: R-USR-02, regla: "El veedor ve 'Rechazada' con el motivo del Administrador (Rechazar exige motivo); 'En Revisión' = solo Oculto"}
    - {id: R-PRIV-02, regla: "Las coordenadas de cada evidencia se muestran al público aproximadas (~100 m), nunca exactas"}
    - {id: R-PRIV-03, regla: "En el JSON de metadatos sellado, el ID del veedor se reemplaza por un seudónimo antes de sellar; ese JSON se publica"}
    - {id: R-PRIV-04, regla: "La app limpia los metadatos de los PDF (autor, software, fechas) antes de calcular el hash"}
    - {id: R-PRIV-05, regla: "No se difuminan rostros ni placas: se publican tal cual y la organización decide al revisar (US-036). Riesgo aceptado por el usuario"}
    - {id: R-PRIV-06, regla: "Las fotos se optimizan en el teléfono antes del hash: lado mayor 1920 px, JPEG, calidad 80 %; se sella el archivo optimizado"}
    - {id: R-SEC-03, regla: "5 intentos fallidos de inicio de sesión bloquean la cuenta 15 minutos"}
    - {id: R-SEC-04, regla: "Los logos SVG se aceptan, pero se limpian de contenido ejecutable"}
    - {id: R-SEC-05, regla: "El servidor registra su hora de recepción y marca los reportes cuya hora de captura está en el futuro o es anterior a los 7 días de vigencia offline; la marca la ven el Admin de Organización y el Super Admin"}
    - {id: R-SEC-06, regla: "El servidor calcula su propia raíz de Merkle y las pruebas; ignora la raíz del teléfono"}
    - {id: R-SA-02, regla: "(precisada) El Super Admin solo crea reportes en una organización si su Admin lo autoriza en el sistema, con registro (US-042-SEC)"}
    - {id: R-AUD-01, regla: "Organización suspendida: su mapa y evidencias publicadas siguen visibles y verificables, con aviso de 'organización suspendida'"}
    - {id: R-AUD-02, regla: "Organización dada de baja: su mapa deja de estar en línea; sus evidencias siguen verificables en el validador libre"}
    - {id: R-AUD-03, regla: "Retención: archivos de una organización dada de baja se conservan 5 años y luego se borran; el sello permanece"}
    - {id: R-AUD-04, regla: "Log de auditoría (quién, cuándo, valor anterior y nuevo) para: ciclo de vida de organizaciones, NIT/datos legales, publicar/rechazar/retirar evidencias, corrección de ubicación, parámetros globales, invitar/desactivar/reactivar veedores, cambios de territorio, autorizaciones al Super Admin"}
    - {id: R-AUD-05, regla: "Los reportes se validan con los parámetros (p. ej. radio de geocerca) vigentes en el momento de la captura"}
    - {id: R-AUD-06, regla: "(ajuste de 4B→4A por conflicto con R-SEC-02) Contratos de un territorio sin organizaciones activas se conservan pero dejan de actualizarse"}
    - {id: R-MON-01, regla: "Evidencia más de 2 h 'En Cola' → alerta al Super Admin y al Admin de la organización afectada"}
    - {id: R-MON-02, regla: "Los reportes con hora sospechosa solo se marcan; decide el Admin al revisar"}
    - {id: R-INT-01, regla: "Si el relayer gestionado no responde, el sellado espera y reintenta (US-021)"}
    - {id: R-INT-02, regla: "Mapas con OpenStreetMap y teselas abiertas"}
    - {id: R-INT-03, regla: "Si el API de precios falla, el reporte de costos usa el último precio conocido con su fecha; el municipio SECOP se empareja con DIVIPOLA normalizado y lo no emparejado se descarta y reporta"}
    - {id: R-INT-04, regla: "Cada descarga incluye la prueba de inclusión para verificar solo contra Polygon, sin depender de GovTrace"}
    - {id: R-INT-05, regla: "Una ficha de obra puede agrupar varios contratos de tipo Obra (lo decide el Admin de Organización)"}
    - {id: R-MNT-01, regla: "El validador consulta todas las direcciones históricas del Smart Contract; los sellos antiguos siguen verificables"}
    - {id: R-MNT-02, regla: "Las pruebas de inclusión se conservan para siempre, aunque se borren los archivos"}
    - {id: R-MNT-03, regla: "El log de auditoría se conserva para siempre; la tabla seudónimo→veedor, 5 años"}
    - {id: R-MNT-04, regla: "Contratos sin evidencias y cerrados hace más de 5 años se archivan fuera de la base principal"}
    - {id: R-BCK-01, regla: "Pérdida máxima de datos aceptada (RPO): 1 hora"}
    - {id: R-BCK-02, regla: "Tiempo máximo de recuperación (RTO): 4 horas"}
    - {id: R-BCK-03, regla: "Los archivos de evidencia se respaldan periódicamente, igual que la base de datos"}
    - {id: R-BCK-04, regla: "Las copias de respaldo se guardan 30 días"}
    - {id: R-BCK-05, regla: "Una restauración de prueba antes de salir a producción (ajuste tras nota asesor; sin prueba mensual)"}
    - {id: R-TST-01, regla: "Sellado: pruebas unitarias con blockchain simulada (mock) + prueba de humo contra la testnet antes de cada salida a producción"}
    - {id: R-TST-02, regla: "SECOP II: pruebas con respuestas grabadas (fixtures), incluidas variantes raras de nombres de municipio"}
    - {id: R-TST-03, regla: "Captura y modo offline: pruebas de componentes (Vitest) + pruebas de extremo a extremo en navegador real simulando pérdida de señal"}
    - {id: R-TST-04, regla: "Todas las reglas deben tener su escenario automático que las viola a propósito"}
    - {id: R-USR-03, regla: "Los reportes offline de un veedor cuya organización fue suspendida se conservan y se envían si se reactiva dentro de los 7 días"}
  completed: true                # checkpoint aceptado

gherkin_phase:
  features_generados: "56 archivos en features/ (uno por historia): 248 escenarios, 136 @negative, 36 @edge; validados con @cucumber/gherkin; mensajes exactos de los YAML verificados literalmente"
  ejemplo_formato_borrado: true
  dqs_lite: sessions/cristhian-barros/dqs-lite.md
  spec: specs/SPEC.md
  ready_for_handoff: true
```

## Decisiones de arquitectura (confirmadas)

- **Publicación manual:** toda evidencia sellada nace Oculta; solo el Administrador de Organización la publica (US-036). Retirar una publicada deja una lápida visible y el sello auditable (US-037).
- **Sellado por raíz de Merkle:** una transacción por reporte; pruebas de inclusión por archivo guardadas en BD y usadas por el validador (US-024) sin que el archivo salga del navegador (R-BLK-05).
- **Modelo de participación CERRADO:** solo veedores invitados por una organización aportan evidencia. El ciudadano abierto/anónimo no entra ni al MVP ni al roadmap. Un ciudadano participa uniéndose (por invitación) a una organización.
- **Privacidad en origen:** el archivo crudo del sensor nunca se publica; la PWA purga EXIF antes de calcular el hash, y ese archivo sanitizado es el que se sube, sella y publica (R-PRIV-01). Protocolo completo en Completitud (SEC).
- **Sellado seguro:** Smart Contract con AccessControl (solo RELAYER_ROLE sella); llave del Relayer custodiada por un servicio gestionado/KMS, nunca en el servidor ni en el repositorio (R-BLK-03, R-BLK-04).
- **Hash en origen:** SHA-256 calculado en el teléfono al capturar; el backend lo recalcula y compara antes de sellar (R-HASH-01).
- **Obra vs Contrato:** la ubicación de una obra vive en una ficha de obra propia de GovTrace, separada del contrato SECOP (inmutable). First-Touch Anchoring escribe solo en la ficha de obra.
- **Ficha de obra POR ORGANIZACIÓN:** cada organización tiene su propia ficha (ubicación First-Touch fijada por su primer reporte, corregible por su administrador — US-035, estado de riesgo). Dos organizaciones que vigilan la misma obra no se afectan entre sí.
- **Retiro de contenido:** nunca borrado físico en BD; revocación lógica (soft delete / estado de revocación) desde el Día 1.
- **Tenancy:** tenant = Organización (ONG, Cámara de Comercio). Subdominio, veedores y evidencias propios por organización. Contratos SECOP II en BD central compartida. Cada organización configura qué ciudades/departamentos ve en su mapa.

## Respuestas registradas

| # | Fase | Pregunta | Respuesta confirmada |
|---|---|---|---|
| 0 | Arranque | Nombre del usuario | Cristhian Barros ✅ |
| 1 | Épicas | Criterio para agrupar épicas | A) Por flujo — una épica por cada uno de los 5 flujos del caso ✅ |
| — | Épicas | (Pregunta del usuario) ¿Dónde entra el manejo de cuentas de Ciudadanos, Veedores y ONG? | Se decide en Épicas P2; también se sondea en Historias (por actor) y en Completitud (USR, SEC) |
| 2 | Épicas | ¿Cómo tratar la gestión de cuentas y roles? | A) Épica propia (EPIC-006), a iterar a fondo. Supuestos del usuario anotados en ideas_por_explorar ✅ |
| 3 | Épicas | ¿Falta alguna épica? | A, B y C: Moderación (EPIC-007), Denuncias (EPIC-008), Gestión de tenants (EPIC-009). El usuario pide iterar qué representa un tenant ✅ |
| 4 | Épicas | ¿Qué representa un tenant? | Una Organización (ONG, Cámara de Comercio). Cada una: subdominio propio, veedores propios, evidencias propias. Contratos SECOP en BD central compartida. Cada organización configura qué ciudades/departamentos ve en su mapa ✅ |
| 5 | Épicas | VUIFED EPIC-001 · Valor + Usuarios | Valor 🔴 Crítico (10) · Usuarios 🔴 los 3 tipos (10) ✅ |
| 6 | Épicas | VUIFED EPIC-001 (cierre) | V10 · U10 · I10 · F10 · E10 · D7 → 57 / 9.5 ✅ |
| 7 | Épicas | VUIFED EPIC-002 (cierre) | V10 · U10 · I10 · F10 · E10 · D5 → 55 / 9.17. Dependencias ajustada de 10 a 5 por la relación con EPIC-001 y EPIC-006 (nota asesor aceptada) ✅ |
| 8 | Épicas | VUIFED EPIC-003 (cierre) | V10 · U10 · I10 · F7 · E5 · D5 → 47 / 7.83 ✅ |
| 9 | Épicas | VUIFED EPIC-004 (cierre) | V7 · U10 · I7 · F10 · E10 · D5 → 49 / 8.17. Dependencias ajustada de 7 a 5 por la relación con EPIC-001 y EPIC-002 (nota asesor aceptada) ✅ |
| 10 | Épicas | VUIFED EPIC-005 (cierre) | V10 · U10 · I10 · F7 · E7 · D5 → 49 / 8.17 ✅ |
| 11 | Épicas | VUIFED EPIC-006 (cierre) | V10 · U7 · I10 · F10 · E5 · D10 → 52 / 8.67. Esfuerzo ajustado de 10 a 5 por las incógnitas abiertas (nota asesor aceptada) ✅ |
| 12 | Épicas | VUIFED EPIC-007 (cierre) | V7 · U7 · I7 · F10 · E10 · D5 → 46 / 7.67. Dependencias ajustada de 10 a 5 por la relación con EPIC-002 y EPIC-006 (nota asesor aceptada) ✅ |
| 13 | Épicas | VUIFED EPIC-008 (cierre) | V10 · U7 · I10 · F10 · E7 · D5 → 49 / 8.17 ✅ |
| 14 | Épicas | VUIFED EPIC-009 (cierre) | V10 · U7 · I10 · F10 · E7 · D10 → 54 / 9.0 ✅ |
| 15 | Épicas | ¿Qué épicas entran al MVP? | D) 7 épicas: 001, 002, 003, 004, 005, 006, 009. Fuera: EPIC-007 Moderación, EPIC-008 Denuncias. Justificaciones recibidas (ver abajo) ✅ |
| 16 | Épicas | Justificación EPIC-007 fuera del MVP | "Al iniciar con un grupo cerrado de veedores confiables o administradores de tenant, la moderación automatizada o de múltiples niveles no es necesaria el Día 1; se puede hacer directamente en base de datos si hay que borrar algo." → ajustado tras nota asesor (ver fila 18) ✅ |
| 17 | Épicas | Justificación EPIC-008 fuera del MVP | "Aunque es una idea brillante, requiere maquetar documentos legales complejos en PDF y flujos de envío externo que consumen tiempo mejor invertido en pulir el sellado blockchain y el mapa." Detalle nuevo: la denuncia se radica ante la Contraloría. ✅ |
| 18 | Épicas | (Nota asesor) ¿Borrado directo en BD el Día 1? | No. Retiro de contenido mediante **revocación lógica** (soft delete / estado de revocación) desde el Día 1: mantiene la integridad y la promesa blockchain sin la complejidad de EPIC-007. Detalle a explorar en Historias / Completitud (AUD) ✅ |
| 19 | Épicas | Checkpoint de transición | Aceptado (el usuario avanzó a Historias) ✅ |
| 20 | Historias | Actores del sistema (aportados por el usuario) | 4 actores: Super Administrador (nuevo), Administrador de Organización, Veedor Ciudadano, Verificador Anónimo/Público (sin registro) ✅ |
| 21 | Historias | ¿Por dónde empezar la descomposición? | A) Cimientos primero: EPIC-009 → EPIC-006 → resto por dependencia ✅ |
| 22 | Historias | EPIC-009: qué puede / no puede el Super Administrador | 4 historias (US-001..US-004) + 2 restricciones (R-SA-01, R-SA-02) ✅ |
| 23 | Historias | INVEST: ¿cómo manejar US-003? | A) Dividir en US-003a (suspender/reactivar: impagos o revisiones de seguridad) y US-003b (baja definitiva con políticas de retención de datos) ✅ |
| 24 | Historias | ¿Alta y aprobación de una organización son pasos distintos? | A) Son lo mismo: el alta con datos válidos deja la organización aprobada y activa ✅ |
| 25 | Historias | EPIC-006: roles y permisos del tenant | 2 roles (Admin de Organización, Veedor de Campo). 6 historias (US-005..US-010) + 5 restricciones (R-TA-01/02, R-VC-01/02/03). Restricción de proyecto: MVP de 5 semanas. Preferencia técnica: middleware estándar de Laravel + Spatie ✅ |
| 26 | Historias | (Nota asesor) ¿NIT editable por el Admin de Organización? | No. US-007 queda en nombre de fantasía + logo. NIT/datos legales solo los actualiza el Super Administrador a solicitud formal → nueva US-011 y regla R-TA-03 ✅ |
| 27 | Historias | Tipos de archivo de evidencia en el MVP (US-009) | B) Fotografías y documentos PDF ✅ |
| 28 | Historias | ¿Quién define el territorio de una organización? | A) El Administrador de Organización, desde su panel → US-012 *(redacción pendiente de confirmar)* |
| 29 | Historias | Redacción US-012 | Confirmada con "y las obras disponibles": el territorio de la organización limita el mapa y las obras que el veedor puede reportar → regla R-VC-04 ✅ |
| 30 | Historias | EPIC-001: historias de la sincronización SECOP II | 5 historias (US-013..US-017; US-016 → EPIC-002, US-017 → EPIC-004) + R-SEC-01 (inmutabilidad de origen) y R-SEC-02 (límite de extracción) ✅ |
| 31 | Historias | (Nota asesor) Coordenadas de las obras (SECOP II no las trae) | Resuelto por el usuario: GPS del dispositivo en US-008 (navigator.geolocation); si el contrato no tiene coordenadas, el primer reporte las fija (First-Touch Anchoring, HITL) → R-GEO-01 *(conflicto con R-SEC-01 pendiente)* |
| 32 | Historias | ¿Dónde se guarda la ubicación fijada por el primer reporte? | A) En una ficha de obra propia de GovTrace, separada del contrato SECOP; R-SEC-01 se cumple ✅ |
| 33 | Historias | EPIC-002: ¿faltan historias? | US-018 Offline-First (cola local + sincronización al volver la señal) y US-019 sugerencia de obras por proximidad (~500 m). Notas técnicas registradas ✅ |
| 34 | Historias | (Nota asesor) ¿Dónde se calcula el hash? | En el teléfono al capturar (Web Crypto API, SHA-256), guardado con el archivo en IndexedDB; el backend recalcula y compara antes del sellado → R-HASH-01 ✅ |
| 35 | Historias | EPIC-003: historias del sellado | US-020 (encolar y sellar vía Relayer), US-021 (reintentos con backoff), US-022 (saldo del Relayer + alertas), US-023 (Recibo de Inmutabilidad) + R-BLK-01 (abstracción Web3 total) y R-BLK-02 (payload mínimo on-chain) ✅ |
| 36 | Historias | (Nota asesor) ¿Quién escribe en el Smart Contract y cómo se custodia la llave? | AccessControl de OpenZeppelin con onlyRole(RELAYER_ROLE) → R-BLK-03; llave en relayer gestionado / KMS (Defender, Gelato o AWS KMS), nunca en .env → R-BLK-04. Historias US-020..US-023 confirmadas ✅ |
| 37 | Historias | EPIC-005: historias de la verificación | US-024 (validador en el navegador), US-025 (Recibo público), US-026 (descarga del original exacto) + R-VER-01 (cálculo solo cliente) y R-VER-02 (sin registro) ✅ |
| 38 | Historias | (Nota asesor) ¿Publicar el archivo original exacto? | No. Sanitización EXIF en el teléfono antes del hash; se sella y publica el archivo sanitizado → US-026 ajustada, R-PRIV-01. Protocolo completo a Completitud (SEC) ✅ |
| 39 | Historias | EPIC-004: historias del mapa | US-027 (pines por color de estado), US-028 (filtros), US-029 (línea de tiempo de evidencias) + R-MAP-01 (mapa aislado por tenant, sin mapa global) y R-MAP-02 (carga diferida) ✅ |
| 40 | Historias | (Nota asesor) "Evidencias aprobadas" en US-029 sin moderación en el MVP | Se resuelve en Criterios: regla de auto-moderación simple o estado por defecto para el MVP ✅ |
| 41 | Historias | Documento externo `~/Downloads/govtrace_decisiones_de_dise_o_funcional_fase_1_y_2.md` | Difiere del registro de sesión → A) Fuente de verdad = registro de la sesión. Las novedades del documento se presentan como candidatas a confirmar o descartar ✅ |
| 42 | Historias | Candidatas del documento externo | C1→US-030 aceptar invitación ✅ · C2→US-031 iniciar sesión ✅ · C3→US-032 solo tipo Obra ✅ · C4→US-033 sincronización incremental ✅ · C5→US-034 Job diario de riesgo por fecha fin (ajustada) ✅ · C6→ no es historia: US-001 incluye asignar subdominio + R-SA-03 ✅ |
| 43 | Historias | Método de priorización | C) Por niveles P1 (sem 1-2), P2 (sem 3-4), P3 (sem 5+), para traducir prioridad en bloques de tiempo reales ✅ |
| 44 | Historias | Prioridad P1/P2/P3 de las 35 historias | P1: 16 · P2: 13 · P3: 6. US-026 subida de P3 a P2 tras nota asesor (validar exige el archivo exacto sellado) ✅ |
| 45 | Historias | INVEST: ¿dividir US-020? | A) US-020a (Smart Contract con control de acceso) + US-020b (encolar y sellar vía relayer), ambas P1 → 36 historias; P1 = 17 ✅ |
| 46 | Historias | Checkpoint de transición | Aceptado ✅ |
| 47 | Criterios | Ritmo de trabajo | C) Por bloques de historias relacionadas (12 bloques) ✅ |
| 48 | Criterios | B1 — Alta y gobierno de organizaciones | Camino feliz, validaciones (NIT único, subdominio slug ≥3 y no reservado, nombre 3-150, correo RFC 5322 único), 6 mensajes de error, 2 casos límite (403 por suspensión, baja lógica sin tocar la blockchain). YAML: US-001, 002, 011, 003a, 003b ✅ (DV del NIT pendiente) |
| 49 | Criterios | B1 — ¿Cómo se valida el NIT? | B) Dígito de verificación obligatorio, comprobado con el algoritmo oficial de la DIAN ✅ |
| 50 | Criterios | B1 — Mensaje de NIT con DV inválido | "El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN." → B1 completo ✅ |
| 51 | Criterios | B2 — Configuración de la organización | Nombre de fantasía 3-100; logo PNG/JPG/SVG ≤2 MB (mín. 128x128 recomendado — pendiente); territorio ≥1 con códigos DIVIPOLA; 4 mensajes; caso límite: quitar ciudad no borra nada, solo deja de mostrarse y sincronizarse. YAML: US-007, US-012 ✅ |
| 52 | Criterios | B2 — Logo menor de 128x128 | A) Se rechaza con mensaje "La imagen es demasiado pequeña. Las dimensiones mínimas requeridas son de al menos 128x128 píxeles." → B2 completo ✅ |
| 53 | Criterios | B3 — Acceso y equipo | Login por subdominio/global con redirección por rol; invitación con token de 48 h; contraseña ≥8 con mayúscula, minúscula, número y símbolo; correo único por organización; desactivación revoca sesiones; 5 mensajes; casos límite: reportes previos intactos, cola offline recibe 403. YAML: US-031, 005, 030, 006 ✅ |
| — | Criterios | (Duda del usuario) Estructura organización → veedor | "Inicialmente mi idea era que cualquier ciudadano pueda aportar evidencia (pero el registro podría ocasionar fricción)". Se reabre el modelo de participación; B4 en pausa |
| 54 | Criterios | ¿Quién puede aportar evidencia? | A) Solo veedores invitados por una organización; la participación ciudadana abierta no entra ni al MVP ni al roadmap. Lo confirmado se mantiene ✅ |
| 55 | Criterios | B4 — Sincronización SECOP | Corrida nocturna 02:00 por territorios DIVIPOLA de orgs activas vía SODA; filtro tipo "Obra"; upsert idempotente con índice único; panel con inicio/fin, estado Success/Warning/Failed, totales y nuevos vs. actualizados por org; fallo de API → reintento con backoff; anulados → cancelled sin borrar si tienen evidencia; sincronización inmediata al activar org o cambiar territorio. YAML: US-013, 032, 033, 014 — 4 pendientes (ver fila siguiente) |
| 56 | Criterios | B4 — pendientes | 1A contratos en BD central compartida (se mantiene la decisión de tenancy) · 2A clave única id_contrato · 3A anulados sin evidencia se conservan como cancelled · 4A el mensaje muestra el tiempo real del próximo reintento → B4 completo ✅ |
| 57 | Criterios | B5 — Contratos para los usuarios | Tabla de 20 con 6 columnas, orden por fecha de firma desc.; búsqueda ≥3 caracteres con debounce 300 ms; solo contratos En ejecución/Celebrado/Adjudicado; tarjeta pública del contrato con enlace a SECOP; badge rojo si el contrato fue anulado; 2 mensajes. YAML: US-015, 016, 017 — 2 pendientes |
| 58 | Criterios | B5 — pendientes | 1A un departamento incluye Gobernación y todos sus municipios; se suman todos los territorios · 2C (nota asesor aceptada) Terminados/Liquidados siguen seleccionables hasta 12 meses tras su terminación/liquidación oficial → B5 completo ✅ |
| 59 | Criterios | B6 — Captura de evidencia | Flujo: contrato → GPS automático → clasificación obligatoria → comentario opcional ≤500 → 1-5 archivos ≤10 MB; EXIF purgado y SHA-256 en el teléfono; mensaje de éxito; GPS accuracy ≤50 m; geocerca 500 m si la obra ya está anclada; 3 mensajes de error; carrera First-Touch con bloqueo atómico. YAML: US-008, 009 — 3 pendientes |
| 60 | Criterios | B6 — pendientes | 1A clasificación: Avance · Retraso · Abandono · 2B fotos (1-5) o un único PDF, sin mezclar · 3B (nota asesor) el Admin de Organización puede corregir la ubicación de una obra con registro → nace US-035 → B6 completo ✅ |
| 61 | Criterios | B6 — US-035 | 1A redacción aceptada · 2A ficha de obra POR ORGANIZACIÓN (ubicación y riesgo propios, sin afectar a otras) · 3A prioridad P1 ✅ |
| 62 | Criterios | B7 — Sellado + US-035 | US-035: pin arrastrable o lat/lng, log inmutable (admin, fecha, antes/después), mensaje de confirmación. Sellado: estados Recibida → En Cola → Transmitiendo → Sellada (≥3 confirmaciones); contrato rechaza hash duplicado; máx. 5 intentos → Falla de Sellado con banner al Admin (veedor no ve errores); relayer sin saldo pausa y alerta; reorg → job nocturno re-encola. Conflicto: "Hash Raíz" por reporte vs. verificación de foto individual (US-024) *(pendiente)* |
| 63 | Criterios | B7 — ¿Qué se sella en Polygon? | B) Una raíz de Merkle por reporte (1 transacción); pruebas de inclusión por archivo en PostgreSQL; el validador pide la prueba al API, calcula el hash local y recompone la raíz → R-BLK-05 ✅ |
| 64 | Criterios | B7 — hoja de metadatos | Sí: hoja adicional con el hash del JSON (lat/lng, comentario, timestamp, ID del veedor) → B7 completo ✅ |
| 65 | Criterios | B8 — Mapa y publicación | Publicación MANUAL: toda evidencia nace Oculta y el Admin pulsa "Publicar"; colores verde/amarillo/rojo; rojo por fecha vencida + "En ejecución" en SECOP o por Abandono; timeline con miniaturas y botón "Verificar Sello Blockchain"; revocar publicada = lápida con mensaje; filtros y mensaje vacío. YAML: US-034, 027, 029, 028 — 4 pendientes |
| 66 | Criterios | B8 — pendientes | 1B nacen US-036 (publicar) y US-037 (retirar con lápida), ambas P1 por bloqueantes · 2A amarillo solo por Retraso · 3A manda la evidencia publicada más reciente · 4A filtros: estado + fechas + presupuesto + municipio → B8 completo ✅ |
| 67 | Criterios | B9 — Verificación + US-036/037 | US-036: bandeja de ocultas, publicar 1 por 1 con mensaje, botón Rechazar (sin lápida). US-037: motivo obligatorio en log, lápida, retiro definitivo. US-024: banners Auténtico/Alterado con Polygonscan, JPG/PNG/PDF ≤10 MB, mensajes No encontrado y error de RPC, lápida sigue verificando. US-026: descarga desde la tarjeta del timeline. YAML: US-036, 037, 024, 026 — 1 pendiente |
| 68 | Criterios | B9 — ¿Cuándo "Alterado" vs "No encontrado"? | A) Dos modos: contextual desde la evidencia (Auténtico/Alterado) y libre (Auténtico/No encontrado). Además, principio: si una evidencia oculta/rechazada se selló, el validador libre debe decir "Auténtico" (detalle en Completitud) → B9 completo ✅ |
| 69 | Criterios | B10+B11 — Recibos, seguimiento y monitoreo | US-010: estados técnico y editorial separados ("En Revisión" agrupa Oculto y Rechazado). Recibo: Merkle Root, TxID, bloque, fecha/hora, Polygonscan; mensaje de espera; tras reorg solo la nueva TxID. US-022: saldo cada 15 min, umbral < 5 POL, Email + Webhook, mensaje. US-004: tabla por mes y organización con costo USD vía API de precios. YAML: 5 — 1 pendiente |
| 70 | Criterios | B10 — Estados visibles para el veedor | A) Recibida + En Cola → "En Cola"; Transmitiendo → "Sellando"; Sellada → "Sellado"; Falla de Sellado → "En Cola" → B10 y B11 completos ✅ |
| 71 | Criterios | B12 — Offline y proximidad | US-018: toast de guardado sin conexión, bandeja de salida con contador, envío en segundo plano; máx. 10 reportes / ~50 MB; TTL 7 días; lat/lng/timestamp congelados al capturar; 2 mensajes; modal al cerrar sesión con pendientes. US-019: máx. 5 obras en 500 m por cercanía, mismas reglas de US-016, mensaje sin resultados. YAML: US-018, 019 — 1 pendiente |
| 72 | Criterios | B12 — Contrato anulado mientras el reporte esperaba | B) (nota asesor aceptada) Manda la captura: se acepta, se sella y entra Oculto; el Admin decide si publica → B12 completo ✅ |
| 73 | Criterios | Checkpoint de transición | Aceptado ✅ |
| 74 | Completitud | CFG P1 — Red de sellado del MVP | C) Testnet en desarrollo y pruebas; mainnet al salir a producción → R-CFG-01 ✅ |
| 75 | Completitud | CFG P2 — Dónde vive cada parámetro | Geocerca B, GPS 50 m A, ventana 12 meses B, archivos A, TTL offline A, invitación 48 h B, umbral 5 POL B, hora 02:00 B → R-CFG-02 y gap US-038-CFG (panel de parámetros del Super Admin) ✅ |
| 76 | Completitud | USR — gap US-038-CFG | ⚡ Importante, P2 ✅ |
| 77 | Completitud | USR P1 — Contraseña olvidada | A) La restablece el propio usuario con enlace por correo → gap US-039-USR ✅ |
| 78 | Completitud | USR P2 — Mismo correo en dos organizaciones | A) Permitido: una cuenta por organización → R-USR-01 ✅ |
| 79 | Completitud | USR P3 — Invitaciones y reactivación | A) Reenviar/revocar invitaciones y reactivar veedores → gaps US-040-USR y US-041-USR ✅ |
| 80 | Completitud | USR P4 — Veedor con evidencia rechazada | C) Ve "Rechazada" con el motivo del Admin → R-USR-02; ajustados US-010 y US-036 ✅ |
| 81 | Completitud | USR P5 — Reporte offline por vencer | B) La app avisa antes de descartarlo (detalle pendiente) → US-018 ✅ |
| 82 | Completitud | USR P6 — Organización suspendida con reportes offline | B) Se conservan y se envían si se reactiva dentro de 7 días → R-USR-03; ajustado US-003a ✅ |
| 83 | Completitud | Clasificación gaps USR | US-039-USR ⚡ P2 · US-040-USR 💡 P3 · US-041-USR ⚡ P2 ✅ |
| 84 | Completitud | Aviso antes de descartar reporte offline | 24 h antes: "⚠️ Tu reporte pendiente de sincronización expirará en 24 horas. Conéctate a una red para enviarlo antes de que se descarte." ✅ |
| 85 | Completitud | SEC P1 — Rostros y placas | A) Se publican tal cual; la organización decide al revisar → R-PRIV-05 (riesgo aceptado) ✅ |
| 86 | Completitud | SEC P2 — Coordenadas públicas de la evidencia | B) Aproximadas (~100 m) → R-PRIV-02 ✅ |
| 87 | Completitud | SEC P3 — JSON de metadatos | C) ID del veedor reemplazado por seudónimo antes de sellar; se publica → R-PRIV-03 ✅ |
| 88 | Completitud | SEC P4 — Metadatos de los PDF | B) La app los limpia antes del hash → R-PRIV-04 ✅ |
| 89 | Completitud | SEC P5 — Validador libre ante evidencia oculta/rechazada sellada | B) "Auténtico" + nota de que la organización no la ha publicado (texto pendiente) ✅ |
| 90 | Completitud | (Propuesta del usuario) Optimizar fotos antes del hash | Redimensionar/comprimir en el teléfono antes del hash; parámetros por confirmar *(pendiente)* |
| 91 | Completitud | Optimización de fotos | A) Lado mayor 1920 px, JPEG, calidad 80 %, en el teléfono antes del hash → R-PRIV-06 ✅ |
| 92 | Completitud | Texto del validador ante evidencia no publicada | A) "✅ Archivo Auténtico. (Nota: esta evidencia existe en la blockchain pero la organización aún no la ha publicado)." ✅ |
| 93 | Completitud | SEC P1 — Intentos fallidos de login | B) 5 intentos → bloqueo de 15 min → R-SEC-03 (mensaje pendiente) ✅ |
| 94 | Completitud | SEC P2 — Logo SVG | B) Se acepta y se limpia de contenido ejecutable → R-SEC-04 ✅ |
| 95 | Completitud | SEC P3 — Teléfono comprometido | B) El servidor registra su hora de recepción y marca diferencias grandes → R-SEC-05 (umbral pendiente) ✅ |
| 96 | Completitud | SEC P4 — Raíz de Merkle | C) El servidor calcula su propia raíz y pruebas; ignora la del teléfono → R-SEC-06; ajustado US-020b ✅ |
| 97 | Completitud | SEC P5 — "Consentimiento" de R-SA-02 | B) Solo con autorización del Admin de la organización en el sistema, con registro → gap US-042-SEC ✅ |
| 98 | Completitud | Mensaje de bloqueo por intentos fallidos | "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos." ✅ |
| 99 | Completitud | Umbral R-SEC-05 | A) Se marca hora de captura en el futuro o anterior a 7 días; ven la marca el Admin de Organización y el Super Admin ✅ |
| 100 | Completitud | Clasificación US-042-SEC | ⚡ Importante, P2 ✅ |
| 101 | Completitud | AUD P1 — Organización suspendida | C) Mapa y evidencias visibles con aviso de "organización suspendida" → R-AUD-01 (texto pendiente) ✅ |
| 102 | Completitud | AUD P2 — Organización dada de baja | B) Mapa fuera de línea; evidencias verificables en el validador libre → R-AUD-02 ✅ |
| 103 | Completitud | AUD P3 — Retención de archivos tras la baja | B) 5 años, luego se borran los archivos; el sello permanece → R-AUD-03 ✅ |
| 104 | Completitud | AUD P4 — Territorio sin organizaciones activas | B) → corregido a A por conflicto con R-SEC-02 (ver fila 108) ✅ |
| 105 | Completitud | AUD P5 — Acciones en el log de auditoría | Todas (a–h) → R-AUD-04 ✅ |
| 106 | Completitud | AUD P6 — Cambio de radio con reportes pendientes | B) Se valida con el radio vigente en la captura → R-AUD-05 ✅ |
| 107 | Completitud | Aviso público de organización suspendida | "⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta." ✅ |
| 108 | Completitud | Conflicto 4B vs R-SEC-02 | A) Se mantiene R-SEC-02: los contratos se conservan pero dejan de actualizarse → R-AUD-06 ✅ |
| 109 | Completitud | MON P1 — Quién consulta el log | B) Super Admin todo; cada Admin de Organización lo de la suya → gap US-043-MON ✅ |
| 110 | Completitud | MON P2 — Fallas técnicas | B) Herramienta de monitoreo externa que alerta al Super Admin → gap US-044-MON ✅ |
| 111 | Completitud | MON P3 — Cola de sellado atascada | C) Más de 2 h en cola → alerta al Super Admin y al Admin afectado → R-MON-01 (texto pendiente) ✅ |
| 112 | Completitud | MON P4 — Reportes con hora sospechosa | A) Solo marca; decide el Admin → R-MON-02 ✅ |
| 113 | Completitud | Texto de alerta de cola atascada | "⚠️ Alerta de Sistema: Hay evidencias con más de 2 horas estancadas en la cola de sellado. Revisa el estado de la red o del proveedor RPC." ✅ |
| 114 | Completitud | Clasificación US-043-MON y US-044-MON | Ambas ⚡ Importante, P2 ✅ |
| 115 | Completitud | INT P1 — Relayer gestionado caído | A) Espera y reintenta (US-021) → R-INT-01 ✅ |
| 116 | Completitud | INT P2 — Proveedor de mapas | B) OpenStreetMap → R-INT-02 ✅ |
| 117 | Completitud | INT P3 — API de precios caída | A) Último precio conocido con su fecha → R-INT-03 ✅ |
| 118 | Completitud | INT P4 — Municipio SECOP distinto a DIVIPOLA | B) Emparejamiento normalizado; lo no emparejado se descarta y reporta en US-014 → R-INT-03 ✅ |
| 119 | Completitud | INT P5 — Varios contratos de una misma obra | B) El Admin puede agruparlos en una ficha → gap US-045-INT, R-INT-05 (clasificación pendiente) ✅ |
| 120 | Completitud | INT P6 — GovTrace caído y verificación | B) Cada descarga incluye su prueba de inclusión → R-INT-04; ajustado US-026 ✅ |
| 121 | Completitud | Clasificación US-045-INT | ⚡ Importante, P2 ✅ |
| 122 | Completitud | Verificar sin GovTrace | C) El validador acepta archivo + prueba (US-024) y hay un script independiente en el repositorio → gap US-046-INT ✅ |
| 123 | Completitud | MNT P1 — Evidencias en Falla de Sellado | A) El Super Admin las re-encola desde su panel → gap US-047-MNT ✅ |
| 124 | Completitud | MNT P2 — Nueva versión del Smart Contract | A) El validador consulta todas las direcciones históricas → R-MNT-01 ✅ |
| 125 | Completitud | MNT P3 — Pruebas tras borrar archivos | A) Se conservan para siempre → R-MNT-02 ✅ |
| 126 | Completitud | MNT P4 — Retención de log y seudónimos | C) Log para siempre; tabla de seudónimos 5 años → R-MNT-03 ✅ |
| 127 | Completitud | MNT P5 — Crecimiento de contratos | B) Sin evidencias y cerrados hace más de 5 años se archivan → R-MNT-04, gap US-048-MNT ✅ |
| 128 | Completitud | Clasificación US-046-INT, US-047-MNT, US-048-MNT | 046 ⚡ P2 · 047 💡 P3 · 048 ⚡ P2 ✅ |
| 129 | Completitud | RPT P1 — Reportes del Admin de Organización | C) Resumen del territorio + exportación CSV → gaps US-049-RPT, US-050-RPT ✅ |
| 130 | Completitud | RPT P2 — Reportes públicos | C) Estadísticas públicas + datos abiertos CSV/JSON → gaps US-051-RPT, US-052-RPT ✅ |
| 131 | Completitud | RPT P3 — Reportes del Super Admin | C) Resumen de uso por organización + alerta de inactividad a 30 días → gaps US-053-RPT, US-054-RPT ✅ |
| 132 | Completitud | Prioridad de gaps RPT | 049 P2 · 050 P3 · 051 P3 · 052 P3 · 053 P3 · 054 P3 (clasificación 🔥/⚡/💡 pendiente) ✅ |
| 133 | Completitud | Clasificación gaps RPT | ⚡ las P2 (US-049-RPT), 💡 las P3 (US-050..054-RPT) ✅ |
| 134 | Completitud | BCK P1 — Pérdida de datos aceptada | B) Hasta 1 hora → R-BCK-01 ✅ |
| 135 | Completitud | BCK P2 — Tiempo fuera de servicio | B) Hasta 4 horas → R-BCK-02 ✅ |
| 136 | Completitud | BCK P3 — Archivos de evidencia | B) Respaldo periódico como la base → R-BCK-03 ✅ |
| 137 | Completitud | BCK P4 — Retención de copias | B) 30 días → R-BCK-04 ✅ |
| 138 | Completitud | BCK P5 — Pruebas de restauración | A) No en el MVP → R-BCK-05 ✅ |
| 139 | Completitud | (Nota asesor) Restauración de prueba | B) Una restauración de prueba antes de salir a producción → R-BCK-05 ajustada ✅ |
| 140 | Completitud | TST P1 — Pruebas del sellado | C) Mock en unitarias + prueba de humo en testnet antes de cada salida a producción → R-TST-01 ✅ |
| 141 | Completitud | TST P2 — Pruebas de SECOP | A) Respuestas grabadas (fixtures) con variantes raras → R-TST-02 ✅ |
| 142 | Completitud | TST P3 — Captura y offline | B) Vitest + e2e en navegador real simulando pérdida de señal → R-TST-03 ✅ |
| 143 | Completitud | TST P4 — Reglas no testeables | A) Ninguna: todas con escenario automático → R-TST-04 ✅ |
| 144 | Completitud | Cierre de áreas | 10/10 áreas recorridas. 17 gaps escritos (historia + YAML). Pendiente: ronda de cierre para SMART de 8 gaps y 3 cabos abiertos |
| 145 | Completitud | Cierre A | 1C Terminados/Liquidados: mismas reglas de color, verde sin evidencias · 2B banner genérico sin "congestión de red" · 3A al quitar una ciudad, lo publicado sigue en el mapa público · 4 enlace de restablecimiento 1 h + respuesta neutra · 5A autorización al Super Admin por 30 días, revocable · 6B monitoreo: caídas > 5 min por Email y Webhook · 7 reporte vinculado a la ficha; pin con el peor estado · 8A archivado mensual, vuelve si llega evidencia ✅ |
| 146 | Completitud | Cierre B | Mensajes de US-039 y US-042; CSV con comentario y seudónimo (3B); datos abiertos con comentario y seudónimo (4B); inactividad = recibir o publicar evidencias, alerta por Email (5 C/A) → 56/56 YAML con SMART completo ✅ |
| 147 | Completitud | Checkpoint de transición | Aceptado ✅ |
| 148 | Gherkin | Generación y cierre | 56 .feature, dqs-lite.md, SPEC.md consolidado; discovery completo → siguiente paso /plan ✅ |
| 149 | Cierre | ¿Rebalancear P2 antes de /plan? | No: se deja que /plan muestre la carga real por iteración ✅ |

## Discovery corto — "Encontrar la obra en campo" (2026-10-10)

> Pedido por el usuario tras la prueba de staging (it. 42b): V18 y V19 de `docs/mapa-funcional.md`, más la búsqueda por GPS. Modo asesor. Toca EPIC-002 (Recolección de evidencia): enmienda US-016, US-019 y, en el GPS, US-008.

```yaml
mini_discovery:
  id: encontrar-la-obra-en-campo
  iniciado: 2026-10-10
  epica: EPIC-002
  alcance:
    - "V18: las obras sin ubicación del municipio donde está el veedor"
    - "V19: Buscar Obra con filtros (municipio, tipo de obra, situación, entidad), tope, orden, sin tildes"
    - "GPS: esperar una buena lectura, no buscar con una mala, leer una sola vez"
  datos_medidos:
    - "UNSPSC en contratos de obra de Antioquia desde 2023 (familias): 7210 mantenimiento 911, 7214 construcción pesada 555, 8110 servicios de ingeniería 463, PECI 455, 7215 oficios especializados 242, 7212 edificaciones no residenciales 226. Ruidoso: redes de acueducto y viviendas caen en 7210, una cancha en 7215"
    - "veeduria-prueba (Antioquia): 1.111 contratos reportables; 915 en Medellín (2026-10-10)"
    - "SECOP II, 525 contratos de obra de Magdalena desde 2023: codigo_de_categoria_principal, direcci_n_de_ejecuci_n_del_contrato, objeto_del_contrato, valor_pagado y dias_adicionados al 100 %; fecha_de_inicio_del_contrato al 75 %; la dirección de ejecución suele ser la de la entidad"
  current_phase: completed   # 21 escenarios nuevos; it. 42c y 47 aprobadas (2026-10-10)
  decisiones:
    - {n: 1, tema: "Qué ve primero el veedor al abrir Nuevo reporte", decision: "A) Las obras de su municipio (por su GPS), en una lista con filtros, y el buscador encima"}
    - {n: 1b, tema: "Modo sin señal", decision: "Fuera de este alcance (el usuario, 2026-10-10). Hallazgo: hoy, sin señal, el veedor no puede elegir la obra (Buscar Obra y Obras cercanas necesitan red). Anotado como V20 en docs/mapa-funcional.md"}
    - {n: 2, tema: "Filtros de la lista, además del municipio", decision: "A) Tipo de obra, B) Situación del contrato, C) Entidad contratante. Sin filtro de valor"}
    - {n: 3, tema: "Orden de la lista", decision: "A) Primero las de plazo vencido; luego las en ejecución que vencen más pronto", porque: "son las que habría que hacerles seguimiento (el usuario)"}
    - {n: 4, tema: "Cuántas obras de una vez", decision: "A) Las primeras 20, con un botón \"Ver más\" para las siguientes 20"}
    - {n: 5, tema: "Si el GPS no dice el municipio (sin permiso, sin buena señal o fuera del territorio)", decision: "A) La lista abre con el primer municipio del territorio (por código DIVIPOLA: la capital, si es un departamento) y le pide elegir el suyo"}
    - {n: 6, tema: "Cómo se decide el tipo de obra", decision: "C) Por palabras del objeto del contrato y, si ninguna encaja, por el código UNSPSC de SECOP II"}
    # Desde aquí, a pedido del usuario ("continúa autónomamente acorde a tu criterio… añades un resumen de tus decisiones para que lo pueda validar"): propuestas de Claude, VALIDADAS por el usuario el 2026-10-10 ("Apruebo todo").
    - {n: 7, por: claude, tema: "Categorías del tipo de obra", decision: "Ocho: vías y puentes · agua y saneamiento · vivienda · educación · salud · deporte y recreación · espacio público · otras. Respaldo UNSPSC solo donde es confiable: 7211 → vivienda, 721411 → vías y puentes; el resto, otras"}
    - {n: 8, por: claude, tema: "Esperar una buena lectura del GPS", decision: "La app sigue el GPS hasta tener 50 m o menos, mostrando la precisión en vivo ('Buscando señal GPS: 120 m. Se necesitan 50 m o menos.'); a los 60 s sin lograrlo explica qué hacer y ofrece 'Intentar de nuevo'"}
    - {n: 9, por: claude, tema: "Leer el GPS una sola vez", decision: "Al abrir Nuevo reporte se enciende el GPS y queda encendido en esa pantalla; las obras cercanas, el municipio y el reporte usan la última lectura. El reporte la usa si es de 50 m o menos y tiene 30 s o menos; su hora de captura es la de esa lectura. Si es más vieja, espera una nueva. El servidor sigue validando lo mismo (US-008)"}
    - {n: 10, por: claude, tema: "No buscar con una mala lectura", decision: "Las obras cercanas (500 m) exigen una lectura de 50 m o menos; mientras no la hay, muestra 'Buscando señal GPS…' en lugar de 'no hay obras'. El municipio se decide con una lectura de hasta 5 km: alcanza para saber el municipio"}
    - {n: 11, por: claude, tema: "Dónde quedan las obras cercanas", decision: "Encabezan la lista, en 'Cerca de usted' (hasta 5, ya ubicadas, a 500 m o menos, con su distancia); debajo, la lista del municipio"}
    - {n: 12, por: claude, tema: "Obras sin ubicación (V18)", decision: "Están en la lista del municipio como cualquier otra, con la marca 'Sin ubicación todavía'. Las cubre la lista: no hace falta otra pantalla"}
    - {n: 13, por: claude, tema: "La búsqueda de texto", decision: "Busca dentro del municipio y de los filtros elegidos; sin resultados, ofrece 'Buscar en todo el territorio'. Sin distinguir tildes ni mayúsculas. En el objeto, el contratista, el número de proceso y la entidad. Siguen los 3 caracteres y los 300 ms"}
    - {n: 14, por: claude, tema: "La situación del contrato", decision: "Todas (por defecto) · Plazo vencido (no terminado, con la fecha de fin ya pasada) · En ejecución (no terminado, con la fecha de fin por venir o sin fecha) · Terminada hace poco (terminado o cerrado hace 12 meses o menos, R-SEC-07)"}
    - {n: 15, por: claude, tema: "El orden, en detalle", decision: "Plazo vencido, de la que lleva más días vencida a la que menos; luego en ejecución, de la que vence más pronto a la que más tarde; luego sin fecha de fin; al final, las terminadas hace poco, de la más reciente a la más antigua"}
    - {n: 16, por: claude, tema: "Los selectores", decision: "Municipio: los del territorio que tienen obras reportables, en orden alfabético, con cuántas tiene cada uno. Entidad: las de las obras del municipio, de la que más tiene a la que menos"}
    - {n: 17, por: claude, tema: "Qué muestra cada obra", decision: "El nombre (o el objeto, en dos líneas), la entidad, el tipo de obra, el número de proceso, la situación con su fecha ('Plazo vencido hace 40 días', 'Vence en 3 meses', 'Terminada hace 2 meses') y 'Sin ubicación todavía' o la distancia"}
    - {n: 18, por: claude, tema: "Privacidad de la ubicación", decision: "Como en la it. 45f: la ubicación viaja en el cuerpo de la petición, nunca en la URL; el servidor la usa para decidir el municipio y las cercanas, y no la guarda"}
    - {n: 19, por: claude, tema: "El tipo de obra, cuándo se calcula", decision: "Lo calcula la sincronización con SECOP II (trae codigo_de_categoria_principal) y queda guardado con el contrato; los contratos ya guardados se completan en la próxima sincronización, como en la 46j"}
```
