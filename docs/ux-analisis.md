# Análisis de UX/UI y usabilidad

Al 2026-09-29, sobre `main` en `b937ff5` (it. 42a). Revisión experta de todas las pantallas de los cuatro perfiles. La meta es que GovTrace sea fácil de usar para cualquier persona, desde jóvenes hasta adultos mayores, tenga o no experiencia con apps. Es la base de la it. 40 (sección 7).

🔴 crítico: impide la tarea · 🟠 alto: la tarea sale, pero con esfuerzo o errores · 🟡 medio: confunde o molesta · ⚪ bajo: detalle · ❓ decisión tuya

## Cómo se hizo

- **Un recorrido con Playwright sobre `make demo`.** Pasó por las 27 vistas de los cuatro perfiles, en celular (412×915) y en escritorio (1366×768), y dejó 54 capturas de página completa.
- **Mediciones en el navegador**, en 26 de esas vistas y en los dos tamaños (52 en total):
  - axe-core 4.13 contra WCAG 2.2 AA y buenas prácticas;
  - el tamaño de letra de cada texto visible;
  - el tamaño de cada botón y enlace;
  - el contenido que tapa la barra fija;
  - el reflujo a 320 px de ancho.
- **Una evaluación heurística** con cuatro marcos:
  - las 10 heurísticas de Nielsen;
  - WCAG 2.2 AA;
  - las pautas para adultos mayores de W3C/WAI (*Older Users and Web Accessibility*) y de Nielsen Norman Group;
  - la *Guía de lenguaje claro para servidores públicos de Colombia* (DNP).
- **Cada hallazgo se comprobó en el código.** Entre paréntesis va el archivo donde se ve.

## 1. Resumen

**Veredicto.** La parte técnica de la accesibilidad está sana:
- axe encuentra solo 2 reglas incumplidas en las 52 vistas;
- el contraste cumple AA en todas;
- todo se reacomoda a 320 px sin barra horizontal;
- el zoom no está bloqueado;
- la barra de abajo nunca tapa contenido al final de la página, porque es `sticky`;
- todo declara `lang="es"`.

Lo que falla es la **usabilidad**: la navegación, el lenguaje y la jerarquía visual. Hoy GovTrace se entiende si uno ya sabe cómo funciona por dentro. Una persona mayor, o poco acostumbrada a las apps, se pierde o no sabe qué hacer.

**Las causas de fondo son cinco:**
1. **No hay un modelo de navegación.**
   - Dos paneles no tienen botón para salir.
   - Ninguna pantalla dice quién está conectado.
   - Las pestañas son solo texto, a 11 y 12 px.
   - Hay pantallas escondidas.
2. **El lenguaje es el del equipo, no el de la gente:** *ledger*, *blockchain*, *Stellar*, *huella*, *subdominio*, *estado editorial*, *CSV*.
3. **La letra y los botones son pequeños para este público.**
   - En la mitad de las pantallas, el 80 % del texto o más mide menos de 16 px.
   - En los paneles, menos de 44 px mide el 84 % de los botones del Administrador y el 91 % de los del Super Administrador.
4. **El color carga el significado solo, y la explicación queda lejos.** En el mapa, la leyenda está debajo, y en escritorio queda fuera de la pantalla.
5. **La spec no fijó reglas de UX.** 39 de los 56 criterios tienen `requisitos_ux: []`, así que nada obligaba a cuidarlo y ningún test lo mide.

### Los 12 cambios con más impacto

| # | Qué | Sev. | Dónde | Esfuerzo |
|---|---|---|---|---|
| 1 | **"Salir" y el nombre del usuario en todos los paneles.** Hoy el Administrador y el Super Administrador no pueden cerrar sesión, y el dominio central no tiene ruta de salida. | 🔴 | Paneles | Horas |
| 2 | **El nombre de la obra en cada tarjeta de la Bandeja.** Hoy el administrador publica o rechaza una foto sin saber de qué obra es. | 🔴 | Bandeja | Horas |
| 3 | **Los estados del mapa junto al mapa y con ícono, no solo con color.** Arriba del mapa, filtros que hacen de leyenda: "✓ Normal · ! Alerta · ✕ En riesgo". | 🟠 | Mapa | Horas |
| 4 | **Decir por qué no se puede enviar.** "Enviar Reporte" se queda gris sin explicación, y depende de cinco condiciones que no se ven. | 🟠 | Nuevo reporte | Horas |
| 5 | **El estado de la obra y su motivo en la ficha pública.** Por ejemplo: "En riesgo: el último reporte publicado es de abandono". | 🟠 | Obra | Horas |
| 6 | **Lenguaje claro:** unos 20 términos técnicos, con su reemplazo en el glosario (sección 5). | 🟠 | Todo | 1 día |
| 7 | **Una navegación nueva.** En celular, pestañas con ícono y texto de 14 px, máximo 5. En escritorio, una barra lateral. En cada pantalla, su título como `h1`. | 🟠 | Paneles | 1–2 días |
| 8 | **Letra base de 16 px y botones de 44 px en los paneles.** | 🟠 | Paneles | 1 día |
| 9 | **Una vista de lista junto al mapa**, con las obras ordenadas por riesgo, para quien no maneja mapas y para los lectores de pantalla. | 🟠 | Mapa | 1 día |
| 10 | **Un buscador en Contratos:** hoy son 742 contratos en 38 páginas, sin forma de buscar. | 🟠 | Contratos | Horas |
| 11 | **Una página central que le sirva al ciudadano:** qué es GovTrace y el directorio de veedurías. Hoy solo ofrece "Entrar al panel global". | 🟠 | Inicio central | Horas ❓ |
| 12 | **Las clases `sm:` muertas.** `app.css` apaga el punto de corte `sm`, así que 11 clases de escritorio nunca se aplican. Por eso el botón negro "Verificar Sello Blockchain" ocupa todo el ancho. | 🟡 | 6 componentes | Horas |

