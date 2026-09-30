# V2 — El ciudadano que reporta

**Documento de diseño. Borrador del 2026-09-30.** Nada de esto está programado. Es la base para el `/discovery` de la V2, que lo convertirá en historias, criterios y `.feature`.

> **En espera (2026-09-30).** Antes de seguir, se revisó el proceso actual del control social: `docs/proceso-actual.md`.
> - Sus "puertas" ya tienen base legal: el ciudadano informa a la veeduría (Ley 850, art. 18 a)) y denuncia (Ley 1757, art. 69).
> - La primera, "informar a esta veeduría" y sin sellar, se propone adelantar al MVP v1 (A2).
> - Este documento se retoma para lo que quede en la V2: la evidencia ciudadana sellada, constituir una veeduría y postularse como veedor.

"V2" es aquí la **versión 2 del producto**. No es el vacío V2 de `docs/mapa-funcional.md` (el Administrador de una organización, cerrado en la it. 43a).

## 1. La decisión

> Para la V2, el ciudadano de a pie debe ser un participante activo y no un simple espectador. Necesitamos un modelo donde el ciudadano pueda reportar, pero protegiendo el presupuesto de XLM. — el usuario, 2026-09-30

**Esto reabre una decisión cerrada.** En el discovery del MVP, el modelo de participación quedó **cerrado**: solo aportan evidencia los veedores invitados por una organización.
- `specs/SPEC.md:16` dice: "Modelo cerrado: solo veedores invitados por una organización aportan evidencia".
- `sessions/cristhian-barros/SHARED-MEMORY.md:286` añadía: "El ciudadano abierto/anónimo no entra ni al MVP ni al roadmap".
- La duda original del usuario sigue vigente y guía este diseño: *"Inicialmente mi idea era que cualquier ciudadano pueda aportar evidencia (pero el registro podría ocasionar fricción)"* (`SHARED-MEMORY.md:354`).

Cuando el `/discovery` de la V2 lo confirme, cambian `SPEC.md` (§ participación y actores) y `docs/mapa-funcional.md` §0.

**Lo que no cambia:**
- la evidencia inmutable, con su sello en Stellar y su prueba de inclusión;
- toda evidencia nace **Oculta** y la organización decide qué se publica (US-036);
- la privacidad en origen: sin EXIF y con coordenadas públicas aproximadas (R-PRIV-01 a 06);
- el GPS de 50 m o menos (US-008) y la geocerca (R-GEO-01).

## 2. El problema: hoy cada reporte es una transacción

**Cómo se sella hoy:**
- Cada reporte es un árbol de Merkle. Sus hojas son el SHA-256 de cada archivo y el de sus metadatos (fecha, clasificación, comentario, lugar exacto y seudónimo del veedor). Ver `app/Application/Sealing/PrepareReportSeal.php:26-28` y `app/Domain/Sealing/SealedMetadata.php:31-41`.
- Su raíz va en **una transacción**, `seal(worksite, root)` (`contracts/sealing/src/lib.rs:67`):
  - la firma la selladora y la paga la patrocinadora, con un *fee bump* (`app/Infrastructure/Stellar/StellarSealingNetwork.php:207-218`);
  - `worksite` es `sha256("organización:obra")`.

**Lo que cuesta:**

| Medición | XLM por sello | Fuente |
|---|---|---|
| Testnet, régimen | 0,2425 | `specs/PLAN.md:476` |
| Testnet, it. 37a | 0,2707 | `specs/PLAN.md:1485` |
| Testnet, 7 sellos seguidos (it. 39) | 0,2845 | `specs/PLAN.md:1568` |
| Red principal | sin medir (it. 37b pendiente) | — |

- Hoy: 100 reportes al día son unos **24,4 XLM al día** (`specs/PLAN.md:455`).
- La patrocinadora arranca con 100 XLM. Avisa bajo 50 XLM (`sponsor_balance_alert_threshold_xlm`) y **pausa todo el sellado** bajo 2 XLM (`SealingPause`, `app/Jobs/SealReport.php:76-99`).
- El precio del XLM cambia, así que el presupuesto se piensa en XLM, no en pesos.

**Lo que abrir la puerta pone en riesgo:**

