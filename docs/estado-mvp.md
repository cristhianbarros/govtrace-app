# Estado del MVP: lo que falta, por área

Al 2026-10-04. Empezó sobre `77e6030` (iteración 39) y se actualizó en las it. 41 y 42a y con los análisis de UX y de flujos. Para ir abordándolo: cada punto dice qué falta y, cuando depende de ti, qué hay que decidir.

✅ hecho y probado · ⚠️ a medias · ⬜ falta · 🔒 espera algo externo · ❓ decisión tuya

## Dónde quedó (2026-10-06)

| Qué | PR |
|---|---|
| 46j: el expediente, con lo que califica la Contraloría: el supervisor, el origen de los recursos y a qué contraloría acudir, el lugar de la obra y las normas incumplidas (US-056-LEG) | este |

- **Tests:** Pest 1119, Vitest 595, `make e2e` 54 de 54, trace-check 412 de 412.
- **46j, en una frase:** el expediente y la denuncia dicen, de cada contrato, el supervisor según SECOP II (solo su nombre), el origen de los recursos y el lugar; la denuncia orienta a qué contraloría acudir y deja espacio para las normas que la veeduría considera incumplidas.
- **Para verlo:** las columnas nuevas se llenan con la próxima sincronización de SECOP (cada noche, o "Sincronizar ahora"); mientras, el expediente dice "Sin dato en SECOP II".
- ❓ **Un abogado confirma** la regla de a qué contraloría acudir: va como orientación, no como decisión.
- **Queda para después:** la denuncia sin nombre (Ley 962 de 2005, art. 81), que espera tu decisión, y el derecho de petición a la Contraloría para validar su formato.

## Antes (2026-10-05)

| Qué | PR |
|---|---|
| 46i: se quitan las redirecciones de los enlaces viejos con número (US-064-SEC) | este |

- **Tests:** Pest 1107, Vitest 595, `make e2e` 54 de 54, trace-check 404 de 404.

| Qué | PR |
|---|---|
| 46h: el ciudadano adjunta de 1 a 3 fotos, con las mismas opciones del veedor (US-059-LEG) | este |

- **Tests:** Pest 1108, Vitest 595, `make e2e` 54 de 54, trace-check 404 de 404, `make ux-check` sin retroceso.
- **46h, en una frase:** el informe ciudadano usa el selector del veedor: "Tomar foto" (cámara trasera) y "Elegir de la galería", de 1 a 3 fotos, cada una revisada con los rostros difuminados. La veeduría las ve numeradas. Al borrar un informe descartado, se borran todas.
- **Compresión de PDF:** pospuesta por el usuario; queda para la versión 2.

| Qué | PR |
|---|---|
| 46g: la verificación en dos pasos del Super Administrador, construida e inactiva (US-065-SEC, R-SEC-09) | este |

- **Tests:** Pest 1101, Vitest 580, `make e2e` 54 de 54 con el interruptor apagado, trace-check 401 de 401, `make ux-check` sin retroceso.
- **46g, en una frase:** el Super Administrador puede entrar con su contraseña y un código de su app autenticadora (TOTP), con 8 códigos de recuperación y restablecimiento desde la consola. Está apagado hasta que el operador ponga `SUPER_ADMIN_TWO_FACTOR=true` en el servidor. Costo recurrente: 0 USD.
- ❓ **Decisión tuya:** cuándo activarla. Recomiendo antes de la red principal (`docs/go-live.md`).

## Dónde quedó (2026-10-04)

| Qué | PR |
|---|---|
| El aviso de la revisión de la foto: "¿Ve a alguien sin difuminar? Tóquelo en la foto.", y que el recuadro amarillo no viaja en la foto | este |
| Las pestañas del veedor y de los paneles, arriba y siempre a la vista | este |
| 46f: la primera ubicación de una obra, con guardas; los rostros lejanos; el 405 en español | este |

- **Tests:** Pest 1085, Vitest 571, `make e2e` 54 de 54, trace-check 392 de 392, `make ux-check` sin retroceso.
- **46f, en una frase:** el primer reporte de una obra sin ubicación la fija solo a menos de 30 km de la cabecera del municipio del contrato y con 20 m de precisión o mejor. Si no, la Bandeja la deja por confirmar, con un botón para confirmarla. El detector de rostros mira además una grilla de 4 × 4 ventanas, y encuentra rostros desde unos 48 px en vez de 80.
- ❓ **Decisión tuya:** el campo de la foto del informe ciudadano es el del navegador, sin cámara directa. La propuesta es darle los mismos dos botones del veedor: "📷 Tomar foto", que abre la cámara trasera, y "Elegir de la galería".
- **Límite que queda:** dentro del mismo municipio, la única guarda es la Bandeja, que marca todo primer reporte. No hay polígonos de los municipios, solo sus cabeceras.

## Dónde quedó (2026-10-02, noche)

| Iteración | Qué | PR |
|---|---|---|
| 45g | Los tokens de acceso fuera de los registros, y el respaldo que espera a la base al arrancar | #90 |
| 46b | La validación asistida de una veeduría: el PDF de su inscripción y lo que dicen los datos abiertos del RUES (US-062-ALT, US-001) | #91 |
| 46c | Identificadores públicos que no se pueden recorrer (US-064-SEC, R-SEC-08) | #92 |
| 46d | La auditoría con filtros y en frases (US-043-MON) | #93 |
| 46e | Los rostros de las fotos, difuminados en el celular antes de la huella (R-PRIV-05, US-009, US-059-LEG) | este |