### Lo que ya está bien, y hay que conservar

- **Diseño y formularios:**
  - mobile-first de verdad: se reacomoda a 320 px y deja hacer zoom;
  - formularios con etiquetas visibles;
  - pestañas de 56 px en la app del veedor.
- **Acciones destructivas, con confirmación:**
  - el rechazo pide un motivo, que después ve el veedor;
  - el retiro explica que en el mapa queda una lápida;
  - la baja de una organización pide doble confirmación, escribiendo su nombre.
- **Protección del trabajo del veedor:**
  - "Salir" avisa si quedan reportes sin enviar;
  - "Obras cercanas" busca con el GPS;
  - la precisión del GPS está a la vista.
- **Controles de confianza:**
  - la Bandeja marca la "Hora de captura sospechosa";
  - los pines del mapa tienen texto para los lectores de pantalla;
  - el validador comprueba el archivo sin subirlo;
  - el título de la pestaña del navegador cambia en cada pantalla.

## 2. Para quién diseñamos

GovTrace tiene cuatro públicos muy distintos. La regla es diseñar para el que menos experiencia digital tiene: **si un adulto mayor lo entiende la primera vez, lo entiende cualquiera.**

| Perfil | Quién es, probablemente | Dónde y cómo lo usa | Qué necesita |
|---|---|---|---|
| Ciudadano | Cualquier edad. Llega por un enlace de WhatsApp o de redes sociales. | En el celular, una sola visita, con poca paciencia. | Entender en cinco segundos qué está viendo y si la obra va bien o mal. |
| Veedor de campo | Un líder comunitario (JAC), a menudo adulto mayor. | Un Android de gama media, en la calle, al sol, con una mano y con señal que va y viene. | Pocos pasos, botones grandes y la certeza de que su reporte llegó. |
| Administrador de la veeduría | Quien coordina la organización. | En el celular o en el computador, revisando por tandas. | Ver rápido qué falta revisar, con el contexto para decidir. |
| Super Administrador | Quien opera la plataforma. | En el computador. | Datos exactos. Aquí el lenguaje técnico sí vale. |

Los perfiles salen del caso. Hay que validarlos con personas reales (sección 8).

### Reglas de diseño propuestas: R-UX, para la SPEC

Cada una se puede medir con un test, como las demás reglas de la SPEC:

1. **R-UX-01 — Letra.**
   - El texto, a 16 px, y lo secundario, a 14 px como mínimo.
   - 12 px solo para notas y metadatos, nunca en un control.
2. **R-UX-02 — Botones y enlaces.** De 44 × 44 px como mínimo y con 8 px de separación. Los enlaces dentro de un párrafo quedan exentos.
3. **R-UX-03 — Navegación.**
   - Ícono y texto, nunca solo uno de los dos.
   - En celular, como mucho 5 destinos a la vista.
   - Con la sesión abierta, desde cualquier pantalla, el nombre del usuario y "Salir" quedan a uno o dos toques.
4. **R-UX-04 — Dónde estoy.** Cada pantalla tiene un `h1` con su título y una forma clara de volver.
5. **R-UX-05 — Color.** Nunca es el único portador de significado: siempre va con una palabra o con un ícono (WCAG 1.4.1).
6. **R-UX-06 — Lenguaje claro.**
   - Ninguna pantalla para ciudadanos, veedores o administradores muestra términos técnicos (*blockchain*, *ledger*, *hash*, *huella*, *Stellar*, *XLM*, *JSON*, *subdominio*). Solo pueden aparecer dentro de un "Ver detalles técnicos" plegado.
   - Es el espíritu de R-BLK-01: "si aparece un prompt criptográfico, es un fallo de UX".
7. **R-UX-07 — Botones deshabilitados.** Ninguno queda deshabilitado sin decir por qué, junto al botón.
8. **R-UX-08 — Qué pasó y qué sigue.** Después de cada acción, la pantalla lo dice. Por ejemplo: "Su reporte quedó guardado con sello digital. La veeduría lo revisará antes de publicarlo".
9. **R-UX-09 — Accesibilidad.** El recorrido de Playwright no tiene ninguna violación de WCAG 2.2 AA (axe), ni en celular ni en escritorio.

## 3. Hallazgos transversales

Son los que afectan a muchas pantallas a la vez; resolverlos arregla la mayor parte.

### T1 · No se puede cerrar sesión en los paneles 🔴

**Qué pasa:**
- **Los paneles no tienen "Salir".**
  - `AdminLayout.vue` y `SuperAdminLayout.vue` no tienen botón de salida.
  - `routes/web.php`, el dominio central, no tiene ruta `POST /logout`. La de cada organización sí existe, en `routes/tenant.php`.
  - En un computador compartido (la sede de la JAC, un café internet), la sesión del administrador queda abierta hasta que vence, tras 2 horas sin uso. Además de un problema de uso, es de seguridad.
