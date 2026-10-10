# DQS-lite — Auditoría del discovery de GovTrace

> Sesión: `sessions/cristhian-barros/` · Cierre: 2026-09-27 · Insumo: 56 historias con criterios YAML (SMART completo) → 56 archivos `features/*.feature`.

Este reporte evalúa **la calidad del discovery**, no la del código. Responde dos preguntas: ¿se exploraron las 10 áreas del ciclo de vida? y ¿cada regla tiene un escenario que la viola a propósito?

## 1. Cobertura por área de completitud

**⚙️ CFG — Configuración: explorada.** Se decidió qué valores son fijos (precisión GPS, archivos por reporte, vigencia sin conexión) y cuáles ajusta el Super Administrador (geocerca, ventana de 12 meses, vigencia de invitaciones, umbral del Relayer, hora de sincronización). Ninguno es por organización. La red de sellado cambia por entorno: testnet en desarrollo y red principal en producción. Nació US-038-CFG. Queda un matiz sin decidir: los rangos válidos de cada parámetro (p. ej. el radio mínimo de geocerca). No se preguntó, así que no se escribió.

**👥 USR — Usuarios y acceso: explorada, con buen detalle.** El ciclo de vida de la cuenta quedó completo: invitación con vencimiento, activación, inicio de sesión con bloqueo, restablecimiento de contraseña, desactivación y reactivación, y reenviar o revocar invitaciones. Se resolvió el caso del ciudadano en varias organizaciones: una cuenta por organización. También se cambió una decisión anterior: el veedor ahora ve sus rechazos con motivo. Nacieron US-039, US-040 y US-041-USR.

**🔒 SEC — Seguridad y privacidad: explorada a fondo; es el área más trabajada del discovery.**
- La cadena de custodia quedó definida de extremo a extremo: optimización y hash en el teléfono, verificación del hash en el servidor, raíz de Merkle calculada por el servidor, control de acceso on-chain y custodia KMS de la llave.
- La privacidad se resolvió por capas: EXIF, coordenadas aproximadas, seudónimo y metadatos de PDF.
- Quedan dos riesgos aceptados de forma consciente:
  - los rostros y placas **no se difuminan**, y la organización decide al revisar (R-PRIV-05);
  - un teléfono comprometido puede alterar foto y hash antes de subirlos. Esto **solo se mitiga parcialmente**, marcando horas sospechosas (R-SEC-05).

  Ambos quedaron escritos como decisiones, no como huecos.

**🗃️ AUD — Auditoría e integridad: explorada.** Suspensión, baja, retención a 5 años y el log de auditoría con ocho tipos de acción. La publicación manual con lápida resolvió la tensión entre la responsabilidad legal de la organización y la inmutabilidad: nada se borra, todo se retira con rastro. Se detectó y corrigió un conflicto con R-SEC-02.

**📈 MON — Monitoreo: explorada.** Tiene alertas de cola de sellado estancada, saldo del Relayer, fallas de sellado, monitoreo externo de caídas e inactividad de organizaciones. Queda liviano en un punto: la herramienta de monitoreo externa (US-044-MON) solo alerta caídas mayores a 5 minutos. Los errores de aplicación que no tumban el servicio no generan alerta, y así lo decidió el usuario.

**🔗 INT — Integraciones: explorada.** Se cubrió la falla de cada dependencia externa: relayer gestionado, API de precios y SECOP (con emparejamiento DIVIPOLA). Se eligió OpenStreetMap. Dos decisiones reducen la dependencia de GovTrace mismo: la verificación con la prueba de inclusión descargada y el script independiente. Queda **con gaps de información externa**: la vigencia del proveedor de relayer gestionado se marcó para verificar (se mencionó el posible cierre de OpenZeppelin Defender) y no se eligió proveedor.

**🔧 MNT — Mantenimiento: explorada.** Contempla el re-encolado manual de fallas, las versiones del Smart Contract (el validador consulta todas las direcciones históricas), la conservación permanente de las pruebas de inclusión, la retención del log y de los seudónimos, y el archivado de contratos viejos.

**📊 RPT — Reportes: explorada, con alcance amplio.** Seis historias nuevas para las tres audiencias (administrador, público y Super Administrador). Cinco quedaron en P3, lo que protege el MVP.

**💾 BCK — Backup: explorada, pero floja en verificabilidad.** Hay objetivos claros: pérdida máxima de 1 h, recuperación en 4 h, respaldo de archivos y 30 días de retención. Pero **no generaron escenarios Gherkin** porque son requisitos operativos, no comportamiento de la aplicación. Su comprobación depende de la restauración de prueba antes de producción (R-BCK-05), que debe planificarse como tarea de infraestructura en `/plan`.