| Qué | Por qué con ciudadanos | Hoy |
|---|---|---|
| **El saldo de XLM** | Si cada reporte es una transacción, el costo crece con el volumen. Un solo atacante con un script puede vaciar la patrocinadora. | 30 reportes por hora por veedor (`config/limits.php:10`); los veedores son pocos y conocidos. |
| **El turno de la selladora** | Stellar admite una transacción pendiente por cuenta: unos 10 a 12 sellos por minuto, **para todas las organizaciones juntas** (`SealerTurn`, `specs/PLAN.md:1555`). Una avalancha de ciudadanos en una veeduría retrasa los sellos de los veedores de todas. | No hay prioridad entre sellos. |
| **El tiempo del Administrador** | Todo nace Oculto y se publica de a uno (US-036). El spam llena su Bandeja. | Solo reportan veedores de confianza. |
| **El almacenamiento** | Hasta 5 fotos de 10 MB por reporte. Nada se borra físicamente (`SPEC.md:20`): el spam se guardaría para siempre, y con fotos de personas (Ley 1581). | Volumen pequeño. |
| **La credibilidad del mapa** | Un contratista, o su competencia, puede inundar una obra de reportes falsos. | El Administrador filtra. |

**La geocerca y el GPS filtran errores, no atacantes.** La ubicación de un navegador se simula en DevTools, lo mismo que haremos en la demostración (`docs/local-environment-setup.md`). Siguen valiendo como filtro de calidad, pero la protección del presupuesto no puede depender de ellos.

## 3. Principios

1. **El gasto en XLM no depende del número de reportes ciudadanos.** Tiene un techo por día, fijado de antemano.
2. **Un ciudadano nunca dispara una transacción por sí solo.** Su reporte entra en un lote.
3. **Los veedores no esperan detrás de los ciudadanos.** Sus reportes se siguen sellando uno a uno y tienen prioridad en el turno.
4. **Cuando falta dinero, el ciudadano se detiene primero.** El sellado ciudadano se pausa mucho antes que el de los veedores.
5. **No se pide más de lo necesario.** Cada dato personal tiene su finalidad (Ley 1581). Quien denuncia una obra puede estar en riesgo, así que su identidad nunca es pública.
6. **Poca fricción.** Reportar no puede exigir más que un correo y un código.

## 4. El modelo propuesto

### 4.1 Sellado por lotes (la pieza central)

**Cómo funciona:**
- El reporte ciudadano se recibe y valida igual que el de un veedor: archivos, hashes, GPS, geocerca, hora de captura.
- En lugar de ir a la cola de sello propio, espera el **próximo lote** de su organización.
- Un lote es un árbol de Merkle cuyas hojas son las raíces de los reportes. Su raíz se sella con **una** transacción: `seal(lote, raíz_del_lote)`.
- **No hace falta cambiar el contrato**, que no se puede actualizar (`lib.rs:9-14`): `seal` acepta cualquier par de 32 bytes.
- Cuándo sale un lote:
  - **como máximo uno por hora**, y solo si hubo al menos un reporte;
  - o antes, si junta **N** reportes (por ejemplo 500), para que un día con mucho volumen no espere.
  - Nunca se sella un lote vacío.

**Cuánto cuesta** (XLM de testnet por sello, entre 0,2425 y 0,2845):

| Modelo | Transacciones al día | XLM al día | XLM al mes |
|---|---|---|---|
| Hoy: 100 reportes de veedores | 100 | 24–28 | 730–855 |
| 1.000 reportes ciudadanos, **uno a uno** | 1.000 | 243–285 | 7.300–8.500 |
| 1.000 o 10.000 reportes, **un lote por hora por organización** | ≤ 24 por organización | ≤ 5,8–6,8 por organización | ≤ 175–205 por organización |
| Lo mismo, **un lote cada 10 minutos** | ≤ 144 por organización | ≤ 35–41 por organización | ≤ 1.050–1.230 por organización |

- El techo lo fija la ventana, no el volumen.
- Con una hora, el ciudadano espera hasta 60 minutos por su sello. Con 10 minutos espera menos, pero el techo sube a 144 transacciones al día. Con poco volumen, casi cada reporte sale en su propio lote y no se ahorra nada.
- Como nunca se sella un lote vacío, un lote nunca cuesta más que sellar uno a uno. La ventana es un parámetro: define cuánto espera el ciudadano a cambio de cuánto techo.

**Tres cambios que el lote obliga a hacer:**
1. **La obra tiene que ir dentro del árbol.** Hoy va como argumento de `seal`, fuera del árbol. En un lote, ese argumento identifica al lote. Cada reporte tiene que comprometer su obra adentro: por ejemplo, como una hoja más, o con `sha256(raíz_del_reporte ‖ obra)` como hoja del lote.
2. **Una prueba de inclusión nueva** (`format` 2), en dos niveles: archivo → reporte y reporte → lote.
   - La de hoy trae todas las hojas del reporte (`app/Application/Publication/InclusionProof.php:29-44`). La del lote trae solo el camino, no las miles de hojas.
   - El validador del navegador (US-024) y el verificador independiente (`tools/verify`, US-046-INT) tienen que aceptar las dos.
3. **El estado técnico que ve el ciudadano:** "Recibido · se sella en el próximo lote (antes de las 10:40)", y después "Sellado", como hoy.