- **En el veedor, "Salir" es una pestaña más** (`VeedorNav.vue`).
  - Está pegada a las de navegación: un toque por error cierra la sesión, y para volver hay que recordar la contraseña.
  - Solo pide confirmación si quedan reportes sin enviar.
- **Ninguna pantalla dice con qué cuenta se está conectado.**

**Recomendación:**
- Una cabecera común con un menú de cuenta: el nombre y el rol, "Ver el sitio público" y "Salir" con confirmación.
- La ruta de salida del dominio central, con su test: la sesión se invalida y el token se regenera.

### T2 · La navegación de los paneles 🟠

**Qué pasa:**
- **Seis pestañas solo de texto.**
  - Las del Administrador van a 11 px (`text-[11px]`) y miden 69×41 px.
  - Las del Super Administrador van a 12 px y, en el celular, se pegan ("Organizaciones Parámetros").
- **La pestaña activa** solo se distingue por un gris más oscuro.
- **En escritorio se usa la misma barra de abajo**, donde lo esperado es una lateral o una superior.
- **Tres pantallas del Administrador están escondidas:**
  - Resumen del territorio;
  - Autorización al Super Administrador;
  - Registro de auditoría.

  Solo se llega a ellas desde enlaces subrayados dentro de "Organización". El Resumen, que es el tablero, queda escondido.

**Recomendación:**
- **En celular:** cuatro pestañas con ícono y texto de 14 px (Bandeja, Obras, Veedores, Más), con un fondo o una barra en la pestaña activa y un contador en Bandeja.
- **En escritorio (`md:`):** una barra lateral con todas las pantallas, en cuatro grupos: Revisar · Territorio y obras · Equipo · Organización.

### T3 · El `h1` de todas las pantallas es el nombre de la organización 🟡

En `AppLayout.vue`, el nombre de la organización es el `h1`, y el título real de la pantalla ("Bandeja de entrada") es un `h2`. Un lector de pantalla anuncia todas las páginas igual.

**Recomendación:** el `h1` es el título de la pantalla, y el nombre de la organización va en la cabecera como marca.

### T4 · Lenguaje técnico 🟠

Está en todas partes. La sección 5 trae el glosario, con el reemplazo de cada término.

### T5 · Letra pequeña 🟠

**Qué pasa:**
- En 18 de las 26 vistas de celular, el 75 % del texto o más mide menos de 16 px (la mediana es 80 %). En los registros de auditoría, el 83–86 % mide 12 px.
- El código usa `text-sm` (14 px) 180 veces, `text-xs` (12 px) 76 y `text-base` (16 px) solo 45.
- Van a 12 px:
  - las etiquetas de los filtros del mapa;
  - los estados de los veedores;
  - notas como "Sellada en el ledger".

**Recomendación:**
- Una escala con base de 16 px: 14 px para lo secundario y 18–20 px para lo que se lee primero, como el nombre de la obra o el estado.
- Interlineado de 1,5.
- Tailwind mide en `rem`: si la persona agranda la letra en su navegador, todo crece con ella.

### T6 · Botones pequeños en los paneles 🟠

**Qué pasa:**
- Por debajo de 44 px queda el 84 % de los botones y enlaces en el panel del Administrador y el 91 % en el del Super Administrador. En el sitio público y la app del veedor, el 23 %.
- Las acciones de cada fila miden 26 px de alto, con letra de 12 px (`RowAction.vue` en Veedores y Organizaciones, y botones parecidos en Obras):
  - Desactivar;
  - Reenviar invitación y Revocar invitación;
  - Agregar a la agrupación;
  - Editar NIT, Suspender y Dar de baja.
- "Dar de baja", que no tiene vuelta atrás, queda justo debajo de "Suspender". La doble confirmación lo mitiga.
- En el mapa, los pines miden 28 px y los botones de zoom 30 px. axe marca los pines que se superponen (WCAG 2.5.8).

**Recomendación:** botones de 44 px o más, con 8 px de separación, y las acciones destructivas aparte, en un menú "⋯".

### T7 · El color solo 🟡

**Qué pasa:**
- **Los pines se distinguen únicamente por el color:** verde, amarillo o rojo.
  - Su texto (`title`) solo sirve con mouse o con lector de pantalla.
  - Alrededor de 1 de cada 12 hombres no distingue bien el rojo del verde.
- **El mismo estado se llama distinto:**
  - "Normal / Alerta / En riesgo" en el mapa;
  - "Verdes / Amarillas / Rojas" en el Resumen del Administrador (`Summary.vue`).

**Recomendación:** cada pin con su ícono (✓ Normal, ! Alerta, ✕ En riesgo), los mismos nombres en todas partes y el color siempre acompañado de la palabra.

### T8 · Botones deshabilitados sin explicación 🟠

**Qué pasa:**
- **"Enviar Reporte"** se habilita solo si se cumplen cinco condiciones (`canSend` en `NewReport.vue`):
  - el GPS está listo;
  - hay una clasificación elegida;
  - el comentario tiene como mucho 500 caracteres;
  - los archivos son válidos;
  - los archivos terminaron de prepararse.

  Nada junto al botón dice cuál falta.
- **"Agrupar"**, en Obras, tiene el mismo problema.