**🧪 TST — Testing: explorada.** Definió cómo se prueba lo que depende del exterior: blockchain simulada más prueba de humo en testnet, respuestas grabadas de SECOP, y pruebas de extremo a extremo del modo sin conexión. El usuario fijó que **todas** las reglas deben tener su escenario automático (R-TST-04). Esto choca con lo dicho en BCK y con algunas reglas de naturaleza técnica. Ver la sección 2.

## 2. Balance camino feliz / camino negativo

Los 56 `.feature` suman **248 escenarios**: **136 marcados `@negative`** y **36 `@edge`**. Más de la mitad de los escenarios existen para romper una regla a propósito, que es el balance que busca el método. Ningún `.feature` quedó solo con caminos felices.

**Lo que sí tiene su escenario negativo:**
- Todas las reglas "NO puede" de cada rol (R-SA, R-TA y R-VC).
- Las reglas de contratos (R-SEC-01 y R-SEC-02).
- Las de sellado (R-BLK), verificación (R-VER), privacidad (R-PRIV), mapa (R-MAP) y seguridad (R-SEC).
- Todas las validaciones de campo con límites, con escenarios de frontera (p. ej. 127×128 px rechazado y 128×128 aceptado; 50 m de precisión aceptados y 51 m no; 12 meses sí y 13 no).
- Los mensajes de error, copiados literalmente de los criterios. Una verificación automática confirmó que **cada mensaje exacto de los YAML aparece en su `.feature`**.

**Lo que NO tiene escenario Gherkin, y por qué:**
- **R-BCK-01 a R-BCK-04** (pérdida máxima, tiempo de recuperación, respaldo de archivos, retención de copias): son objetivos operativos. Se validan con la restauración de prueba (R-BCK-05), fuera del Gherkin.
- **R-CFG-01** (testnet o red principal según entorno) y **R-INT-02** (OpenStreetMap): son decisiones de configuración y de tecnología, no comportamiento verificable por un escenario de negocio.
- **R-MNT-03** (log para siempre; seudónimos 5 años) y **R-TST-01 a R-TST-03**: son políticas de retención y de estrategia de pruebas.

Esto contradice literalmente R-TST-04 (*"todas las reglas deben tener su escenario automático"*). Hay que resolverlo en `/plan`: esas reglas se verifican con tareas de infraestructura y de CI, o se ajusta R-TST-04 para que hable de "reglas de comportamiento".

## 3. Observaciones para las fases siguientes

- **Tamaño de P2.** El backlog pasó de 13 a 23 historias en P2 (semanas 3-4) por los huecos de Completitud. `/plan` debe dimensionarlo contra el objetivo de 5 semanas.
- **Decisiones técnicas pendientes, anotadas en `project-context.md`:**
  - Horizon/Redis frente a la cola `database`;
  - MinIO o S3 para los archivos;
  - Haversine frente a PostGIS;
  - Filament y TypeScript, mencionados por el usuario pero no confirmados.
- **Documento externo.** El usuario aportó a mitad de sesión un documento que contradecía lo confirmado. Se fijó como fuente de verdad el registro de la sesión, y solo se rescataron 5 historias (US-030 a US-034).

## Discovery corto "Encontrar la obra en campo" (2026-10-10)

- **Alcance:** V18 y V19 de `docs/mapa-funcional.md`, y la lectura del GPS. Enmienda US-016, US-019 y US-008 (EPIC-002). El modo sin señal quedó fuera, como V20.
- **Escenarios nuevos: 21** (15 en US-016, 3 en US-019, 3 en US-008), validados con el parser oficial de Gherkin, sin nombres repetidos. **7 `@negative`, 4 `@edge` y 1 `@privacy`**.
- **Cada mensaje exacto de los YAML nuevos está en su `.feature`:** sin municipio por el GPS, sin resultados en el municipio, la espera del GPS y su minuto de límite.
- **Decisiones:** 7 del usuario (lo que se ve primero, los filtros, el orden, el tope, el municipio por defecto, la regla del tipo de obra y dejar fuera el modo sin señal) y 13 propuestas de Claude, validadas por el usuario el mismo día.
- **Frontera probada:** la lectura del GPS de 30 s se usa y la de 31 s no; 50 m sí y 120 m no para las obras cercanas; 20 obras con "Ver 20 más" y sin él cuando no hay más.
- **Riesgo anotado:** leer el GPS una vez por pantalla toca la validez de la evidencia (la hora y el lugar de la captura). Por eso esa parte se propone con Opus en el plan.