- **Tests:** Pest 1064, Vitest 562, `make e2e` 53 de 53, trace-check 383 de 383, `make ux-check` sin retroceso.
- **46e, en una frase:** cada foto se revisa antes de adjuntarla, con los rostros que encontró el detector ya difuminados (BlazeFace, en el celular, alojado en GovTrace). Se difumina a mano lo demás, y lo que se sella es la foto difuminada. La Bandeja dice cuántas zonas se difuminaron y avisa si se quitó un difuminado propuesto.
- **Bloque 46, cerrado:** 46a a 46e.
- **46c, en una frase:** las URL y el API llevan un ULID en vez del número de las obras, los reportes, las evidencias, los informes ciudadanos, los usuarios y las solicitudes de alta. Lo sellado no cambia, porque la referencia de la obra en Stellar sigue saliendo del número. Los enlaces viejos redirigen solo si lo que nombran ya es público, y una invitación solo con su token.
- ✅ Los enlaces viejos con número ya no abren nada: se quitaron las redirecciones (it. 46i).
- **Lo que dice el RUES, y lo que no:** trae lo inscrito en las cámaras de comercio, también las veedurías (382, 336 activas). Las inscritas en una personería no están, y para ellas el Super Administrador revisa el PDF. Es una ayuda para decidir: si el RUES no responde, se decide igual.
- **Riesgo que queda:** el PDF no se analiza contra malware. Se descarga como adjunto, en un sandbox, y solo lo abre el Super Administrador.
- **Lo que sigue:** las decisiones pendientes de la red principal (`docs/go-live.md`) y 42b, que espera la cuenta de AWS.

## Dónde quedó (2026-10-02, tarde)

| Iteración | Qué | PR |
|---|---|---|
| 46a | Varios Super Administradores, y nunca ninguno (US-063-USR) | #88 |
| — | El sellado en Stellar, explicado (`docs/sellado-en-stellar.md`), y la revisión del contrato y del RPC frente a las guías de Stellar | este |

- **Tests:** Pest 1003, Vitest 508, `make e2e` 52 de 52, trace-check 355 de 355, `make ux-check` sin retroceso.
- **La revisión frente a las guías de Stellar** (skills.stellar.org): el contrato cumple la lista de seguridad y no se cambia; el servidor simula, acota la validez, reintenta y no depende de la historia del RPC. Pendientes antes de la red principal: confirmar que el RPC devuelve un sello archivado (~180 días), comprobar en testnet que la selladora no acumula reembolsos y correr Scout sobre el contrato.
- **Lo que sigue:** 46b (validación con el RUES), 46c (identificadores públicos) y 46d (auditoría con filtros). La de los rostros en las fotos (enmienda de R-PRIV-05) espera la aprobación del usuario.

## Dónde quedó (2026-10-02)

**La ubicación del veedor y lo que sigue.** Al probar la geocerca en el navegador salió que "Obras cercanas" dejaba las coordenadas del veedor en los registros de acceso, y que la Bandeja no decía qué reporte había fijado la ubicación de una obra.

| Iteración | Qué | PR |
|---|---|---|
| — | Las funciones de cada rol (`docs/funciones-por-rol.md`) | #84 |
| — | La preparación del operador para la red principal (`docs/preparacion-red-principal.md`) y el recorrido de cada rol en la guía local | #85 |
| 45f | La ubicación del veedor fuera de los registros (POST y registros sin parámetros); la primera ubicación de una obra a la vista en la Bandeja; el mapa ya no tapa las barras fijas | #86 |

- **Tests:** Pest 984, Vitest 497, `make e2e` 51 de 51, trace-check 343 de 343, y `make ux-check` sin retroceso.
- **Lo que sigue, aprobado por el usuario en este orden** (detalle en `specs/PLAN.md`, bloque 46):
  1. **46a:** más de un Super Administrador desde el panel, sin quedar nunca en cero;
  2. **46b:** la validación asistida de una veeduría con los datos abiertos del RUES, y el PDF de su resolución;
  3. **46c:** identificadores públicos (ULID) en las URL, sin tocar las llaves numéricas selladas en Stellar.
- ~~**Pendiente:** `/reset-password/{token}` lleva el token en la ruta y sigue en los registros.~~ **Resuelto en la 45g**, junto con el token de las invitaciones que volvía en el Referer.

## Dónde quedó (2026-10-01)

**Las decisiones del usuario, aplicadas.** Aprobó la retención de 30 días y la enmienda de US-021, pidió que el S3 de desarrollo no perdiera las fotos y decidió los 4 vacíos de flujo que quedaban.

| Iteración | Qué | PR |
|---|---|---|
| 45d | El S3 de desarrollo con versitygw: las evidencias quedan en disco y sobreviven a un reinicio (`make storage-check`, `make storage-restore`) | #78 |
| 45e | US-021 enmendada: la alerta de la cola de sellado, de usted y sin jerga técnica | #79 |
| 43h | V13: el correo y el teléfono de contacto de la veeduría, en el pie de su sitio | #80 |
| 43i | V9: el resumen diario de las evidencias por revisar (US-060-MON) | #81 |
| 43j | V3: varios administradores por organización, sin dejarla nunca sin uno activo (US-061-USR) | #82 |
| 43k | V10: la solicitud de alta desde el Inicio y la bandeja del Super Administrador (US-062-ALT) | #83 |