**Recomendación**, el patrón de GOV.UK. Una de dos:
- el botón siempre activo: al tocarlo, la pantalla sube al primer dato que falta y lo marca con un mensaje en palabras;
- o, junto al botón, la lista de lo que falta: "Para enviar falta: ☐ decir qué vio en la obra".

### T9 · Clases `sm:` que nunca se aplican 🟡

`app.css` apaga el punto de corte `sm` a propósito: en el Strict Mobile-First solo existen `md:` y `lg:`. Aun así, 11 clases lo usan en 6 archivos:

| Archivo | Lo que no se aplica |
|---|---|
| `VeedorNav.vue` | El diálogo de "Salir" centrado en escritorio. |
| `ContractCard.vue` | La ficha del contrato en 2 columnas. |
| `Public/EvidenceCard.vue` | El botón de verificar a su ancho natural. |
| `Admin/EvidenceCard.vue` | La grilla de fotos de la Bandeja. |
| `Map.vue` | Los filtros del mapa en 2 columnas. |
| `Organization.vue` | Los datos de la organización en 2 columnas. |

**Recomendación:** pasarlas a `md:`, y un test que falle si vuelve a aparecer `sm:`.

### T10 · Inconsistencias 🟡

**Qué pasa:**
- **"Volver" tiene tres estilos:**
  - "← Volver al mapa", sin subrayar;
  - "Volver al mapa", subrayado, en Estadísticas;
  - "← Organizaciones".
- **La cabecera del inicio de sesión** de una organización dice "GovTrace", en lugar del nombre y el logo de la organización (`Login.vue`).
- **Hay textos en inglés:** "Success" en Salud de SECOP y "cancelled" en Contratos.
- **Botones de la misma jerarquía** tienen tamaños distintos.

**Recomendación:** componentes comunes:
- `PageHeader`: el título y cómo volver;
- `Button`, en tres variantes;
- `StatusBadge`;
- `EmptyState`;
- `HelpTip`.

### T11 · Datos de SECOP difíciles de leer, que por regla no se pueden tocar 🟡

**Qué pasa:**
- **Los nombres de SECOP llegan en MAYÚSCULAS y muy largos.** En Nuevo reporte, la obra elegida ocupa 7 renglones.
- **En Contratos se ve:**
  - "Sin Descripcion", sin tilde;
  - estados como "enviado Proveedor";
  - "Fecha de firma —" en todos.
- **R-SEC-01 obliga a mostrar los datos de SECOP tal cual.**

**Recomendación:**
- Que la **ficha de obra**, que es de GovTrace y no de SECOP (R-GEO-01), tenga un **nombre corto** que pone el administrador, por ejemplo "Pavimentación Calle 18, Taganga".
  - Se muestra como título.
  - Debajo va el nombre oficial de SECOP, intacto.
- El estado `cancelled` no viene de SECOP: es el estado interno de GovTrace (`ProcessSecopContractRow.php`). Se puede mostrar como "Anulado en SECOP".

### T12 · Coordenadas crudas 🟡

**Qué pasa:** a nadie le dicen nada:
- "Ubicación oficial: 11.2408000, -74.1990000", en Obras;
- "Ubicación aproximada (unos 100 m): 11.241, -74.199", en la obra pública.

**Recomendación:** un mapa pequeño o un enlace "Ver en el mapa", con las coordenadas dentro de los detalles.

### T13 · No hay orientación ni ayuda 🟠

**Qué pasa:** nada explica:
- qué es GovTrace;
- qué significa cada color;
- qué es el sello;
- qué pasa con un reporte después de enviarlo.

Tampoco hay un contacto de ayuda.

**Recomendación:**
- Un "¿Cómo funciona?" de 3 pasos con dibujos, en el mapa y en el inicio:
  1. Los veedores toman fotos en la obra.
  2. Cada foto recibe un sello digital que nadie puede borrar ni cambiar.
  3. La veeduría las revisa y las publica aquí.
- Una guía de primer uso para el veedor: 3 pantallas que se pueden saltar.
- Ayudas "¿Qué es esto?" junto a los conceptos clave.

## 4. Hallazgos por pantalla

### 4.1 Sitio público (ciudadano)

