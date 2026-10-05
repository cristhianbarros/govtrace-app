# GovTrace: las funciones del MVP, por rol

Estado al **2026-10-01**: lo construido y probado hasta la it. 43k, PR #83. Cada función dice dónde está, qué hace, sus reglas y su historia (`features/US-*.feature`).

**Qué es GovTrace.** Una plataforma de código abierto para el control social de las obras públicas en Colombia:
- las veedurías ciudadanas toman fotos de las obras con el celular;
- cada evidencia recibe un sello digital en la red pública Stellar, así que nadie puede cambiarla ni borrarla;
- la veeduría la revisa y la publica en su mapa, donde cualquiera la ve y comprueba que es original.

**Cómo está organizada.**
- **Dos dominios:**
  - el central, `govtrace.<dominio>`: el Inicio y el panel global del Super Administrador;
  - uno por cada veeduría, `<veeduría>.govtrace.<dominio>`: su sitio público, el panel de su Administrador y la app de sus veedores.
- **Una base de datos por organización:** lo de una nunca se mezcla con lo de otra.

## Índice

1. [Los roles, de un vistazo](#1-los-roles-de-un-vistazo)
2. [Visitante o ciudadano (sin cuenta)](#2-visitante-o-ciudadano-sin-cuenta)
3. [Una veeduría que quiere usar GovTrace](#3-una-veeduría-que-quiere-usar-govtrace)
4. [Veedor de Campo](#4-veedor-de-campo)
5. [Administrador de Organización](#5-administrador-de-organización)
6. [Super Administrador](#6-super-administrador)
7. [Lo que hace el sistema solo](#7-lo-que-hace-el-sistema-solo)
8. [El operador técnico (consola)](#8-el-operador-técnico-consola)
9. [Reglas que valen para todos](#9-reglas-que-valen-para-todos)
10. [Lo que falta](#10-lo-que-falta)
11. [Las historias, por rol](#11-las-historias-por-rol)

---

## 1. Los roles, de un vistazo

| Rol | Quién es | Dónde entra | Cómo obtiene acceso |
|---|---|---|---|
| **Visitante o ciudadano** | Cualquier persona | El Inicio y el sitio de cada veeduría | Sin cuenta |
| **Veeduría solicitante** | Una veeduría que quiere publicar en GovTrace | El Inicio, "¿Su veeduría quiere publicar en GovTrace?" | Sin cuenta; el Super Administrador la aprueba |
| **Veedor de Campo** | Un integrante de una veeduría que sale a las obras | La app de su veeduría, en el celular | Lo invita un Administrador, por correo |
| **Administrador de Organización** | Quien gestiona una veeduría (puede haber varios) | El panel de su veeduría | Lo invita el Super Administrador u otro Administrador |
| **Super Administrador** | El operador central de GovTrace | El panel global | `make admin` crea el primero; no hay pantalla para crearlo |
| **El sistema** | Las tareas automáticas | — | El calendario y la cola de trabajos |

Los permisos se aplican en el servidor, no solo en la pantalla:
- cada ruta exige su rol;
- una cuenta desactivada o una organización suspendida pierden el acceso en su siguiente petición;
- cambiar la contraseña cierra las otras sesiones abiertas de esa cuenta.

---

## 2. Visitante o ciudadano (sin cuenta)

### 2.1 En el Inicio de GovTrace (dominio central)

| Función | Qué hace | Historia |
|---|---|---|
| **Qué es y cómo funciona** | Una bienvenida en verde, con ilustración, y "¿Cómo funciona?" en tres pasos con su dibujo: la foto, el sello y el mapa. | 40d, 40e, 40f |
| **Directorio de veedurías** | Cada veeduría con su territorio y el botón "Ver su mapa de obras". Una suspendida lo dice. | 40d (V5) |
| **Pedir el alta de una veeduría** | Ver la sección 3. | US-062-ALT |
| **Política de tratamiento de datos** | `/privacidad`: quién responde por los datos, qué datos se tratan y para qué, qué queda en la red pública, los derechos y cómo ejercerlos, y los plazos de retención. Sigue como borrador hasta que estén los datos del operador. | US-058-LEG |

### 2.2 En el sitio de cada veeduría

El sitio tiene tres pestañas: **Obras**, **Estadísticas** y **Validar**. Al pie, el barrio en silueta, la frase de GovTrace, la política de datos y el contacto de la veeduría si lo publicó.

**El mapa de obras** (`/`, US-027, US-028, it. 40b a 40f)
- **Un pin por obra, con el color de su estado:**
  - **Normal** (verde);
  - **Alerta** (amarillo): la evidencia publicada más reciente dice "Retraso";
  - **En riesgo** (rojo): la evidencia más reciente dice "Abandono", o un contrato en ejecución ya pasó su fecha de fin (US-034).
  - Gana el peor, entre las evidencias y los contratos de la obra.
- **Los tres estados como fichas grandes,** con cuántas obras hay de cada uno. Tocar una muestra solo esas.
- **"Más filtros":** el estado, las fechas de las evidencias, el presupuesto mínimo y el municipio. Si nada coincide, el mapa lo dice con una ilustración.
- **El mapa también como lista,** con un buscador por nombre que ignora tildes y mayúsculas.
- **"¿Cómo funciona?"** en tres pasos. Los colores son alertas de GovTrace, no decisiones de una autoridad (it. 44a).
- **Si la veeduría está suspendida,** un aviso, y el mapa sigue a la vista. Si está **dada de baja**, el mapa sale de línea, pero el validador sigue sirviendo (US-003b).

**La página de una obra** (`/worksite/{id}`, US-029, US-017, US-055-LEG)
- **Su estado y por qué**, en palabras. Si está "En riesgo", aclara que eso no es una "obra inconclusa" de la Ley 2020 de 2020.
- **Sus contratos de SECOP II:** entidad, contratista, valor, plazo y enlace a SECOP. Un contrato anulado en SECOP que ya tenía evidencias lo dice.
- **Su línea de tiempo de evidencias publicadas:**
  - fecha, clasificación (Avance, Retraso o Abandono) y comentario;
  - las fotos, que se abren en un visor;
  - la ubicación **aproximada**, a unos 100 m: nunca la exacta del veedor;
  - una evidencia **retirada** queda como lápida, sin fotos, con la fecha y el motivo.
- **"Comprobar que es original"** en cada evidencia: el recibo público, con el sello en Stellar, el ledger y el enlace al explorador (US-025).
- **"Descargar archivo original" y "Descargar su prueba"** (US-026): el mismo binario que se selló y su prueba de inclusión (`.prueba.json`), para verificarlo sin GovTrace.
- **"¿Sabe de un problema en esta obra?":** los canales oficiales de la Contraloría General de la República (línea 199, el 01 8000 y SIPAR en línea), con lo que puede hacer cualquier ciudadano (it. 44a).
- **"Informar a esta veeduría"** (US-059-LEG, it. 44f):
  1. El ciudadano escribe su correo, autoriza el tratamiento de sus datos y recibe un **código de 6 dígitos**. Vale 10 minutos y admite 5 intentos.
  2. Con el código, escribe lo que vio, entre 20 y 2.000 caracteres, y una foto opcional (JPEG, hasta 10 MB, sin metadatos), que revisa antes de enviar, con los rostros ya difuminados (it. 46e).
  3. Le llega un correo con la referencia de su informe, como `7KQ3-M9XD` (it. 46c: no un número consecutivo, que decía cuántos había). La veeduría le responde desde GovTrace **sin ver su correo**, que se guarda cifrado.
  - Límites: 3 informes por correo al día, 1 por obra, y límites por conexión.
  - El informe no se sella, no se publica y no cambia el estado de la obra.

**Estadísticas del territorio** (`/stats`, US-051-RPT, it. 40f)
- Obras en riesgo, contratos anulados con evidencias y evidencias publicadas por mes, en barras.
- **Datos abiertos** (US-052-RPT): las evidencias publicadas y sus sellos, en **CSV** (para Excel) y **JSON**. Las ubicaciones van aproximadas y cada veedor con un seudónimo.

**Validar** (`/verify`, US-024)
- Comprueba si una foto o un PDF es el original **sin subirlo**: el navegador calcula su huella y la busca en la red Stellar.
- **Dos modos:**
  - **Un archivo**: busca su sello;
  - **Archivo y su prueba**: con el `.prueba.json`, consulta solo la red Stellar, sin pasar por GovTrace.
- **Los veredictos:** ✅ auténtico (sellado tal día, en tal ledger); ❌ alterado; ⚠️ no encontrado.
- **Enlace al verificador independiente** del repositorio (US-046-INT, V14), para no depender de la página.


---

## 3. Una veeduría que quiere usar GovTrace

| Función | Qué hace | Historia |
|---|---|---|
| **Pedir el alta desde el Inicio** | El nombre de la veeduría, un correo de contacto, el número de la resolución o de la matrícula y la personería o la cámara de comercio que la registró, y **el PDF de la resolución o del certificado de inscripción** (hasta 10 MB; it. 46b); la autorización del tratamiento de datos. Dice todo lo que hay que corregir a la vez. Ve: "Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a…". | US-062-ALT (it. 43k) |
| **La respuesta** | Si la aprueban, su primer Administrador recibe la invitación para crear su contraseña. Si la rechazan, le llega el motivo por correo. | US-062-ALT, US-002 |

**Las reglas:**
- no es autorregistro: el alta la da el Super Administrador;
- 5 solicitudes por hora por conexión, y un campo oculto que solo llena un robot (no deja nada guardado);
- ningún correo sale hasta que se decide, para que el formulario no sirva para escribirle a terceros;
- una solicitud decidida se borra a los 30 días.

---

## 4. Veedor de Campo

### 4.1 Su cuenta

| Función | Qué hace | Historia |
|---|---|---|
| **Activar la cuenta** | Con el enlace de la invitación (48 horas por defecto, configurable): escribe **su nombre**, crea su contraseña, **declara que no tiene impedimentos** para ser veedor (Ley 850 de 2003, art. 19) y **autoriza el tratamiento de sus datos** (Ley 1581). | US-030, US-057-LEG, US-058-LEG, it. 45c |
| **Iniciar sesión** | En el sitio de su veeduría. Llega directo a "Nuevo Reporte". Tras 5 intentos fallidos, la cuenta se bloquea 15 minutos. | US-031 |
| **Declarar sus impedimentos** | Quien ya tenía cuenta antes de la it. 44c lo hace al entrar. Sin la declaración no puede reportar. | US-057-LEG |
| **Olvidé mi contraseña** | Un enlace por correo, de 60 minutos y un solo uso. La respuesta es siempre la misma, exista o no el correo. | US-039-USR |
| **Cambiar la contraseña** | Desde el menú de su cuenta, con la actual. Cierra sus otras sesiones abiertas. | it. 40c (V11), it. 45a |
| **Salir** | Desde el menú de su cuenta. Si tiene reportes sin enviar, avisa antes. | it. 40b (V1) |
| **Instalar la app** | En el celular, con el nombre y el logo de su veeduría; abre en "Nuevo Reporte". El navegador lo ofrece por HTTPS. | it. 43b (V6) |

### 4.2 Reportar una obra ("Nuevo Reporte", la pantalla central)

| Paso | Qué hace | Historia |
|---|---|---|
| **1. Encontrar la obra** | **"Obras cercanas"**: las que están a menos de 500 m, con su distancia. O **"Buscar Obra"** por nombre, contratista o número de proceso, desde 3 letras. Solo los contratos de obra de su territorio: en ejecución, celebrados, adjudicados, o terminados y liquidados en los últimos meses (parámetro). | US-019, US-016 |
| **2. El GPS** | Toma la ubicación al elegir la obra. Exige **50 m de precisión o mejor**, y si no la tiene ofrece "Reintentar GPS". | US-008 |
| **3. Qué vio** | **Avance**, **Retraso** o **Abandono**, cada uno con su explicación en una línea, y un comentario opcional de hasta 500 caracteres. | US-008 |
| **4. Los adjuntos** | **De 1 a 5 fotos, o un PDF**, de hasta 10 MB cada uno: tomar la foto o elegirla. El teléfono **les quita los metadatos** (ubicación, cámara) y calcula su **huella SHA-256** antes de enviarlas. | US-009 |
| **5. Los rostros, difuminados** | Cada foto se revisa antes de adjuntarla: el teléfono busca los rostros y **los difumina** ("Encontramos 1 rostro y lo difuminamos."). Encima de la foto, un aviso destacado: "¿Ve a alguien sin difuminar? Tóquelo en la foto." El recuadro amarillo solo marca la zona: no viaja en la foto. El detector mira la foto entera y una grilla de 4 × 4 ventanas, y encuentra rostros desde unos 48 px de ancho en una foto de 1920 px (it. 46f). Tocar la foto difumina otra zona (un rostro más lejano, una placa); "No es un rostro" quita un recuadro equivocado, y la veeduría lo verá. La huella es la de la foto ya difuminada: el original nunca sale del teléfono. Si el detector no carga, la foto se revisa a mano. | R-PRIV-05, US-009 (it. 46e) |
| **5. Enviar** | Junto al botón, en palabras, lo que falta ("Para enviar falta: …"). El servidor vuelve a validar todo: la **geocerca** (500 m de la obra, parámetro), la precisión, la hora de captura (marca la sospechosa) y que la huella coincida. El primer reporte de una obra sin ubicación la fija solo a menos de 30 km de la cabecera de su municipio y con 20 m de precisión o mejor; si no, el veedor lee por qué quedó por confirmar (it. 46f). | US-008, it. 40b |

**Sin señal** (US-018):
- el reporte queda **en la bandeja de salida del teléfono**, con su lugar y su hora congelados;
- se envía solo cuando vuelve la señal;
- hasta 10 reportes y 50 MB, durante 7 días;
- avisa antes de que venza uno;
- si el servidor lo rechaza por una regla, dice por qué y lo descarta;
- si pasa del límite por hora (30 reportes), lo deja en la bandeja.

### 4.3 Seguir sus reportes ("Mis Reportes", US-010, US-023)

- Cada reporte con su **estado técnico** (En cola, Sellando, Sellado) y su **estado editorial** (En revisión, Publicado, Rechazada con su motivo, Retirado), como etiquetas de color, siempre con su palabra (it. 40f).
- **"Ver recibo":** el **Recibo de Inmutabilidad**, con la huella, el sello en Stellar, el ledger y el enlace al explorador.
- El veedor **nunca ve un error de sellado**: los reintentos y las fallas los atiende el sistema.

---

## 5. Administrador de Organización

Su panel está en el sitio de su veeduría. En el computador tiene una barra lateral; en el celular, pestañas con ícono y "Más".

### 5.1 La bandeja de revisión ("Bandeja", US-036, US-037)

| Función | Qué hace |
|---|---|
| **Revisar** | Las evidencias **selladas y ocultas**, de una en una: de qué obra son, qué veedor las envió, la clasificación, el comentario, las fotos y la marca de hora sospechosa. Cuántas zonas se difuminaron en el celular, y un aviso si el veedor quitó un difuminado que el detector propuso (it. 46e). La pestaña dice cuántas hay. |
| **Dónde se tomó** | Cada evidencia dice a qué distancia de la obra se tomó ("Tomada a 120 m de la obra."), sin las coordenadas del veedor. La que fijó la ubicación oficial de una obra que no la tenía llega marcada, con el punto en un mapa pequeño y **Corregir ubicación**; al rechazarla, se avisa que la ubicación no cambia (it. 45f). Si ese primer reporte se tomó lejos del municipio del contrato o con mala señal, no la fijó: llega **por confirmar**, con el motivo, el punto, **Confirmar esta ubicación** y **Corregir ubicación** (it. 46f). |
| **Publicar** | Pasa al mapa público. No hay publicación masiva. |
| **Rechazar con motivo** | Nunca se publica; el veedor ve el motivo. |
| **Retirar una publicada** | Con motivo: en el mapa queda una lápida. No se borra nada, y el sello en Stellar sigue. |
| **El resumen diario** | A las 07:00, un correo con cuántas evidencias esperan su revisión, solo si hay (US-060-MON, it. 43i). |
| **El aviso de fallas de sellado** | Un banner rojo si hay evidencias que no se pudieron certificar: "El soporte técnico de GovTrace tiene que revisarlas" (US-021, it. 45e). |

Todo queda en el log de auditoría: quién, cuándo, antes y después.

### 5.2 Las obras y los contratos

| Función | Qué hace | Historia |
|---|---|---|
| **Obras** | Las obras de su organización, con su estado. Una obra aparece con el primer reporte de uno de sus contratos. | US-027 |
| **Corregir la ubicación oficial** | Mueve el punto de una obra mal ubicada; la geocerca de los veedores sigue el punto nuevo. | US-035 |
| **Agrupar contratos en una ficha de obra** | Varios contratos de la misma obra física, en una ficha con su nombre. Gana el peor estado. | US-045-INT |
| **Descargar el expediente** | Un ZIP con el **expediente en PDF**, las plantillas pre-llenadas del **derecho de petición** (Ley 1755 de 2015) y de la **denuncia ante la Contraloría**, que citan cada sello como "Prueba Pericial Criptográfica", y los **originales con su prueba**. Solo lo publicado. Cada descarga queda en el log. | US-056-LEG |
| **Contratos** | Los contratos de obra de su territorio, sincronizados de SECOP II, con un buscador. | US-015 |
| **Territorio** | Los departamentos o municipios que vigila. Al cambiarlo, se sincroniza SECOP de inmediato. | US-012 |

### 5.3 El equipo ("Veedores")

| Función | Qué hace | Historia |
|---|---|---|
| **Invitar a un veedor** | Por correo. El enlace vence a las 48 horas (parámetro). | US-005 |
| **Ver el equipo** | Cada veedor con su estado: Activo, Invitación pendiente, Invitación vencida o Inactivo; y si ya declaró sus impedimentos. | US-005, US-057-LEG |
| **Reenviar o revocar una invitación** | Mientras no se acepte. | US-040-USR |
| **Desactivar a un veedor** | Pide confirmar. Su sesión se cierra de inmediato y sus reportes se conservan. | US-006 |
| **Reactivarlo** | Vuelve con la misma cuenta. | US-041-USR |
| **Los administradores** | La lista de los administradores de la organización, con su estado, e **"Invitar a otro administrador"**, para que la veeduría no dependa de una sola persona. Desactivar a un administrador lo hace el Super Administrador. | US-061-USR (it. 43j) |

### 5.4 Los informes de los ciudadanos (US-059-LEG)

- **"Informes ciudadanos":** lo que los ciudadanos le escriben desde cada obra, con la foto si la hay, **sin el correo** de quien lo envió.
- **Responder:** la respuesta le llega al ciudadano por correo, desde GovTrace.
- **Descartar:** queda registrado.
- **Retención** (it. 45c): 30 días después de atendido, se borra el correo del informe; 30 días después de descartado, se borra entero.

### 5.5 La organización

| Función | Qué hace | Historia |
|---|---|---|
| **Nombre y logo** | El nombre de fantasía (3 a 100 caracteres) y el logo: PNG, JPG o SVG, hasta 2 MB, mínimo 128×128 px. Un SVG con código se limpia. Los veedores y el sitio público los ven de inmediato. | US-007 |
| **Contacto público** | Un correo y, si quiere, un teléfono, que se ven en el pie de su sitio. | US-007 (it. 43h, V13) |
| **Sus datos legales** | Los ve sin poder editarlos: el nombre legal, el NIT o la inscripción y el subdominio. Solo el Super Administrador los cambia. | US-011, R-TA-03 |
| **Resumen del territorio** | Las obras por color, las evidencias por clasificación y por mes, y los veedores activos. | US-049-RPT |
| **Exportar a CSV** | Las obras y las evidencias de su organización, con el veedor como seudónimo. | US-050-RPT |
| **Autorizar al Super Administrador** | Por 30 días, para que reporte en nombre de la organización. Una autorización a la vez, revocable. | US-042-SEC |
| **Registro de auditoría** | Lo que pasó en su organización, en frases, paginado. | US-043-MON |

---

## 6. Super Administrador

Su panel está en el dominio central: Organizaciones, Solicitudes de alta, Sellado, SECOP, Uso, Parámetros y Auditoría.

### 6.1 Las organizaciones

| Función | Qué hace | Historia |
|---|---|---|
| **Dar de alta una organización** | El nombre, el **NIT** (con dígito de verificación de la DIAN) o la **inscripción** (resolución o acta, y la personería o cámara de comercio que la registró), o los dos, y el subdominio. En el mismo paso, opcionalmente, su Administrador inicial. Al registrarla se sincroniza SECOP. | US-001, US-002, it. 44d |
| **Las solicitudes de alta** | "Solicitudes de alta", con cuántas hay en el menú. **"Aprobar y dar de alta"** abre la Nueva organización precargada; al registrarla, la solicitud queda aprobada. **"Rechazar"** pide un motivo, que le llega al contacto. | US-062-ALT (it. 43k) |
| **Comprobar la inscripción** | Junto a cada solicitud, **lo que dicen los datos abiertos del RUES** (Confecámaras) de su NIT o su matrícula: razón social, cámara, estado y la fecha de los datos. Una veeduría inscrita en una personería no está ahí; si el RUES no responde, se sigue. **"Descargar el PDF que adjuntó"**. En la Nueva organización, **"Consultar en el RUES"**. La decisión queda en la auditoría con lo que dijo el RUES. Al aprobarla, el PDF queda con la organización (**"Documento de inscripción (PDF)"** en su fila). | US-062-ALT, US-001 (it. 46b) |
| **El registro de auditoría, con filtros** | Se filtra por fecha, por quién lo hizo, por tipo de acción y por organización; "Quitar filtros" lo muestra todo. Cada entrada es una frase, con el antes y el después desplegables. El Administrador de una organización ve y filtra solo lo suyo. | US-043-MON (it. 46d) |
| **Corregir los datos legales** | El NIT, la inscripción y la **razón social**, a solicitud formal, con las reglas del alta; el log guarda los anteriores y los nuevos. | US-011, it. 43f (V8) |
| **Los administradores de cada organización** | Ve quiénes son y en qué va su invitación. **Asigna** el primero o **agrega otro**. **Reenvía o revoca** una invitación. **Desactiva** al que se fue y lo **reactiva**; nunca al único activo. | US-002, it. 43a, US-061-USR (it. 43j) |
| **Suspender y reactivar** | Suspendida, nadie de la organización entra ni reporta, y su mapa sigue a la vista con un aviso. Reactivarla sincroniza SECOP. | US-003a |
| **Dar de baja** | Con doble confirmación: escribir el subdominio, en 10 minutos. Es definitivo. El mapa sale de línea y lo sellado sigue verificable. Los archivos se guardan 5 años. | US-003b |
| **La verificación en dos pasos** | **Inactiva por ahora:** la activa el operador en el servidor (`SUPER_ADMIN_TWO_FACTOR=true`). Activa, entra con su contraseña y el código de 6 dígitos de su app autenticadora. La primera vez la configura: escanea un código QR y guarda 8 códigos de recuperación, que sirven una vez cada uno. Sin teléfono ni códigos: `make admin-2fa-reset EMAIL=…` en el servidor. | US-065-SEC (it. 46g) |
| **Los Super Administradores** | "Super Administradores": quiénes son y en qué va cada uno. **Invita** a otro por correo, **reenvía o revoca** una invitación, **desactiva** al que se fue y lo **reactiva**. Nadie se desactiva a sí mismo, y la plataforma nunca queda sin uno activo, ni aunque dos se desactiven el uno al otro a la vez. Con uno solo activo, el panel lo avisa en todas sus pantallas y llega una alerta. Las alertas les llegan solo a los activos. | US-063-USR (it. 46a) |
| **Reportar en nombre de una organización** | Solo si lo autorizó: el listado dice hasta cuándo. Es el mismo formulario del veedor, con las mismas reglas; el reporte va al log y pasa por la revisión de la veeduría. | US-042-SEC (it. 43g, V7) |

### 6.2 La operación

| Función | Qué hace | Historia |
|---|---|---|
| **Sellado** | El saldo de la cuenta patrocinadora frente a su umbral, la vigencia del contrato de sellado en Stellar y las evidencias en "Falla de Sellado", para **reencolarlas**. | US-020b, US-021, US-022, US-047-MNT |
| **Costos de sellado** | Las comisiones pagadas en XLM, por organización. | US-004 |
| **SECOP** | La salud de cada sincronización: cuándo corrió, qué trajo, qué no pudo emparejar con la DIVIPOLA. **"Sincronizar ahora"**, sin esperar a la madrugada. | US-014, it. 43b (V15) |
| **Uso** | Por organización: su estado, los veedores activos, las evidencias recibidas, publicadas, rechazadas y retiradas, y su última actividad. | US-053-RPT |
| **Parámetros** | El radio de la geocerca, la ventana de los contratos terminados, la vigencia de las invitaciones, el umbral de saldo de la patrocinadora, la hora de la sincronización SECOP, en hora de Colombia, y la distancia al municipio para fijar una obra (it. 46f). Cada cambio queda en el log y rige desde el minuto siguiente. | US-038-CFG |
| **Auditoría global** | El log de todas las organizaciones: quién, cuándo, qué, antes y después. | US-043-MON |
| **Las alertas por correo** | La cola de sellado estancada más de 2 horas; el saldo bajo o agotado de la patrocinadora; la vigencia del contrato por vencer; las organizaciones con 30 días sin actividad. | US-021, US-022, US-054-RPT |

---

## 7. Lo que hace el sistema solo

Las horas son de Colombia (it. 45a).

| Cuándo | Qué | Historia |
|---|---|---|
| **Al recibir un reporte** | Lo sella en Stellar: la raíz de Merkle de sus archivos y metadatos, con la comisión pagada por la cuenta patrocinadora. Una transacción a la vez por cuenta, por turnos. Reintenta a 1, 5, 15 y 60 minutos y, tras 5 intentos, lo deja en "Falla de Sellado". | US-020b, US-021, it. 39 |
| **Cada 15 minutos** | Revisa la cola (alerta si algo lleva más de 2 horas) y el saldo de la patrocinadora (alerta bajo el umbral). | US-021, US-022 |
| **01:00** | Borra los archivos de las organizaciones dadas de baja hace 5 años. | US-003b |
| **01:15** | La retención de los informes ciudadanos. | it. 45c |
| **01:20** | Borra las solicitudes de alta decididas hace 30 días. | US-062-ALT |
| **01:30** | Borra la relación seudónimo-veedor de quien lleva 5 años sin reportar. | R-MNT-03 |
| **02:00** (parámetro) | Sincroniza los contratos de obra de SECOP II del territorio de cada organización activa. Solo de tipo "Obra", sin duplicados y sin borrar nada. Guarda solo los 13 campos que usa, sin los datos personales de representantes y supervisores. | US-013, US-032, US-033, it. 45c |
| **03:00** | Calcula las obras en riesgo: contratos en ejecución con la fecha de fin vencida. | US-034 |
| **05:00, el día 1 de cada mes** | Archiva los contratos cerrados hace más de 5 años y sin evidencias. | US-048-MNT |
| **07:00** | La vigencia del contrato de sellado, con aviso 30 días antes, y el resumen diario a los administradores. | US-022, US-060-MON |
| **08:00** | Avisa de las organizaciones con 30 días sin actividad. | US-054-RPT |
| **Cada hora** | Una copia de respaldo de todas las bases y de los archivos de evidencia, guardada 30 días, con réplica fuera del sitio si está configurada. | R-BCK |

---

## 8. El operador técnico (consola)

No es un rol de la aplicación, pero es parte de operarla. Todo está en `make help` y en `CLAUDE.md`.

- **Levantar el entorno:** `make setup` la primera vez, y `make up`. Una demostración entera con un comando: `make demo`, con `LUGAR="lat,lng"` y `TERRITORIO=medellin`.
- **Crear el primer Super Administrador:** `make admin EMAIL=…`. La contraseña se pregunta o se genera; nunca va en la línea de comandos.
- **Los correos de desarrollo:** `make invites` muestra los enlaces y códigos; `make mail` da la dirección del buzón Mailpit, que tiene una copia de cada correo.
- **Respaldos:** `make backup-now`, `make restore-drill`, `make backup-check`. El S3 de desarrollo guarda en disco (`make storage-check`), y `make storage-restore` recupera fotos desde una copia.
- **La red Stellar:** `make network-deploy NETWORK=testnet|mainnet`, `make network-extend` y el verificador independiente.
- **Calidad:** `make test-all`, `make e2e`, `make ux-check`, `make trace-check`, `make audit`, `make secrets-check`, `make staging-check`.

---

## 9. Reglas que valen para todos

- **La evidencia no se cambia ni se borra.** Cada reporte se sella en Stellar. El validador, el verificador independiente y la prueba descargada lo comprueban sin depender de GovTrace. Retirar deja una lápida.
- **Privacidad:**
  - el veedor aparece en lo público solo con un **seudónimo**;
  - la ubicación pública va **aproximada** (unos 100 m);
  - las fotos se publican **sin metadatos** y con los **rostros difuminados** en el celular (it. 46e);
  - en la red pública solo va la huella de cada evidencia: ningún dato personal.
- **Cada veeduría es independiente:** una base de datos por organización. El Super Administrador no reporta en una sin su autorización.
- **Todo cambio importante va al log de auditoría,** que no se puede editar.
- **Ley 1581 de 2012:**
  - la política de datos, enlazada donde se entra;
  - la autorización al activar una cuenta, al informar a una veeduría y al pedir un alta;
  - la retención: 30 días para los correos de los informes atendidos, los informes descartados y las solicitudes decididas.
- **Límites de abuso:** 30 reportes por veedor por hora; 120 peticiones públicas por minuto; 10 descargas de datos abiertos por minuto; 10 códigos y 20 informes ciudadanos por hora; 5 solicitudes de alta por hora. Todos por conexión.
- **Para cualquiera** (R-UX):
  - letra de 16 px y botones de 44 px;
  - lenguaje claro, sin jerga técnica (ni "blockchain" ni "hash" en las pantallas de las personas);
  - el color nunca va solo: siempre con una palabra o un ícono;
  - WCAG 2.2 AA, medido con axe en cada pantalla (`make ux-check`).
- **Todo en español, y los correos de usted,** con la identidad de GovTrace (it. 45b).
- **Seguridad en producción:** HTTPS con HSTS, CSP y cabeceras, cookies seguras, proxies de confianza, auditoría de dependencias y ninguna llave en el repositorio.

---

## 10. Lo que falta

Los vacíos de flujo de `docs/mapa-funcional.md` quedaron todos cerrados, del V1 al V16. Lo que queda para salir a producción depende de afuera:

| Qué | De quién depende |
|---|---|
| Los datos del operador (`PRIVACY_CONTROLLER_*`): sin ellos, la política es un borrador | El usuario |
| La revisión legal: "Prueba Pericial Criptográfica", los impedimentos y el texto de la política | Un abogado |
| Las 3 credenciales de testnet en Jenkins | El usuario |
| Staging en AWS: HTTPS de verdad, SES y secretos en un gestor (42b) | La cuenta de AWS |
| La llave de la selladora en AWS KMS, y la salida a la red principal (37b) | La cuenta de AWS y el proveedor de RPC |
| La prueba de usabilidad con 5 personas (40d) | Las personas |
| Decisiones abiertas: doble factor para el Super Administrador, seguimiento de errores, prueba de carga | El usuario |

### Funciones que convendría agregar

Revisión del 2026-10-01, sobre el código. Ninguna bloquea el MVP; van de mayor a menor peso frente al Problem Brief.

1. **Sellar también lo que dice el SECOP II.**
   - Hoy el sello cubre la evidencia del veedor: los archivos y sus metadatos (fecha, GPS, clasificación, comentario y seudónimo, en `SealedMetadata`), más una referencia cifrada a la obra.
   - No cubre el estado oficial del contrato en ese momento: estado, fecha de fin, valor. La sincronización lo sobrescribe cada día sin guardar historial.
   - El Problem Brief promete "sellar ambas partes" para demostrar las actas retroactivas. Si mañana el SECOP II muestra una suspensión con fecha de ayer, hoy GovTrace no puede probar que ayer no estaba.
   - Dos caminos:
     - meter los datos del contrato en los metadatos de cada reporte (barato, pero cambia el formato sellado y el verificador);
     - guardar el historial de cada contrato y sellar cada día una raíz de Merkle de lo sincronizado (una transacción diaria, entre 0,24 y 0,28 XLM, lo que costó cada sello en testnet).
2. **Alertar los cambios retroactivos.** Con ese historial, avisar a la veeduría y mostrar en la ficha cuando el SECOP II cambia la fecha de fin, el estado o el valor de una obra que ya tiene evidencias selladas. Es el detector del "maquillaje" que describe el Problem Brief.
3. **Avisar al veedor de la decisión sobre su reporte.** Hoy solo la ve en "Mis Reportes": no le llega un correo cuando su reporte se publica o se descarta, ni la razón.
4. **Seguir una obra.** Un ciudadano o periodista deja su correo, verificado como en los informes ciudadanos, y recibe un aviso cuando se publica una evidencia nueva o cambia el estado de la obra.
5. **La réplica de la entidad o del contratista.** Un canal para que respondan a una evidencia publicada, visible junto a ella. Baja el riesgo legal por el buen nombre; depende de la revisión legal.
6. **Video corto.** Hoy se aceptan JPG, PNG y PDF. Un video muestra mejor una obra parada; con un límite de duración y de tamaño, por el almacenamiento.
7. **Los derechos del titular, por autoservicio.** La política explica los derechos de la Ley 1581, pero se ejercen escribiendo al responsable. Un veedor no puede descargar sus datos ni pedir que se borren desde la app; con pocos usuarios, el correo alcanza.

---

## 11. Las historias, por rol

Las 64 historias, con su `features/*.feature`. Cada escenario tiene un test con su nombre (`make trace-check`).

| Rol | Historias |
|---|---|
| **Visitante o ciudadano** | US-017, US-024, US-025, US-026, US-027, US-028, US-029, US-046-INT, US-051-RPT, US-052-RPT, US-055-LEG, US-058-LEG, US-059-LEG |
| **Veeduría solicitante** | US-062-ALT |
| **Veedor de Campo** | US-008, US-009, US-010, US-016, US-018, US-019, US-023, US-030, US-031, US-039-USR, US-057-LEG |
| **Administrador de Organización** | US-005, US-006, US-007, US-012, US-015, US-035, US-036, US-037, US-040-USR, US-041-USR, US-042-SEC, US-043-MON, US-045-INT, US-049-RPT, US-050-RPT, US-056-LEG, US-059-LEG, US-060-MON, US-061-USR |
| **Super Administrador** | US-001, US-002, US-003a, US-003b, US-004, US-011, US-014, US-038-CFG, US-042-SEC, US-043-MON, US-047-MNT, US-053-RPT, US-061-USR, US-062-ALT |
| **El sistema** | US-013, US-020a, US-020b, US-021, US-022, US-032, US-033, US-034, US-044-MON, US-048-MNT, US-054-RPT, US-060-MON |

Más detalle:
- `specs/SPEC.md`: la especificación;
- `specs/PLAN.md`: cada iteración, con sus decisiones y pruebas;
- `docs/mapa-funcional.md`: los flujos de punta a punta;
- `docs/estado-mvp.md`: dónde quedó y qué falta para producción.