**Sellar al recibir, no al publicar.** Con lotes, sellar todo lo recibido no cuesta más, porque las hojas son gratis. Así se conserva la garantía central: el archivo existía así desde que llegó, y nadie pudo cambiarlo después. Esto incluye a la organización, que podría sentir presión para no aceptar un reporte incómodo.

### 4.2 Quién puede reportar y cuánto

| Nivel | Qué necesita | Cuánto puede reportar | Cómo se sella |
|---|---|---|---|
| **Visitante** | nada | no reporta; mira y verifica, como hoy | — |
| **Ciudadano verificado** | correo, con un código de un solo uso | por ejemplo 3 al día y 1 por obra al día | en lote |
| **Ciudadano de confianza** | N reportes publicados por la organización, sin rechazos recientes | más, por ejemplo 10 al día | en lote |
| **Veedor** (invitado, como hoy) | la invitación de la organización | 30 por hora, como hoy | uno a uno, con prioridad |

- **Correo antes que celular:** el código por correo es gratis. Un SMS cuesta dinero por cada envío: sería un segundo presupuesto que proteger.
- **El seudónimo ya existe.** Los metadatos sellados llevan el del veedor, no su nombre (`SealedMetadata.php`), y la tabla seudónimo → persona se guarda 5 años (R-MNT-03). El ciudadano usaría el mismo mecanismo:
  - la organización ve "Ciudadano 7F3A", no un nombre;
  - el público no ve a nadie;
  - quién es cada seudónimo solo lo sabe la plataforma, para atender un abuso o una orden judicial.
- **Las cuotas se cuentan** por identidad verificada, por dispositivo y por IP. Ninguna basta sola; juntas encarecen el abuso.
- **Un archivo con el mismo SHA-256 que otro ya recibido** se rechaza.

### 4.3 El presupuesto, con techo y con orden

- **Una bolsa aparte para los lotes ciudadanos:** un techo de XLM al día, global y por organización, como parámetros.
  - Si se alcanza, los lotes se espacian (por ejemplo, uno cada 6 horas) en vez de detenerse.
  - Ningún reporte se pierde: su hash ya está guardado y entra en el próximo lote.
- **El ciudadano se detiene primero:**
  - bajo el umbral de alerta (50 XLM), se pausan los lotes ciudadanos;
  - los veedores siguen sellando hasta la pausa general de 2 XLM, como hoy.
- **Prioridad en el turno.** Un lote toma el turno de la selladora solo si ningún sello de veedor está esperando. La alternativa es una **segunda selladora** solo para los lotes: más rendimiento y aislamiento, a cambio de una cuenta más que fondear y custodiar (D11).
- **Lo que ya existe:**
  - `CheckSponsorBalance` avisa cada 15 minutos por correo y *webhook*;
  - `report_seals.fee_stroops` guarda el costo real de cada sello;
  - el panel de Uso (it. 32) mostraría las dos bolsas por separado.

### 4.4 Moderación y almacenamiento

- **La Bandeja distingue** los reportes de veedores de los de ciudadanos (filtro y etiqueta). La publicación sigue siendo de a una (US-036).
  - Descartar el spam: ❓ ¿de a uno, o en bloque solo para el Rechazo?
- **Un reporte ciudadano no cambia el estado de la obra hasta que se publica.** Hoy solo cuentan los publicados (`WorksiteCondition`), y así sigue.
- **Los archivos de un reporte rechazado** se borran a los X días. Su hash, su prueba y su sello quedan, igual que en la baja de una organización (it. 33). R-MNT-02 ya lo prevé: las pruebas se conservan aunque se borren los archivos.
  - Esto enmienda "nada se borra físicamente" (`SPEC.md:20`), solo para lo rechazado.
  - Es necesario por el almacenamiento y por la Ley 1581: fotos de personas que nadie va a publicar.
- **El ciudadano tiene menos formación que un veedor.** Hay que esperar más fotos de menores o de terceros. La moderación las atrapa antes de publicar (R-PRIV-05).

### 4.5 Dónde y cómo reporta

- **Desde la página pública de una obra**, con **Reportar esta obra**. El reporte va a la organización de esa página, que es la que vigila la obra: no hace falta elegir organización.
- **Una sola identidad para todas las veedurías**, verificada una vez en la base central, con un seudónimo distinto en cada una. Así una organización no puede cruzar a sus ciudadanos con los de otra.
- **El mismo formulario del veedor**, en la misma app instalable (it. 43b). Incluye el modo sin conexión, con sus límites de la bandeja de salida: 10 reportes y 50 MB (`resources/js/lib/outbox.js:10`).
- **"Mis reportes" del ciudadano:** su recibo y en qué va cada uno.