| Pantalla | Hallazgo | Sev. | Recomendación |
|---|---|---|---|
| Inicio central | Solo una tarjeta, con "sellada en la red Stellar", "subdominio" y un único botón: "Entrar al panel global", que es del Super Administrador. Casi toda la pantalla, vacía. Un ciudadano no tiene cómo llegar a ninguna veeduría. | 🟠 | Qué es GovTrace en una frase, "¿Cómo funciona?" y el directorio de veedurías (nombre, municipio y enlace a su mapa) ❓. El acceso de administradores, al pie. |
| Mapa | **La leyenda está debajo del mapa**, lejos de los pines. En escritorio (1366×768) queda justo por debajo del borde: hay que desplazarse para verla. | 🟠 | Arriba del mapa, filtros que hacen de leyenda: "✓ Normal (3) · ! Alerta (1) · ✕ En riesgo (1)". Al tocar uno, filtra. Otra opción: la leyenda dentro del mapa, abajo a la izquierda. |
| Mapa | No tiene título ni explicación: no dice qué se está viendo. | 🟠 | "Obras vigiladas por …" y una línea: "Toque un punto para ver la obra y sus fotos". |
| Mapa | En Santa Marta, 3 pines se superponen: se ve uno amarillo y apenas el borde de uno verde. Con datos reales serán cientos. | 🟡 | Agrupar los pines cercanos (Leaflet.markercluster), y una **lista** ordenada por riesgo. |
| Mapa | "▸ Filtrar obras" está plegado, en una caja grande y vacía, con etiquetas de 12 px. | 🟡 | Los filtros-leyenda, a la vista. Los avanzados (municipio, fechas, presupuesto), en "Más filtros". |
| Mapa | No hay entrada para veedores ni administradores. | 🟡 | "Soy veedor: entrar", en la cabecera o al pie. |
| Obra | **No muestra el estado** (el color del mapa), ni por qué lo tiene. | 🟠 | Un recuadro con ícono, color y palabra, y el motivo según las reglas de US-027. Por ejemplo: "En riesgo: el último reporte publicado es de abandono (12/09/2026)". |
| Obra | Por cada foto, un botón negro de todo el ancho: "Verificar Sello Blockchain". Es lo que más llama la atención, y es jerga. | 🟡 | "✓ Foto original, con sello digital", como insignia, y "Comprobar" como enlace secundario. Lo técnico va en el validador. |
| Obra | El título aparece dos veces. "Valor —". | ⚪ | Una sola vez. "Valor: no informado en SECOP". |
| Estadísticas | "Volver al mapa" con otro estilo. Tarjetas que confunden ("0 Contratos anulados con evidencias"). "CSV" y "JSON". | 🟡 | Una frase que explique cada cifra. "Descargar para Excel (CSV)" y "Datos para programadores (JSON)". |
| Validador | Dice "huella" y "red Stellar". Las pestañas son "Un archivo" y "Archivo y su prueba". En todos los equipos dice "Arrastre aquí… o toque para elegirlo": en el celular no se arrastra, y en el computador no se toca. | 🟡 | "Compruebe si una foto es la original", con un botón grande "Elegir foto o PDF". Arrastrar, solo en escritorio. La segunda pestaña, como "¿Tiene también el comprobante descargado?". El resultado, en palabras y con un ícono grande. |
| Iniciar sesión | En una organización dice "GovTrace". No hay "mostrar contraseña" ni forma de volver al mapa. "¿Olvidó su contraseña?" mide 20 px de alto. | 🟡 | El nombre y el logo de la organización. Un ojo para mostrar la contraseña. "← Volver al mapa". El enlace, de 44 px. |

### 4.2 App del veedor

| Pantalla | Hallazgo | Sev. | Recomendación |
|---|---|---|---|
| Barra de abajo | Solo texto. La pestaña activa solo es un gris más oscuro. "Salir" va como una pestaña más. | 🟡 | Ícono y texto, con un indicador claro de la activa. "Salir", dentro de "Mi cuenta" (T1). |
| Nuevo reporte | No se ven los pasos: la pantalla es un formulario largo. El código sí los tiene: obra, clasificación, comentario y fotos. | 🟠 | Pasos a la vista, con el avance arriba: 1 Obra · 2 ¿Qué vio? · 3 Fotos · Enviar. |
| Nuevo reporte | El nombre de SECOP, en mayúsculas, ocupa 7 renglones. | 🟡 | El nombre corto de la ficha (T11). |
| Nuevo reporte | "Clasificación: Avance / Retraso / Abandono", sin explicar qué es cada una. | 🟠 | "¿Qué vio en la obra?", con un ícono y una línea en cada opción. Por ejemplo: "Abandono: no hay nadie trabajando y la obra parece dejada". Esas líneas cambian el color del mapa (US-027): las valida la veeduría ❓. |
| Nuevo reporte | "Adjuntar fotos o PDF" abre el selector del sistema. | 🟡 | Dos botones grandes: "📷 Tomar foto", que abre la cámara (`capture="environment"`), y "Elegir de la galería". |
| Nuevo reporte | "Enviar Reporte" queda gris sin decir por qué. | 🟠 | T8. |
| Nuevo reporte | "Escriba al menos 3 caracteres", en letra de 12 px. | ⚪ | "📍 Obras cercanas", que ya existe, como el camino principal. |
| Mis reportes | "Estado técnico: Sellado · Estado editorial: En Revisión". | 🟠 | Siguen por separado, como pide US-010, pero en palabras de todos los días: "Sello digital: ✓ guardado" · "En el mapa: ⏳ la veeduría lo está revisando". |
| Mis reportes | "Ver recibo". | ⚪ | ❓ "Ver comprobante". |

### 4.3 Panel del Administrador