- **Vacíos de flujo que quedan: 0.** Del V1 al V16, todos cerrados.
- **Tests:** Pest 972, Vitest 485, `make e2e` 50 de 50 (ningún `fixme`), trace-check 339 de 339, y `make ux-check` sin retroceso.
- **Las funciones de cada rol**, con lo que convendría agregar, están en `docs/funciones-por-rol.md`. Lo de más peso: sellar también lo que dice el SECOP II y alertar sus cambios retroactivos.
- **Pendiente de tu parte:** lo mismo de la noche anterior, menos las dos decisiones por defecto, que ya aprobaste.

## Dónde quedó (2026-09-30, noche)

**Después del proceso actual, la interfaz y lo que quedó de las iteraciones pasadas.** El usuario pidió ilustraciones y una interfaz más acogedora; ver los correos que se envían; y seguir sin su revisión con lo que no espera a nadie ("continúa con lo sugerido").

| Iteración | Qué | PR |
|---|---|---|
| 40e | Pestañas en el sitio de cada veeduría y una identidad propia: verde pino, sol y terracota, Atkinson Hyperlegible y Fraunces | #69 |
| 40f | Ilustraciones en las pantallas vacías, los errores y los encabezados; el barrio en el pie; barras en las estadísticas | #70 |
| 38b | El buzón de desarrollo: una copia de cada correo en Mailpit (back_cosmetics usa MailDev, no MailHog) | #71 |
| 45a | El calendario en la hora de Colombia; cambiar la contraseña cierra las otras sesiones; el respaldo resiste una base que desaparece | #72 |
| 45b | Los correos, de usted y con la identidad de GovTrace | #73 |
| 45c | De SECOP solo lo que se usa (sin los datos personales de representantes y supervisores); la retención de los informes ciudadanos; el nombre al activar la cuenta | #74 |
| 43f | V8: la razón social | #75 |
| 43g | V7: reportar en nombre de una organización, con su pantalla | #76 |

- **Vacíos de flujo que quedan: 4**, todos con una decisión tuya pendiente:
  - V3: un segundo administrador, o reemplazarlo;
  - V9: un aviso diario al administrador con las evidencias por revisar;
  - V10: la solicitud de alta de una veeduría;
  - V13: el contacto de la veeduría en su página.
- **Tests:** Pest 935, Vitest 466, `make e2e` 50 (44 pasan y 6 `fixme`, uno por vacío), trace-check 315 de 315, y `make ux-check` sin retroceso.
- **Hallazgos:**
  - **LocalStack gratuito perdía los archivos al reiniciarse** (45a). En desarrollo, un reinicio de Docker borró las fotos del bucket; se recuperaron desde la copia de respaldo. En producción no pasa: es S3. → **Resuelto en la 45d:** versitygw guarda en disco.
  - **La sincronización incremental de SECOP no es posible** (45c): el conjunto se vuelve a publicar entero cada día y `:updated_at` es igual en todas las filas.
- **Decisiones por defecto que esperan tu confirmación**, cada una en su iteración de `specs/PLAN.md`:
  - los 30 días de retención de los informes ciudadanos (45c);
  - la alerta de la cola de sellado, que tutea, con el texto exacto de US-021 (45b).
- **Pendiente de tu parte:**
  - los datos del operador (`PRIVACY_CONTROLLER_*`): sin ellos, la política sigue como borrador;
  - las 3 credenciales de testnet en Jenkins (desde la 37a);
  - la revisión legal de la "Prueba Pericial Criptográfica", los impedimentos y el texto de la política;
  - la cuenta de AWS para staging (42b) y la red principal (37b).

## Dónde quedó (2026-09-30, mañana)

El usuario pidió avanzar sin su revisión hasta el día siguiente, con la interfaz como prioridad, "como si mañana fuera el día de la demo". Se hicieron ocho iteraciones, cada una con su PR:

| Iteración | Qué | PR |
|---|---|---|
| 40a | El checkpoint base: los flujos de cada rol como tests y `make ux-check` | #50 |
| 40b | Salir en todos los paneles, la Bandeja con obra y veedor, los estados junto al mapa, el estado de la obra y su motivo, qué falta para enviar | #51 |
| 40c | Navegación con íconos (barra lateral en el computador), letra de 16 px, botones de 44 px, lenguaje claro, buscadores, el mapa como lista, cambiar contraseña | #52 |
| 40d (parte) | El Inicio con "¿Cómo funciona?" y el directorio de veedurías, "Entrar", tomar la foto o elegirla | #53 |
| 43a (parte) | El Super Administrador ve, reinvita, revoca y asigna el Administrador de cada organización | #54 |
| 43b (parte) | La app instalable, el verificador independiente enlazado, sincronizar SECOP ahora | #55 |
| 43c | El ensayo de la demostración, de punta a punta: descargar el original de una foto, la cuenta de la Bandeja que baja, el valor de los contratos de `make demo` | #57 |
| 43d | `make demo LUGAR="lat,lng"`: la obra de la Calle 30 en el lugar de la presentación, para reportarla en vivo dentro de la geocerca | #58 |