### 4.6 Ley 1581 (hoy pendiente, en la V2 obligatoria)

En el MVP, la protección de datos personales es una decisión pendiente (`docs/estado-mvp.md:76`). Con ciudadanos que dan su correo, la V2 no puede salir sin:
- la **política de tratamiento** publicada;
- la **autorización** de cada ciudadano al verificar su correo, con la finalidad: atender sus reportes y prevenir abusos;
- sus **derechos** para conocer, actualizar y suprimir sus datos. Ojo: lo sellado no se puede suprimir. Su seudónimo y sus hashes quedan en la red; su correo, no;
- ❓ **quién es el responsable del tratamiento:** GovTrace, cada veeduría o ambos.

## 5. Qué habría que cambiar (sin programar todavía)

| Pieza | Cambio |
|---|---|
| Contrato Soroban | **Ninguno.** `seal(lote, raíz)` ya sirve. |
| Sellado | Los lotes por organización: armar el árbol, sellar y guardar el camino de cada reporte. La prioridad en `SealerTurn`, o una segunda selladora. |
| Pruebas y verificación | La prueba `format` 2; el validador del navegador y `tools/verify` con las dos; la obra dentro del árbol del reporte. |
| Identidad | El ciudadano en la base central: correo con código, seudónimo por organización y cuotas. |
| Presupuesto | Parámetros para la ventana del lote, el tamaño N, el techo por día y la pausa ciudadana. El panel de Uso con dos bolsas. |
| Interfaz | **Reportar esta obra** en la página pública, "Mis reportes" del ciudadano y el filtro de la Bandeja. |
| Retención | Borrar los archivos de lo rechazado a los X días. |
| Legal | La política de tratamiento, la autorización y los derechos (Ley 1581). |

## 6. Decisiones para el usuario ❓

Cada una lleva la recomendación de este borrador. El `/discovery` de la V2 las cierra.

| # | Decisión | Recomendación |
|---|---|---|
| D-V2-01 | ¿Anónimo, seudónimo verificado o identificado? | **Seudónimo verificado por correo.** El anónimo sin verificación no permite cuotas, y el identificado pone en riesgo a quien denuncia. |
| D-V2-02 | ¿Se sella al recibir (en lote) o solo al publicar? | **Al recibir, en lote.** Cuesta lo mismo y conserva la garantía frente a la propia organización. |
| D-V2-03 | La ventana del lote y su tamaño N | **Una hora y 500.** El ciudadano espera hasta una hora; el costo queda en ≤ 24 transacciones al día por organización. |
| D-V2-04 | ¿Lote por organización o uno solo para todas? | **Por organización.** Respeta el aislamiento de cada veeduría. Uno global costaría menos (≤ 24 al día en total), pero mezcla organizaciones en el mismo árbol. |
| D-V2-05 | Las cuotas por nivel | **3 al día y 1 por obra al día** para el verificado; **10 al día** para el de confianza. |
| D-V2-06 | El techo de XLM al día para los lotes | Una cifra del producto: por ejemplo, la mitad de lo que hoy gastan los veedores. |
| D-V2-07 | ¿Prioridad en el turno o una segunda selladora? | **Prioridad primero.** Con ≤ 24 lotes al día por organización, el turno sobra; una segunda selladora solo si hay muchas organizaciones. |
| D-V2-08 | ¿El público distingue un reporte ciudadano de uno de veedor? | **Sí, con una etiqueta.** Da contexto sin quitarle valor: los dos pasaron por la organización. |
| D-V2-09 | ¿Se borran los archivos de lo rechazado, y a los cuántos días? | **Sí, a los 30 días**, dejando el hash, la prueba y el sello. |
| D-V2-10 | ¿Quién es el responsable del tratamiento (Ley 1581)? | Requiere asesoría legal. |
| D-V2-11 | ¿El ciudadano de confianza puede convertirse en veedor? | Sí, por invitación de la organización, como hoy. |

## 7. Siguiente paso

1. **El usuario revisa este documento** y decide D-V2-01 a D-V2-11, o las deja para la entrevista.
2. **`/discovery` de la V2** (BDD 2.0, 5 etapas) sobre esta base:
   - enmienda `SPEC.md` (el modelo de participación, los actores y las reglas nuevas);
   - escribe las historias con sus criterios YAML y sus `.feature`.

   Épicas probables:
   - reportar como ciudadano;
   - el sellado por lotes y su prueba de dos niveles;
   - el presupuesto con techo;
   - la moderación de lo ciudadano;
   - la Ley 1581.
3. **`/plan`:** el sellado por lotes y su prueba van primero. Es lo de más riesgo (criptografía y verificación) y lo que protege el presupuesto. La interfaz del ciudadano va después.