| Pantalla | Hallazgo | Sev. | Recomendación |
|---|---|---|---|
| Todas | No hay "Salir" ni el nombre del usuario. Navegación de 11 px. | 🔴 | T1 y T2. |
| Bandeja | **La tarjeta no dice de qué obra es la evidencia.** Solo muestra la clasificación, la fecha, las fotos, el comentario y "Sellada en el ledger 121382" (`Admin/EvidenceCard.vue`). | 🔴 | El nombre de la obra y su municipio arriba, y si la foto se tomó dentro de la geocerca. |
| Bandeja | En escritorio, cada foto mide casi 500 px: cabe una evidencia por pantalla. | 🟡 | Miniaturas en grilla (el arreglo de `sm:`), y la foto grande al tocarla. |
| Bandeja | Las pestañas no dicen cuántas evidencias hay. | ⚪ | "Por revisar (3)". |
| Veedores | Acciones de 26 px. | 🟡 | T6. |
| Territorio | "Vigilamos" y "Guardar territorio" no explican para qué sirve el territorio. | 🟡 | "Municipios y departamentos que vigila la veeduría. De ellos se traen los contratos de SECOP". |
| Contratos | **No tiene buscador:** 742 contratos en 38 páginas. Los títulos se cortan y quedan idénticos ("AUNAR ESFUERZOS ENTRE EL DEPARTAMENTO DEL MAGDALEN…"). "cancelled", en inglés. | 🟠 | Un buscador por número de proceso, contratista o palabras. El título, en 2 renglones. "Anulado en SECOP". |
| Obras | Coordenadas crudas (T12). "Agregar a la agrupación", de 26 px. El formulario "Agrupar contratos en una ficha" pide pegar números de proceso de SECOP, uno por línea. "Agrupar" queda gris sin explicación. | 🟠 | "Unir contratos de una misma obra", eligiéndolos de una lista con buscador, sin pegar números. Corregir la ubicación sobre un mapa: `LocationMap.vue` ya existe. |
| Organización | Esconde tres pantallas (T2). El selector de archivo es el nativo del navegador. | 🟡 | T2. Un botón "Subir logo" con vista previa. |
| Resumen | "Verdes / Amarillas / Rojas". | 🟡 | Los mismos nombres que en el mapa (T7). |
| Auditoría | Datos crudos: `user_id: 4`, `editorial_status: hidden → published`, `territory: [{"department_code":"47",…}]`. El 83 % del texto va a 12 px. | 🟠 | Frases: "Marta Ospina publicó la evidencia #6 (Pavimentación Calle 30)". El detalle técnico, plegado. |
| Autorización | "Autorizar por 30 días" actúa con el primer toque. | 🟡 | Una confirmación que diga qué podrá hacer el Super Administrador, y hasta cuándo. |

### 4.4 Panel global (Super Administrador)

Es un perfil técnico: aquí sí caben el XLM, las llaves y el ledger. Lo que falla es lo básico.

| Pantalla | Hallazgo | Sev. | Recomendación |
|---|---|---|---|
| Todas | No hay "Salir" ni ruta de salida central. Seis pestañas de 12 px, pegadas en el celular. | 🔴 | T1 y T2. |
| Organizaciones | "Editar NIT", "Suspender" y "Dar de baja", de 26 px y apiladas. | 🟡 | Un menú "⋯", con "Dar de baja" separado del resto. |
| Sellado | El saldo, con 7 decimales. Las llaves públicas, sin botón para copiarlas. | ⚪ | 2 decimales, con el valor exacto al pasar el mouse, y un botón "Copiar". |
| Salud de SECOP | "Success", en inglés. | ⚪ | "Correcta". |

## 5. Glosario: del lenguaje técnico al claro

| Hoy dice | Dónde | Propuesta |
|---|---|---|
| Verificar Sello Blockchain | Obra | Comprobar que la foto es original |
| Sellada en el ledger 121382 | Bandeja | Con sello digital ✓ (el número, en los detalles) |
| Estado técnico / Estado editorial | Mis reportes | Sello digital / En el mapa |
| Sellado · En Revisión · Publicado | Mis reportes | Guardado para siempre · La veeduría lo está revisando · Publicado en el mapa |
| la huella del archivo, la red Stellar | Validador | "Comparamos su archivo con el original guardado. Su archivo no sale de su equipo". |
| Un archivo / Archivo y su prueba | Validador | Solo la foto / La foto y su comprobante |
| sellada en la red Stellar · subdominio | Inicio central | con un sello digital que nadie puede borrar ni cambiar · "cada veeduría tiene su propia página" |
| Entrar al panel global | Inicio central | Al pie: "Acceso para administradores" |
| CSV / JSON | Estadísticas | Descargar para Excel / Datos para programadores |
| Clasificación: Avance · Retraso · Abandono | Nuevo reporte | ¿Qué vio en la obra? Cada opción con su explicación. |
| Verdes · Amarillas · Rojas | Resumen | Normal · Alerta · En riesgo |
| Agregar a la agrupación · Agrupar contratos en una ficha | Obras | Unir con otra obra · Unir contratos de una misma obra |
| Ubicación oficial: 11.2408000, -74.1990000 | Obras | Un mapa pequeño y "Ver en el mapa" |
| Vigilamos | Territorio | Lugares que vigila la veeduría |
| cancelled | Contratos | Anulado en SECOP |
| Success | Salud de SECOP | Correcta |
| user_id, editorial_status, hidden → published | Auditoría | Una frase: "publicó la evidencia…" |

## 6. Cómo se vería (bocetos)

**El mapa público, en el celular.** Los filtros hacen de leyenda y están arriba del mapa. Cada pin lleva su ícono. La lista es la alternativa al mapa.

```
[logo] Veeduría Ciudadana SM                        Entrar
──────────────────────────────────────────────────────────
Obras vigiladas en Magdalena                          (h1)
Toque un punto para ver la obra y sus fotos.

[✓ Normal 3]  [! Alerta 1]  [✕ En riesgo 1]    ← leyenda y filtro a la vez
[ Mapa ] [ Lista ]                         Más filtros ▾
┌─ mapa ───────────────────────────────────────────────────
│       (✓)              (!)
│                               (✕)
│               (✓)
└──────────────────────────────────────────────────────────
¿Cómo funciona?  ·  Comprobar una foto
```