**Alinear la v1 con el proceso actual (2026-09-30).** La revisión del control social de hoy (`docs/proceso-actual.md`, #60) encontró que GovTrace terminaba en el mapa, un paso antes del proceso formal. El usuario metió en el MVP v1 cuatro correcciones:

| Iteración | Qué | PR |
|---|---|---|
| 44a (A1) | El estado de una obra es una alerta de GovTrace, no una "obra inconclusa" (Ley 2020 de 2020); los canales oficiales de la Contraloría en cada obra | #61 |
| 44b (A3) | El expediente de una obra: un ZIP con el expediente y las plantillas del derecho de petición y de la denuncia en PDF, pre-llenadas y con la "Prueba Pericial Criptográfica", más los originales con su prueba. Cada descarga queda en el log (telemetría) | #62 |
| 44c (A4) | El veedor declara que no tiene impedimentos para serlo (Ley 850 de 2003, art. 19); sin la declaración no reporta | #63 |
| 44d (A5) | Una veeduría sin NIT se identifica con su inscripción en la personería o la cámara de comercio | #64 |
| 44e | La política de tratamiento de datos (Ley 1581 de 2012) en `/privacidad`, y la autorización al activar una cuenta | #66 |
| 43e | `make demo TERRITORIO=medellin`: la Comuna 13, con contratos reales solo con "Avance" y obras de ejemplo ficticias | #67 |
| 44f (A2) | "Informar a esta veeduría": el ciudadano, con su correo verificado por un código, informa a la veeduría; la veeduría le responde sin ver su correo | #68 |

- **A1 a A5 quedaron hechas**, con la política de datos (44e), la demostración en la Comuna 13 (43e) y el canal del ciudadano (44f).
- **Falta de parte del usuario:** los datos del operador central (`PRIVACY_CONTROLLER_*`), que es el responsable del tratamiento. Sin ellos, `/privacidad` sigue como borrador.
- **Decisiones por defecto que conviene revisar con un abogado:**
  - la etiqueta "Prueba Pericial Criptográfica";
  - los cinco impedimentos en lenguaje claro;
  - la entidad de registro como texto libre (personería o cámara de comercio).

  Cada una está en su iteración de `specs/PLAN.md`.

**Frente al checkpoint base:**

| Qué | Antes | Ahora |
|---|---|---|
| Vacíos de flujo | 17 | **7**: V3, V7, V8, V9, V10, V13 y la Ley 1581. Todos esperan una decisión o son trabajo que no hace falta para la demo. |
| Flujos como tests (`make e2e`) | 27 pasan y 20 `fixme` | **38 pasan y 9 `fixme`** |
| axe (WCAG 2.2 AA) | 2 reglas en 52 vistas | **0** |
| Texto de menos de 16 px, en la pantalla típica | 73 % | **12,5 %** |
| Texto de menos de 14 px, en la pantalla típica | 25,5 % | **0 %** |
| Botones de menos de 44 px, en la pantalla típica | 66 % | **0 %** |
| Tests | Pest 785, Vitest 351 | Pest 858, Vitest 418, E2E 49 (40 + 9 pendientes), trace-check 295 de 295 |

**Para probar el flujo visual completo:** `make demo`, y el guion de `docs/local-environment-setup.md` (sección "Un guion para la demostración").

**Decisiones que se tomaron por defecto y esperan tu confirmación:** están en `specs/PLAN.md`, en cada iteración (40b a 43b).
- "Salir" siempre pregunta.
- Los textos del glosario enmendados en el spec ("Comprobar que es original").
- Las explicaciones de Avance, Retraso y Abandono, provisionales.
- La escala de letra, cambiada en toda la app.
- El directorio de veedurías, publicado.
- No se asigna un segundo Administrador (V3).

## Checkpoint base (2026-09-29)

Es el punto de partida para mejorar todo lo que falta. El tag `checkpoint-base` marca el código tal como se analizó. Desde ahí, cada mejora se mide contra estas cifras:

| Qué | Línea base | Detalle |
|---|---|---|
| Historias de la SPEC | 56 de 56, en 28 pantallas | `specs/AUDIT.md` |
| Flujos de punta a punta | **17 vacíos:** 4 cortan un flujo, 6 lo dejan a medias y 7 son menores | `docs/mapa-funcional.md` |
| Rutas del backend sin pantalla | 1: reportar en nombre de una organización | `docs/mapa-funcional.md`, sección 6 |
| Accesibilidad técnica (axe, WCAG 2.2 AA) | 2 reglas incumplidas en 52 vistas; contraste AA en todas | `docs/ux-analisis.md` |
| Texto de menos de 16 px (celular) | 80 % en la pantalla típica | `docs/ux-analisis.md`, anexo A |
| Botones de menos de 44 px | 84 % en el panel del Administrador, 91 % en el del Super Administrador, 23 % en el sitio público y el veedor | `docs/ux-analisis.md`, anexo A |
| Tests | Más de 1.100, cada uno de una historia sola; hasta la it. 40a, solo 2 en un navegador y **ninguno recorre el flujo de un rol** | `docs/mapa-funcional.md`, sección 6 |
| Flujos de punta a punta, como tests (it. 40a, `make e2e`) | **27 pasos pasan y 20 son `fixme`**, uno por vacío. Tras la it. 40b: 30 y 17 (cerrados V1 y V4). Tras la 40c: 32 y 15 (cerrados V11 y V12). Tras la 40d: 34 y 13 (cerrado V5). Tras la 43a: 35 y 12 (cerrados V2 y V16). Tras la 43b: 38 y 9 (cerrados V6, V14 y V15). | `tests/e2e/flujos/` |
| Pantallas medidas (it. 40a, `make ux-check`) | 52 (26 por tamaño), comparadas con `tests/ux/baseline.json` | `tests/ux/` |

**Por dónde empezar:** por la **it. 40a**, que convierte los dos análisis en tests: el recorrido con axe (`make ux-check`) y los flujos de cada rol como tests de extremo a extremo. Los vacíos quedan como tests pendientes, y cada iteración los pasa a verde. El orden completo está en la sección 6.

## Resumen

**El alcance funcional está completo y probado:**
- las 56 historias del discovery, en 39 iteraciones;
- los 250 escenarios Gherkin, cada uno con un test que lleva su nombre;
- 761 tests rápidos de backend, 351 de frontend y 17 contra la red Stellar local;
- el sellado probado en testnet, también en ráfaga: 7 evidencias en 7 ledgers seguidos.

**Lo que separa esto de un MVP listo para producción, de más a menos peso:**
1. ~~**Salir a internet con seguridad**~~ — hecho en la it. 41: proxies de confianza, CSP y HSTS, límites de abuso, auditoría de dependencias, logs diarios y todo en español. Falta el certificado, que llega con staging.
2. **Una prueba real en la nube** (staging en AWS, apuntando a testnet): celulares de verdad, HTTPS y correo real.
3. **AWS KMS** para la llave de la selladora (37b): espera la cuenta de AWS.
4. **La usabilidad.** Ya se revisó con capturas y mediciones (`docs/ux-analisis.md`). Hoy no se puede salir de los paneles, la leyenda del mapa queda lejos y hay lenguaje técnico, letra y botones pequeños. Lo corrige la it. 40.
5. **Los flujos completos.** Recorriendo cada rol aparecen 17 vacíos que la SPEC no cubrió (`docs/mapa-funcional.md`). Por ejemplo: el Super Administrador no puede reenviar la invitación del administrador de una organización ni reemplazarlo, y una veeduría no tiene cómo pedir su alta. Lo cierran la it. 40 y la it. 43.
6. **La protección de datos personales** (Ley 1581 de 2012): no está en la SPEC.
7. **La salida a la red principal** (37b): proveedor de RPC, tesorería y restauración con datos reales.

## Listo para producción: la lista

El MVP tiene que estar listo para producción, no solo funcionar. Esta es la vara, punto por punto; cada uno se da por cumplido con una prueba, no con una promesa.

**Seguridad**
- ✅ Lista para ir detrás de TLS: HSTS, cookies seguras, proxies de confianza (it. 41).
- ✅ El proxy con TLS 1.2 y 1.3 y el certificado comodín, probados en local con una CA de prueba: por HTTPS la aplicación sabe que es HTTPS, sin contenido mixto (it. 42a). ⬜ El certificado de Let's Encrypt en AWS (it. 42b).
- ✅ Cabeceras: CSP, `Permissions-Policy`, `nosniff`, `X-Frame-Options`, `Referrer-Policy` (it. 41).
- ✅ Límites de abuso: reportes, API públicas, datos abiertos, inicio de sesión y recuperación de contraseña.
- ✅ Una base de datos por organización, roles probados y log de auditoría inmutable.
- ✅ Ninguna llave en el repositorio (`make secrets-check`). ⬜ Los secretos desde un gestor en el servidor (it. 42).
- 🔒 La llave de la selladora en AWS KMS (37b).
- ✅ Dependencias auditadas en cada PR (`make audit`).
- ⬜ Un análisis dinámico (OWASP ZAP) contra staging, sin hallazgos altos (it. 42).
- ✅ Verificación en dos pasos (TOTP) para el Super Administrador, construida e **inactiva** (it. 46g): ❓ cuándo activarla (`docs/go-live.md`).

**Confiabilidad**
- ✅ Respaldos cada hora, 30 días, con réplica fuera del sitio y restauración de prueba con límite de 4 h. 🔒 La restauración con datos reales (37b).
- ✅ Sellado resistente a las fallas de la red (US-021) y a las ráfagas (it. 39).
- ✅ Logs diarios con retención (it. 41).
- ✅ Monitor externo configurado y probado. ⬜ Apuntando a staging y producción (it. 42).
- ❓ Seguimiento de errores: un servicio tipo Sentry, o alertas por correo de los errores críticos del log.

**Operación**
- ✅ La plantilla de producción, vigilada por un test (it. 41).
- ✅ La guía de restauración (`docs/restore.md`) y la lista de salida (`docs/go-live.md`).
- ✅ Un despliegue repetible: `deploy/deploy.sh` (imágenes, migraciones, DIVIPOLA, caché, `/up` por HTTPS), probado dos veces seguidas (it. 42a). ⬜ Su guía en AWS (it. 42b).

**Calidad**
- ✅ Suite completa en el pipeline, pruebas de extremo a extremo en un navegador, cada escenario con su test, y reglas probadas rompiéndolas a propósito.
- ✅ La imagen de producción se construye y se prueba entera, detrás de TLS, en cada PR (`make staging-check`, it. 42a). Antes no se construía en el pipeline, y estuvo rota desde la it. 27 sin que nadie lo notara.
- ⬜ El recorrido visual de todas las pantallas (it. 40).
- ❓ Una meta de accesibilidad.

**Cumplimiento**
- ❓ La política de tratamiento de datos (Ley 1581 de 2012) y la autorización de cada veedor.

**Rendimiento**
- ✅ La capacidad del sellado, medida: 10 a 12 sellos por minuto (it. 39).
- ❓ Una prueba de carga con el volumen que esperas.

## 1. Interfaz (UX/UI)

| | Qué | Detalle |
|---|---|---|
| ✅ | Todas las pantallas de la SPEC | 28 páginas: la app del veedor (PWA, con modo sin conexión), el panel del Administrador, el panel global y el sitio público. Mobile-first, con los estados carga / error / vacío / éxito probados en Vitest. Los botones de 44 px se cumplen en la app del veedor y el sitio público, no en los paneles: ahí la mayoría mide 26 a 41 px. |
| ⚠️ | **Revisadas el 2026-09-29; falta corregir** | Un análisis experto de las 26 vistas, en celular y escritorio, con capturas y mediciones: `docs/ux-analisis.md`. **La accesibilidad técnica está sana:** axe incumple solo 2 reglas en 52 vistas y el contraste cumple AA. **La usabilidad no:** el Administrador y el Super Administrador no pueden cerrar sesión, la Bandeja no dice de qué obra es cada foto, la leyenda del mapa queda lejos, y hay lenguaje técnico, letra y botones pequeños. Lo corrige la **it. 40**, en cuatro partes: la 40a es el checkpoint base, y la 40b, la 40c y la 40d corrigen. |
| ✅ | Correos y mensajes en español (it. 41) | `APP_LOCALE=es` y `lang/es`: los correos ya no traen las frases en inglés de la plantilla de Laravel ("Regards", "If you're having trouble clicking…"), y los mensajes de validación por defecto y las páginas de error salen en español. |
| ⬜ | Accesibilidad | ❓ La SPEC no fija una meta. Propuesta: WCAG 2.2 AA más las reglas R-UX de `docs/ux-analisis.md` (sección 2), medidas en el pipeline con `make ux-check`. Lo que falta no es contraste ni etiquetas: es el tamaño de letra y de botones, y el color como único significado. |
| ⚠️ | Cámara y GPS en un celular | El navegador los exige con HTTPS: en local, solo desde el mismo equipo. Se prueban de verdad en staging. |
| ⬜ | Perfil del veedor | ❓ Su nombre es la parte local del correo (deuda aceptada). Ninguna historia pide editarlo. |
| ⚠️ | Mapas | ❓ Las imágenes del mapa vienen de `tile.openstreetmap.org`, cuya política no admite tráfico de producción intenso. Para la red principal, un proveedor de mapas con su propia llave. |

## 2. Requisitos funcionales

| | Qué | Detalle |
|---|---|---|
| ✅ | Historias, escenarios y reglas | De las 66 reglas de la SPEC, 64 en ✅ (`specs/AUDIT.md`). |
| ⚠️ | **Flujos de punta a punta** | Revisados rol por rol el 2026-09-29 (`docs/mapa-funcional.md`): **17 vacíos** que la SPEC no cubrió. **4 cortan un flujo:** salir de los paneles; el administrador de una organización, que no se puede reinvitar, asignar después ni reemplazar; y la Bandeja sin obra ni autor. Solo una ruta del backend no tiene pantalla. Los cierran la it. 40 y la it. 43. |
| 🔒 | R-CFG-01 y R-BCK-05 | La red principal (preparada y probada en testnet, sin desplegar) y la restauración con datos reales. Esperan producción (37b). |
| ⚠️ | SECOP II de verdad | Los tests usan respuestas grabadas (R-TST-02). La sincronización contra la API real no se ha visto correr de punta a punta en un entorno desplegado: se comprueba en staging. |
| ⬜ | Correo real | Hoy sale a un archivo (`make invites`). En staging, por SMTP (Amazon SES). |
| ⬜ | **Protección de datos personales** | ❓ La SPEC no menciona la Ley 1581 de 2012. GovTrace trata correos, nombres y la ubicación exacta de cada reporte. Hace falta una política de tratamiento de datos publicada y la autorización de cada veedor al activar su cuenta. Primero es una decisión legal; después, una pantalla y el registro de esa autorización. |
| ✅ | Doble factor | It. 46g (US-065-SEC, R-SEC-09): TOTP para el Super Administrador, inactivo hasta que el operador lo active (`SUPER_ADMIN_TWO_FACTOR`). ❓ Cuándo activarlo. |

## 3. Requisitos no funcionales

| | Qué | Detalle |
|---|---|---|
| ✅ | Respaldos | Cada hora, guardados 30 días, con réplica fuera del sitio y una restauración de prueba que falla si pasa de 4 h. 🔒 Falta la restauración de producción. |
| ✅ | Sellado bajo carga | Por turnos (it. 39): 10 a 12 sellos por minuto con una selladora, y ninguna ráfaga gasta intentos. |
| ✅ | Rotación de logs (it. 41) | La plantilla de producción usa `LOG_STACK=daily` con 14 días (en desarrollo, un solo archivo llegó a pesar 345 MB). Un test vigila la plantilla. |
| ⬜ | Pruebas de carga | ❓ No hay, y la SPEC no fija tiempos de respuesta. ¿Cuántos veedores y visitantes esperas en la primera salida? |
| ⚠️ | Monitoreo | El monitor externo (Gatus) está configurado y probado (US-044-MON), sin apuntar a un entorno real. No hay seguimiento de errores (tipo Sentry): quedan en el log. |
| ⚠️ | Calendario en UTC | Deuda aceptada: la sincronización de las 02:00 corre a las 21:00 en Colombia. |
| ⚠️ | Pipeline | El Jenkinsfile corre completo en un entorno de cero. Faltan las credenciales de testnet de Jenkins (sección 2 de `docs/estado-37b.md`). |

## 4. Seguridad

| | Qué | Detalle |
|---|---|---|
| ⚠️ | **HTTPS** | Listo y probado en local (it. 42a): nginx termina TLS 1.2 y 1.3 con un certificado comodín, y por HTTPS la aplicación sabe que es HTTPS (hizo falta un arreglo en Apache: `mod_remoteip` le ocultaba al proxy). Falta el certificado de Let's Encrypt en AWS (it. 42b). |
| ✅ | Laravel detrás de un proxy (it. 41) | Confía en el proxy de la red privada (`TRUSTED_PROXIES`), y solo en su `X-Forwarded-For` y `X-Forwarded-Proto`. Nunca en `X-Forwarded-Host` ni `-Port`, que además nginx borra: con ellos se envenenarían los enlaces. |
| ✅ | Cabeceras de seguridad (it. 41) | CSP en toda respuesta, con los dos únicos orígenes externos (las imágenes del mapa y el RPC público de Stellar), `Permissions-Policy` (cámara y ubicación solo para el sitio) y, por HTTPS, HSTS. Probada en un Chromium de verdad: ninguna pantalla pública ni del veedor la viola. |
| ✅ | Cookies de sesión | Cifradas y `Secure` en la plantilla de producción. |
| ✅ | Límites de abuso (it. 41) | 30 reportes por veedor y por hora (la bandeja de salida de la PWA, que guarda 10, cabe entera); 120 consultas públicas por visitante y por minuto; 10 descargas de datos abiertos. Un visitante es su IP real detrás del proxy. Pasado el límite, 429 en español, y la PWA guarda el reporte y lo envía sola después. ❓ Los números. |
| 🔒 | **AWS KMS** (D11 b, 37b) | Elegido. **El emulador local no sirve:** LocalStack 3.8, el gratuito, no soporta llaves Ed25519, y la versión actual exige licencia. La prueba de concepto se hace contra AWS. ❓ Sigue abierta la decisión de pasar también la patrocinadora a KMS (recomendado: es la que tiene los fondos). |
| ✅ | Llaves fuera del repositorio | `make secrets-check`. En producción, desde un gestor de secretos (pendiente, `docs/go-live.md`). |
| ✅ | Dependencias (it. 41) | `make audit` (composer audit y npm audit de producción, nivel alto o más) corre en el pipeline, en su propia etapa. Hoy, sin vulnerabilidades conocidas. |
| ✅ | Lo que ya protege | Una base de datos por organización; los roles probados, incluido el caso negativo; log de auditoría inmutable; SVG saneados; fotos con EXIF rechazadas; hashes recalculados en el servidor; contrato sin `upgrade`; sin `v-html` en el frontend. |
| ⬜ | Análisis de seguridad | Nadie ha atacado un entorno desplegado (por ejemplo, con OWASP ZAP). Se hace en staging. |

## 5. La prueba real en la nube (staging en AWS)

> **Actualizado en la it. 42b (D14):** staging quedó con un subdominio gratuito de DuckDNS en lugar de un dominio en Route 53, con el SMTP de Gmail en lugar de SES y sin KMS. La cuenta es de la experiencia nueva de AWS: el proyecto vive en us-east-2, sin usuario raíz ni usuarios de IAM con consola. La infraestructura está en CloudFormation. Lo de abajo es la propuesta original; lo vigente está en `docs/staging.md`.

### ¿Tu cuenta personal o una exclusiva para GovTrace?

**Recomiendo una cuenta exclusiva:**
- **Aislamiento:** una llave filtrada o un recurso olvidado no toca tu cuenta personal ni tu facturación.
- **Traspaso:** si GovTrace pasa a una organización o fundación, se entrega la cuenta entera.
- **Créditos:** hoy, una cuenta nueva recibe **100 USD en créditos**, y hasta **100 USD más** por cinco actividades de 20 USD cada una: lanzar una instancia EC2, configurar RDS, crear una función Lambda, usar Bedrock y crear un presupuesto en AWS Budgets.
  - Con el **plan gratuito**, AWS no cobra nada hasta que lo pases a pago. Vence a los 6 meses o cuando se acaban los créditos. Si no lo pasas a pago en 90 días, AWS cierra la cuenta.
  - Tus 20 USD de créditos son de tu cuenta personal: los créditos promocionales no pasan de una cuenta a otra, salvo compartidos dentro de una organización de AWS.

### Qué haces tú en la cuenta nueva

1. Activar MFA en el usuario raíz, y nunca crearle llaves de acceso.
2. Crear un usuario para el día a día (IAM Identity Center o IAM), también con MFA.
3. Crear un presupuesto en AWS Budgets con alertas a 5, 10 y 15 USD. Además, es una de las actividades de 20 USD.
4. Elegir la región: **us-east-1** (Norte de Virginia), la más barata y con todos los servicios.
5. Para la prueba de concepto de KMS: un usuario o rol limitado a `kms:CreateKey`, `kms:GetPublicKey`, `kms:Sign` y `kms:ScheduleKeyDeletion`, con su perfil configurado en tu equipo. Me dices el nombre del perfil y la región, nunca las llaves.

### La arquitectura que propongo

Una sola máquina, sin balanceador de carga: un balanceador cuesta más que todo lo demás junto.

| Pieza | Para qué | Costo aproximado |
|---|---|---|
| **EC2 t4g.small** (2 vCPU ARM, 2 GB) | El mismo stack de Docker, apuntando a testnet con el contrato oficial, sin la red Stellar local. Si 2 GB no alcanzan, t4g.medium (4 GB). | ~12 USD/mes (~24 la t4g.medium) |
| Rol de la instancia | Permisos solo para firmar con la llave de KMS, los dos buckets y enviar correo. **Ninguna llave guardada en el servidor.** | gratis |
| **HTTPS con Let's Encrypt** | Un certificado comodín (`*.dominio`), validado por DNS en Route 53. | gratis |
| Dominio y zona en Route 53 | Cada organización es un subdominio: el comodín va en el DNS y en el certificado. | el dominio, si hay que comprarlo; la zona, 0,50 USD/mes |
| IP pública | La dirección de la máquina. | 0,005 USD/hora, ~3,65 USD/mes |
| Disco (EBS gp3, 20 GB) | El sistema, Docker y PostgreSQL. | ~1,60 USD/mes |
| S3 | Las evidencias, y los respaldos en otro bucket de otra región, con versionado. | centavos |
| SES | El correo. Empieza en modo sandbox (solo a direcciones verificadas) hasta pedir la salida. | centavos |
| KMS | La llave de la selladora (y la de la patrocinadora, si lo decides). | 1 USD/mes por llave, más 0,15 USD por cada 10.000 firmas; las firmas asimétricas no entran en la capa gratuita |

**Total: unos 20 USD al mes con la t4g.small, o unos 33 con la t4g.medium.** Con los 100 USD de una cuenta nueva, unos 5 meses de staging; con los 200, los 6 meses del plan gratuito. Con tus 20 USD, cerca de un mes. Los precios son de us-east-1: confírmalos en la calculadora de AWS antes de crear nada.

**Sobre el certificado:** con Let's Encrypt no se paga. Los certificados de ACM son gratis solo detrás de un balanceador o de CloudFront; para usar uno dentro de la máquina, ACM cobra 15 USD por nombre y 149 USD por un comodín.

### Qué hay que construir para staging (una iteración)

- El proxy con HTTPS (Let's Encrypt comodín) y HSTS, y Laravel confiando en ese proxy.
- Un `docker-compose` de staging: S3 y SES de verdad en vez de LocalStack y el log de correo, y testnet en vez de la red local.
- Los secretos desde SSM Parameter Store (gratis en su nivel estándar), no en archivos.
- La firma con KMS (37b), si la prueba de concepto pasa.
- Un script de despliegue, con su guía, y el monitor externo apuntando a staging.

## 6. Orden propuesto

Desde el checkpoint base, primero lo que corta un flujo, después lo que lo deja a medias y al final el pulido. AWS avanza en paralelo, cuando exista la cuenta.

| # | Qué | Cierra | Estado |
|---|---|---|---|
| 1 | **It. 40a, checkpoint base** | La medida de todo lo demás | ✅ #50 |
| 2 | **It. 40b, lo urgente** | V1, V4 | ✅ #51 |
| 3 | **It. 43a, el gobierno de las organizaciones** | V2, V16 ✅ (#54); V3 ❓, V7 y V8 pendientes | ⚠️ |
| 4 | **It. 40c, navegación y legibilidad** | V11, V12 | ✅ #52 |
| 5 | **It. 43b, llegar y volver** | V6, V14, V15 ✅ (#55); V9, V10 y V13 ❓ | ⚠️ |
| 6 | **It. 40d, orientación** | V5 ✅ (#53); quedan la guía del veedor, los pasos del reporte, la auditoría en frases, el nombre corto de la obra, unir contratos de una lista y la prueba con 5 personas | ⚠️ |
| — | **Tú:** la cuenta de AWS y el perfil de KMS (sección 5) | Desbloquea las siguientes | 🔒 |
| 7 | **It. 42b, staging en AWS** con testnet | La prueba real | 🔒 |
| 8 | **37b:** KMS, y después la red principal | Producción | 🔒 |
| 9 | **It. 46a, los Super Administradores** | La dependencia de un solo Super Administrador | ✅ #88 |
| 10 | **It. 46b, la validación asistida con el RUES** | El alta, hoy 100 % manual | ✅ |
| 11 | **It. 46c, identificadores públicos** | Los IDs que se pueden recorrer | ✅ |

**Decisiones tuyas (❓):**
- cuándo activar la verificación en dos pasos del Super Administrador (construida en la it. 46g, inactiva);
- la política de datos personales (Ley 1581) y la autorización de los veedores;
- la meta de accesibilidad y las decisiones de UX (sección 9 de `docs/ux-analisis.md`);
- las decisiones de los flujos: la solicitud de alta, varios administradores, el autor en la Bandeja, el aviso diario y el contacto de la veeduría (sección 5 de `docs/mapa-funcional.md`);
- el límite de reportes por veedor;
- el perfil del veedor;
- el proveedor de mapas para la red principal;
- la patrocinadora en KMS;
- cuántos usuarios esperas, para las pruebas de carga.