**Los paneles.** La cabecera tiene el menú de cuenta. En el celular, pestañas con ícono. En escritorio, una barra lateral.

```
Celular                             Escritorio (md:)
┌──────────────────────────────┐    ┌──────────────┬──────────────────────────────┐
│ [logo] Veeduría SM   Marta ▾ │    │ [logo]       │ Bandeja de entrada  Marta ▾  │
├──────────────────────────────┤    │ Veeduría SM  │      ├ Ver el sitio público  │
│ Bandeja de entrada (h1)      │    │              │      └ Salir                 │
│ ┌──────────────────────────┐ │    │ ▸ Bandeja (3)│ ┌──────┐ ┌──────┐ ┌──────┐   │
│ │ Pavimentación Calle 30   │ │    │   Obras      │ │ foto │ │ foto │ │ foto │   │
│ │ Avance · 29/09 · en obra │ │    │   Contratos  │ │Calle │ │Cole- │ │Acue- │   │
│ │ [foto]                   │ │    │   Veedores   │ │  30  │ │ gio  │ │ducto │   │
│ │ [Publicar]  [Rechazar]   │ │    │   Territorio │ └──────┘ └──────┘ └──────┘   │
│ └──────────────────────────┘ │    │   Resumen    │                              │
├──────────────────────────────┤    │   Organiz.   │                              │
│  (ic)   (ic)    (ic)   (ic)  │    └──────────────┴──────────────────────────────┘
│ Bandeja  Obras  Equipo  Más  │
└──────────────────────────────┘
```

`(ic)` marca el lugar de cada ícono. Van íconos SVG de un set de código abierto, por ejemplo Heroicons (MIT), empaquetados con la app: la CSP no cambia.

## 7. Plan de mejora: la it. 40, "Usable por cualquiera"

La it. 40 estaba reservada para "el recorrido visual con Playwright y axe". Ese recorrido se hizo para este análisis, y ahora se convierte en el punto de partida: la **40a** lo deja en el repositorio como el *checkpoint base*, junto con los flujos de `docs/mapa-funcional.md`. Las otras tres partes corrigen lo encontrado. Cada parte va con su commit y su criterio de terminado.

**40a — Checkpoint base (1 día):** el recorrido, axe y los flujos de cada rol, como tests. Detalle en `docs/estado-mvp.md` y `specs/PLAN.md`.

**40b — Lo urgente (1–2 días)**
- **Sesión:**
  - "Salir" y el nombre del usuario en los dos paneles;
  - la ruta de salida del dominio central, con sus tests;
  - el "Salir" del veedor, dentro de "Mi cuenta" y siempre con confirmación.
- **Contexto que falta:**
  - el nombre de la obra en cada tarjeta de la Bandeja;
  - el estado de la obra y su motivo en la ficha pública. El backend tiene que devolver el motivo.
- **Mapa:** los filtros-leyenda arriba, íconos en los pines y los mismos nombres de estado en todas partes.
- **Nuevo reporte:** "Enviar Reporte" dice qué falta.
- **Arreglos de código:**
  - `sm:` pasa a `md:`, con un test que lo vigila;
  - el `h1` es el título de cada pantalla;
  - "Anulado en SECOP" y "Correcta" en lugar de "cancelled" y "Success".

**Done-when:**
- Playwright:
  - "Salir" queda a dos toques o menos desde cada pantalla con sesión;
  - los estados del mapa se ven sin desplazarse, en 412×915 y en 1366×768.
- Pest: salir del dominio central y de una organización invalida la sesión.
- axe: cero violaciones en las 52 vistas.
- Ningún `sm:` en `resources/js`.

**40c — Navegación y legibilidad (2–3 días)**
- **La navegación nueva:**
  - una cabecera común con el menú de cuenta;
  - en celular, la barra con íconos (máximo 5) y "Más";
  - en escritorio, la barra lateral.
- **Letra y botones:**
  - la escala tipográfica, con base de 16 px;
  - botones de 44 px en los paneles;
  - las acciones de cada fila, en un menú "⋯".
- **Componentes comunes** (`PageHeader`, `Button`, `StatusBadge`, `EmptyState`, `HelpTip`) y el set de íconos.
- **El glosario de la sección 5**, aplicado.
- **Contratos:** el buscador.
- **Mapa:** la vista de lista y la agrupación de pines.

**Done-when (Playwright):**
- ningún control por debajo de 44 px, salvo los enlaces dentro de un texto;
- como mucho el 5 % del texto por debajo de 14 px en cada pantalla;
- cada pantalla con un `h1` igual a su título;
- la navegación con ícono y texto.

**40d — Orientación y confianza (2–3 días)**
- **Inicio y ayuda:**
  - el inicio central, con "¿Cómo funciona?" y el directorio de veedurías ❓;
  - la guía de primer uso del veedor.
- **Nuevo reporte:** los pasos a la vista y los botones "Tomar foto" y "Galería".
- **Administrador:**
  - la auditoría, en frases;
  - el nombre corto de la ficha de obra ❓ (una migración y su pantalla);
  - "Unir contratos", eligiéndolos de una lista.
- **La prueba con personas** (sección 8), y los ajustes que salgan de ella.

**Done-when:** en la prueba con 5 personas, al menos 4 terminan cada tarea sin ayuda.

**Modelo, según la política de cada iteración:** Sonnet medium, porque es trabajo de interfaz. Solo una pieza toca el acceso: la ruta de salida del dominio central. Es pequeña y estándar, y lleva sus tests.

## 8. Cómo sabremos que mejoró

**Automático, en el pipeline (`make ux-check`):**
- axe sin ninguna violación de WCAG 2.2 AA en las 52 vistas;
- las comprobaciones propias de este análisis:
  - botones de 44 px;
  - texto de menos de 14 px;
  - un `h1` por pantalla;
  - "Salir" alcanzable;
  - ningún `sm:`;
- las capturas del recorrido como artefacto de Jenkins, para revisarlas en cada PR.

**Con personas: lo que ningún test mide.**
- **Quiénes:** 5 personas. Al menos 2 mayores de 60 y 1 que casi no use apps. Con 5 personas aparece la mayoría de los problemas de uso (Nielsen).
- **Cómo:** en persona o por videollamada, con un celular de gama media y la demo. Unos 20 minutos por persona, con guion.
- **Las 6 tareas:**
  1. Encontrar una obra en riesgo y decir por qué lo está.
  2. Comprobar si una foto es la original.
  3. Como veedor, enviar un reporte con foto.
  4. Como veedor, saber si su reporte ya se publicó.
  5. Como administrador, publicar la evidencia de una obra dada.
  6. Cerrar la sesión.
- **Qué se mide:** si la persona termina la tarea sin ayuda, cuánto tarda y dónde duda. La meta: 4 de 5 personas terminan cada tarea.

## 9. Decisiones tuyas (❓)

1. **Las reglas R-UX en la SPEC** (sección 2): ¿las apruebas como meta, con WCAG 2.2 AA? Así `/audit` las verifica como las demás.
2. **El directorio de veedurías** en el inicio central: ¿se listan todas las organizaciones activas? R-MAP-01 aísla cada mapa, pero no impide un directorio.
3. **El nombre corto de la obra** en la ficha de GovTrace, puesto por el administrador. Respeta R-SEC-01: el nombre de SECOP se sigue mostrando intacto.
4. **La explicación de Avance, Retraso y Abandono:** debe validarla alguien de una veeduría, porque cambia el color del mapa.
5. **Las palabras:**
   - para el público, "sello digital" en lugar de "blockchain";
   - "comprobante" o "recibo".
6. **La prueba con personas:** ¿puedes conseguir 5, al menos 2 de ellas mayores de 60?

## Anexo A: mediciones por pantalla (celular, 412×915)

Esta es la línea base: la it. 40 se compara contra ella.

| Vista | Texto < 16 px | Texto < 14 px | Botones y enlaces < 44 px |
|---|---|---|---|
| Inicio central | 53 % | 0 % | 0 de 1 |
| Mapa | 81 % | 36 % | 9 de 19 |
| Obra | 60 % | 28 % | 0 de 5 |
| Estadísticas | 82 % | 13 % | 1 de 3 |
| Validador | 83 % | 8 % | 0 de 3 |
| Iniciar sesión (organización) | 76 % | 0 % | 1 de 4 |
| Iniciar sesión (central) | 69 % | 0 % | 1 de 4 |
| Veedor: nuevo reporte | 51 % | 22 % | 0 de 5 |
| Veedor: reporte con foto | 46 % | 1 % | 2 de 10 |
| Veedor: mis reportes | 65 % | 20 % | 0 de 8 |
| Admin: bandeja | 78 % | 28 % | 8 de 14 |
| Admin: veedores | 79 % | 47 % | 10 de 12 |
| Admin: territorio | 65 % | 27 % | 7 de 9 |
| Admin: contratos | 99 % | 34 % | 10 de 10 |
| Admin: obras | 96 % | 23 % | 20 de 22 |
| Admin: organización | 85 % | 42 % | 10 de 12 |
| Admin: resumen | 72 % | 40 % | 6 de 7 |
| Admin: auditoría | 97 % | 83 % | 8 de 8 |
| Admin: autorización | 78 % | 16 % | 6 de 7 |
| Super Admin: organizaciones | 92 % | 38 % | 13 de 13 |
| Super Admin: nueva organización | 78 % | 21 % | 7 de 13 |
| Super Admin: sellado | 83 % | 42 % | 6 de 6 |
| Super Admin: parámetros | 96 % | 25 % | 16 de 16 |
| Super Admin: uso | 93 % | 75 % | 6 de 6 |
| Super Admin: auditoría | 99 % | 86 % | 8 de 8 |
| Super Admin: salud de SECOP | 89 % | 18 % | 6 de 6 |

**Resultado de axe:** en las 52 vistas fallan solo 2 reglas.
- **`target-size` (WCAG 2.5.8), en 5 vistas:**
  - los pines superpuestos del mapa;
  - el "Quitar" de un archivo adjunto;
  - un "Agregar a la agrupación" de una ficha con dos contratos.
- **`definition-list`, en Organización:** hay un `<p>` dentro de un `<dl>`.

Ninguna vista falla por contraste. Ninguna desborda a 320 px. El zoom está permitido en todas.

## Anexo B: cómo repetirlo

Por ahora, el recorrido y las mediciones viven fuera de git, en `storage/framework/testing/ux/`:
- `tour.spec.js` toma las capturas;
- `audit.spec.js` hace las mediciones y usa axe-core, instalado aparte.

Corren con la imagen oficial de Playwright contra `make demo`. En la 40a pasan a ser parte del repositorio, con `make ux-check`, y corren en el pipeline.
