# Lo que puede frenar a GovTrace: la ley y el presupuesto

**Revisión documental del 2026-10-06.**

Para qué sirve: saber qué puede impedir que GovTrace salga a producción o que se use, más allá del código, por dónde empezar y qué existe ya en otros países. Complementa dos documentos:
- [`proceso-actual.md`](proceso-actual.md), que alinea la app con la ley del control social;
- [`preparacion-red-principal.md`](preparacion-red-principal.md), que dice qué tiene que hacer el operador.

Lo marcado ❓ está **por validar**, casi siempre con un abogado. Esto no es asesoría legal.

## Resumen

Dentro de la app, la ley del control social y la de datos personales están cubiertas (sección 1). Lo que puede frenar a GovTrace está fuera del código:

1. **No hay operador.** Nadie es todavía el responsable del tratamiento de los datos, el dueño de las cuentas, ni quien responde ante una demanda.
2. **No hay reglas con los terceros.** Faltan tres cosas:
   - los términos de uso;
   - un acuerdo entre el operador y cada veeduría;
   - un canal para que un contratista pida revisar lo que se dice de su obra.
3. **Nadie ha confirmado que la Contraloría acepte la evidencia**, que es la razón de ser del sello.
4. **No hay quién pague.** La infraestructura cuesta poco; las personas que la operan, no. Y no hay ingresos.

| Gravedad | Qué | Sección |
|---|---|---|
| Bloquea la salida | L1 el operador · L2 las reglas con veedurías y terceros · L3 la revisión legal | 2 |
| Puede tumbarlo después | R1 el buen nombre · R2 el valor de la evidencia · R3 la seguridad del veedor · R4 la operación de los datos · R5 los XLM | 3 |
| Frena el uso | F1 a F5: instituciones, estigma, veedurías, fuentes, accesibilidad | 4 |
| Lo sostiene o no | Quién paga | 5 |
| Contexto | Lo que ya existe: GovTrace no es pionera en cada mitad, pero no se encontró a nadie que haga las dos | 8 |

## 1. Lo que ya está cubierto

| Norma | Qué hace GovTrace | Regla |
|---|---|---|
| Ley 850 de 2003, art. 18 a) | El ciudadano informa a la veeduría, sin que ella vea su correo | R-LEG-09, R-LEG-10 |
| Ley 850, art. 3 | La veeduría se identifica con su inscripción, aunque no tenga NIT | R-LEG-06 |
| Ley 850, art. 19 | El veedor declara que no tiene impedimentos; sin la declaración, no reporta | R-LEG-05 |
| Ley 850, arts. 15 y 16 | El expediente de la obra y las plantillas, que la veeduría completa, firma y presenta | R-LEG-03, R-LEG-04 |
| Ley 2020 de 2020 | Un estado de GovTrace es una alerta, no una "obra inconclusa"; la ficha remite a los canales de la Contraloría | R-LEG-01, R-LEG-02 |
| Ley 1581 de 2012 | La política de datos (en borrador hasta tener al responsable) y la autorización, con su versión y su fecha | R-LEG-07, R-LEG-08 |
| Ley 1581, datos sensibles | Ver detalle debajo de la tabla | R-PRIV-01 a R-PRIV-05 |
| — | Retirar una evidencia publicada deja una lápida, así que una orden de retiro se puede cumplir | US-037 |
| — | La verificación en dos pasos del Super Administrador, construida e inactiva | R-SEC-09 |

Para los datos sensibles:
- los rostros se difuminan en el teléfono;
- los archivos no llevan metadatos;
- el veedor aparece con un seudónimo;
- la ubicación pública es aproximada;
- en la red solo queda la huella.

## 2. Bloqueantes: sin esto no se puede salir

### L1. No hay operador

El paso 0 de `preparacion-red-principal.md` sigue pendiente. Todo cuelga del operador:
- el responsable del tratamiento de los datos;
- la cuenta de AWS, la del exchange y el contrato con el proveedor de RPC;
- quién responde si alguien demanda.

Si es una persona natural, responde con su nombre y su patrimonio.

**Recomendación:** para operar con veedurías reales, una **ESAL o fundación**. Puede recibir donaciones y subvenciones, se traspasa entera, y es la contraparte natural de una alianza con una universidad o una entidad pública (sección 5). ❓ Si conviene su régimen tributario especial ante la DIAN.

### L2. No hay reglas entre el operador, las veedurías y los terceros

Hoy nada dice quién responde por lo que se publica. La veeduría publica cada evidencia (US-036), pero el servidor es del operador. Si un tercero demanda, se discute desde cero.

Hace falta:
- **Términos de uso** para la veeduría, el veedor y el ciudadano:
  - quién responde por lo publicado (la veeduría, que lo revisa y lo publica);
  - el uso prohibido (por ejemplo, el partidista);
  - el canal de revisión de R1.
- **Decidir D-V2-10.** El operador puede ser el responsable único de los datos, o cada veeduría puede ser responsable y el operador su encargado. En el segundo caso hace falta un acuerdo de tratamiento con cada una. En la app cambia el texto, no el mecanismo (US-058-LEG).
- **En la app** es pequeño: la aceptación de los términos usa el mismo mecanismo versionado de R-LEG-07.

### L3. La revisión legal

La política de `/privacidad` no se puede publicar como borrador en producción, y varias decisiones por defecto esperan a un abogado (`go-live.md`, sección 3, y `estado-mvp.md`).

**Recomendación:** una sola revisión con todo lo de este documento. La sección 7 tiene las preguntas. Es más barato que pedirla por partes.

## 3. Riesgos altos: se puede salir, pero pueden tumbarlo

### R1. El buen nombre del contratista

Es el riesgo más probable.

- **La exposición.** La ficha pública de una obra muestra al contratista, tomado de SECOP II (`ContractCard.vue`). Al lado van el estado de la obra y las fotos.
- **El estado lo calcula GovTrace, no la veeduría.** "En riesgo" sale de sus reglas: el plazo vencido, o una última evidencia que dice Abandono.
  - La Corte Constitucional dijo que el intermediario no responde por lo que publican sus usuarios (SU-420 de 2019).
  - Pero un estado que calcula la plataforma es un contenido propio ❓.
- **Lo que puede hacer el contratista:**
  - pedir por tutela la rectificación o el retiro (Constitución, arts. 15 y 20);
  - denunciar por injuria o calumnia (Código Penal, arts. 220 a 222).
- **R-LEG-01 ya lo matiza:** el estado es una alerta, no una decisión de una autoridad.

Falta:
- **Un canal para que un tercero pida una revisión o una rectificación**, con su plazo. Hoy solo la veeduría puede retirar una evidencia.
- **Un aviso en la ficha:** "Evidencia aportada por la veeduría X. GovTrace garantiza que no se alteró desde que se registró, no lo que muestra."
- **En los términos de uso,** la responsabilidad de lo publicado es de la veeduría (L2).

A favor: una orden de retiro se puede cumplir. La lápida existe, y en la red solo queda la huella, que no muestra nada.

### R2. El valor de la evidencia no está validado

**El marco ayuda.** La Ley 527 de 1999 da valor probatorio a los mensajes de datos (art. 10). Para valorarlos mira tres cosas (art. 11):
- cómo se generaron y conservaron;
- si se conservó su integridad;
- cómo se identifica a quien los generó.

Frente a esos tres puntos:
- **La integridad la cubre el sello.**
- **La identificación es débil:**
  - el veedor es una cuenta con correo, sin firma electrónica;
  - la hora y el GPS salen de su teléfono, y se pueden falsificar;
  - la geocerca y la marca de hora sospechosa lo atenúan, pero no lo resuelven.
- **El nombre "Prueba Pericial Criptográfica"** es un riesgo:
  - un dictamen pericial lo rinde un perito con sus calidades (Código General del Proceso, arts. 226 y siguientes), y un PDF que GovTrace genera solo puede objetarse por llamarse así;
  - choca, además, con evitar el lenguaje "cripto" (F2);
  - la propuesta es "Constancia de integridad" ❓.
- **La validación con la Contraloría** (`proceso-actual.md`, sección 7) no se ha hecho. Conviene hacerla **antes** de comprar XLM: si no acepta la evidencia, o la pide en otro formato, cambia lo que hay que sellar.

### R3. La seguridad del veedor

En Colombia, quien vigila lo público puede ser amenazado.

**Lo que ya protege:**
- el seudónimo;
- la ubicación aproximada;
- la publicación manual, una por una.

**Lo que no:**
- La línea de tiempo pública muestra **la fecha y la hora** de cada evidencia (`EvidenceCard.vue`). En un municipio pequeño, con la ubicación aproximada, eso puede delatar a quien fue.
- **Colombia no tiene todavía una ley de protección al denunciante:**
  - el capítulo se quitó de la Ley 2195 de 2022;
  - el proyecto "Jorge Enrique Pizano" se archivó en junio de 2025;
  - el Proyecto de Ley 476 de 2025 de la Cámara se radicó el 20 de noviembre de 2025 y sigue en trámite.

Falta:
- **Un protocolo para un veedor amenazado:**
  - retirar de una vez todo lo suyo;
  - desactivarlo;
  - a quién avisar (la Defensoría del Pueblo ❓).
- **Decidir si se publica solo la fecha**, sin la hora.

### R4. Los datos personales, en la operación

La política está escrita; falta operarla.

- **La transferencia a Estados Unidos (AWS):**
  - se permite, porque la SIC incluye a Estados Unidos entre los países con nivel adecuado (Circular Externa 005 de 2017; Ley 1581, art. 26);
  - con AWS como encargado hace falta un contrato de transmisión (Decreto 1074 de 2015, art. 2.2.2.25.5.2), y ❓ si bastan sus términos de procesamiento de datos;
  - `PRIVACY_HOSTING` ya lo dice en la política.
- **La responsabilidad demostrada:**
  - un manual interno;
  - quién atiende el correo de los titulares, en 10 días hábiles para una consulta y 15 para un reclamo (Ley 1581, arts. 14 y 15);
  - cómo se avisa a la SIC de un incidente de seguridad (arts. 17 n) y 18 k)).

  Nada de eso está escrito. `docs/restore.md` cubre la restauración, no el aviso.
- **El Registro Nacional de Bases de Datos:** depende de quién sea el operador (L1; Decreto 090 de 2018).
- **Las placas de los vehículos** solo se difuminan a mano.

### R5. Los XLM

**Para un operador privado:**
- Los criptoactivos no son moneda ni divisa (Banco de la República). La Superfinanciera no deja que sus vigiladas los custodien ni operen. No hay un marco legal específico, pero tampoco una prohibición para una persona o una ESAL.
- La compra queda en la contabilidad del operador ❓ (su tratamiento ante la DIAN).

**Para una entidad pública es casi un bloqueo.** Comprar criptoactivos con presupuesto público no tiene una vía clara ❓. Si una contraloría o una personería quisiera operar GovTrace, el modelo se rompe.

**La salida:** el operador privado compra los XLM y la entidad paga en pesos un servicio.

**La volatilidad:**
- el precio del XLM en pesos cambia;
- la alerta del saldo de la patrocinadora (bajo 50 XLM) ya avisa a tiempo;
- el costo en pesos de cada sello no se puede fijar de antemano.

## 4. Fricción de adopción: no lo tumba, pero frena el uso

- **F1. Las instituciones no están obligadas a usarlo.**
  - Una alcaldía puede ver GovTrace como un arma de la oposición. Una regla de neutralidad en los términos de uso ayuda; el art. 19 de la Ley 850 ya excluye a los políticos como veedores.
  - El aval de la red institucional de apoyo (Ley 850, art. 22: Procuraduría, Contraloría, Defensoría, Ministerio del Interior, Función Pública y ESAP) bajaría mucho esa fricción.
  - ❓ Si el expediente cabe en los canales de la Contraloría, por tamaño y formato.
- **F2. El estigma de lo "cripto."**
  - En Colombia se asocia con pirámides y estafas.
  - La interfaz ya no lo menciona: dice "sello" y "huella", sin billeteras ni tokens para nadie.
  - Lo único que lo contradice es el nombre "Prueba Pericial Criptográfica" (R2).
- **F3. Las veedurías son voluntarias:** sin presupuesto, con poca práctica digital y, a veces, con mala conexión.
  - Ya está cubierto:
    - el modo sin conexión;
    - la interfaz pensada para adultos mayores (`docs/ux-analisis.md`);
    - el costo cero para ellas.
  - Falta la capacitación, que podría venir de la ESAP o de una universidad ❓.
- **F4. Las fuentes de terceros.**
  - SECOP II tiene vacíos: muchos contratos sin avance.
  - ❓ Los límites de su API sin token de aplicación, y la atribución que pide su licencia abierta (Resolución 1519 de 2020, anexo de datos abiertos).
  - Las imágenes de OpenStreetMap no admiten tráfico de producción intenso; ya está anotado en `estado-mvp.md`.
- **F5. La accesibilidad.**
  - Si una entidad pública adopta GovTrace, le aplica la Resolución 1519 de 2020 del MinTIC, que exige WCAG 2.1 AA a los sujetos obligados de la Ley 1712 de 2014.
  - La app ya apunta a WCAG 2.2 AA y lo mide con `make ux-check`.

## 5. El presupuesto: la infraestructura es barata, lo caro son las personas

| Qué | Costo | Estado |
|---|---|---|
| AWS: servidor, S3, correo y KMS | 20 a 33 USD al mes | Calculado (`estado-mvp.md`, sección 5) |
| XLM de arranque | ~200 XLM, una vez | Calculado (`preparacion-red-principal.md`, sección 4) |
| XLM por uso | ~0,25 XLM por sello (~25 por cada 100 reportes) | Medido en testnet |
| XLM de mantenimiento | ~27 XLM cada ~180 días, para la vigencia del contrato | Calculado |
| Proveedor de RPC de la red principal | Según el plan ❓ | Sin cotizar |
| Proveedor de mapas | Según el plan ❓ | Sin cotizar |
| Dominio | El de un `.co` | Sin comprar |
| Abogado | Honorarios de L3, más revisiones | Sin cotizar |
| **Personas** | Ver la lista debajo de la tabla | **Nadie asignado. Es el costo más grande** |

Las personas cubren:
- al menos dos Super Administradores;
- la validación de cada veeduría (46b);
- el soporte;
- las solicitudes de los titulares de los datos y de terceros (R1, R4).

**Ingresos: ninguno.** La facturación quedó fuera del MVP, y las veedurías no pueden pagar.

**Quién podría pagar**, de lo más rápido a lo más lento:

1. **El Stellar Community Fund.** Financia proyectos construidos en Stellar y Soroban, en XLM: sus Build Awards llegan hasta 150.000 USD. Que pague en XLM resuelve de paso la tesorería (R5).
2. **La cooperación internacional y las fundaciones de transparencia.**
3. **Una alianza con una universidad, de preferencia pública.** Su consultorio jurídico podría cubrir parte de L3 y asesorar a las veedurías, y su facultad, la capacitación (F3). Siendo pública, además, abre el fondo del punto 5.
4. **Los organismos de control.** La Ley 1757 de 2015 los obliga a incluir en su plan anual "el financiamiento de actividades para fortalecer los mecanismos de control social" (art. 71). Una contraloría territorial podría pagar en pesos el servicio a una veeduría de su territorio, sin comprar XLM (R5). ❓ La contratación.
5. **Los fondos de participación ciudadana:**
   - el nacional, del Ministerio del Interior (Ley 1757, art. 96). Financia proyectos de participación, que el Ministerio ejecuta directamente o mediante contratos o convenios con entidades de derecho público;
   - los departamentales, municipales y distritales (art. 99).

## 6. Por dónde empezar

1. **Decidir el operador (L1).** Destraba todo lo demás.
2. **Pedir una sola revisión legal (L3)** con las preguntas de la sección 7.
3. **Validar con la Contraloría** el valor y el formato de la evidencia (R2), antes de comprar XLM. Las preguntas están en `proceso-actual.md`, sección 7. Y proponerle un convenio, como el de Tá de Pé con la contraloría federal de Brasil (sección 8).
4. **Un plan de financiación a 12 meses** (sección 5). Empezar por el Stellar Community Fund y una universidad.

Cuando el abogado responda, lo que toca en la app son unas dos o tres iteraciones pequeñas, cada una con su `/discovery`:
- los términos de uso, con aceptación versionada (L2);
- el canal "pedir revisión" para terceros y el aviso de responsabilidad en la ficha (R1);
- el nuevo nombre de la constancia (R2);
- si se decide, mostrar solo la fecha en la línea de tiempo pública (R3).

Y dos documentos de operación, sin código:
- el procedimiento de incidentes de datos (R4);
- el protocolo del veedor amenazado (R3).

## 7. Las preguntas para el abogado

1. ¿El operador debe ser una persona natural, una fundación o una ESAL? ¿Con qué régimen tributario? (L1)
2. ¿Hay un responsable único de los datos (el operador), o cada veeduría es responsable y el operador su encargado (D-V2-10)? ¿Qué acuerdo se firma con cada una? (L2)
3. ¿Qué deben decir los términos de uso sobre la responsabilidad de lo publicado? ¿Un estado que calcula GovTrace, como "En riesgo", lo expone como autor? (L2, R1)
4. ¿Qué plazo y qué procedimiento debe tener la solicitud de revisión de un tercero? (R1)
5. ¿"Prueba Pericial Criptográfica" o "Constancia de integridad"? ¿Qué le falta a la constancia para valer más como mensaje de datos (Ley 527, art. 11)? (R2)
6. ¿Bastan los términos de AWS como contrato de transmisión? (R4)
7. ¿El operador está obligado a inscribir sus bases en el Registro Nacional de Bases de Datos? (R4)
8. ¿Cómo registra la compra de XLM el contador del operador? (R5)
9. ¿Los cinco impedimentos del veedor, en lenguaje claro, dicen lo mismo que el art. 19 de la Ley 850? (`go-live.md`)
10. ¿Los 30 días de retención de los informes ciudadanos son suficientes, o demasiados? (`go-live.md`)
11. ¿Cómo contrata una contraloría territorial un servicio así con los recursos del art. 71 de la Ley 1757? (sección 5)

## 8. Lo que ya existe en otros países

GovTrace junta dos cosas:
- la vigilancia ciudadana de obras públicas;
- una evidencia sellada en una red pública, que cualquiera puede verificar.

**Cada mitad ya existe por separado.** No se encontró a nadie que haga las dos juntas, aunque eso no prueba que no exista.

| Referente | Dónde | Qué hace | Frente a GovTrace |
|---|---|---|---|
| **ControlApp y Geo Portal** (Contraloría General) | Colombia | Por ControlApp, el ciudadano envía fotos y videos de una obra a la Contraloría. El Geo Portal muestra más de 45.000 contratos de SECOP I y II con sus advertencias. Desde agosto de 2025 hay un ecosistema digital de participación. | **El más cercano, y es la propia Contraloría.** No modela veedurías, y su evidencia no la puede verificar un tercero. |
| **Elefantes Blancos** (Secretaría de Transparencia, 2013) | Colombia | Fotos de obras abandonadas, con votos de los ciudadanos. En 2017 había identificado 54 proyectos. | Ya no se encuentra activa ❓. |
| **INFObras y los Monitores Ciudadanos de Control** (Contraloría) | Perú; INFObras se llevó a Chile | Las entidades registran sus obras, y la Contraloría capacita y acredita a voluntarios que las vigilan. | Un estudio del BID encontró que redujo los sobrecostos de las obras contratadas. |
| **Tá de Pé** (Transparência Brasil, 2017) | Brasil | El ciudadano fotografía obras de escuelas y guarderías, y unos ingenieros revisan las fotos. | Tiene un **convenio con la CGU**, la contraloría federal, que atiende sus alertas de retraso. |
| **DevelopmentCheck** (Integrity Action) | Kenia, Nepal, Congo | Monitores comunitarios con una app y fotos, que siguen cada problema hasta que se resuelve. | Lo esencial es cerrar el ciclo con la entidad. |
| **eyeWitness to Atrocities** (International Bar Association, 2015) | Global | Hash al capturar, cadena de custodia y un custodio de confianza (LexisNexis). | **El referente probatorio:** su evidencia se ha usado ante tribunales de Ucrania. Es de derechos humanos, no de obras. |
| **ProofMode y Starling Lab**, con el estándar C2PA | Global | Fotos firmadas al capturarlas, con su huella fechada en Bitcoin. | Periodismo y derechos humanos. |
| **Piloto del Foro Económico Mundial, el BID y la Procuraduría** (2019–2020) | Colombia | Blockchain para la contratación del PAE en Medellín, sobre Ethereum, con la Universidad Nacional. | Fue contratación, no evidencia de campo, y se quedó en prueba de concepto. Encontró límites de escala y de anonimato en una red pública. |

**Lo que sí es distintivo de GovTrace:**
- está hecha para la veeduría de la Ley 850, con sus veedores y la revisión antes de publicar;
- la evidencia queda sellada en Stellar, con un verificador independiente;
- tiene la geocerca y los contratos de SECOP II;
- arma el expediente para el proceso formal;
- protege la privacidad desde el teléfono;
- es de código abierto.

**Qué enseñan:**
- **La Contraloría es el competidor más cercano, y conviene que sea aliada.**
  - La primera objeción será "¿para qué otra app, si ControlApp ya existe?".
  - La respuesta: GovTrace sirve al trabajo organizado de la veeduría, su evidencia la puede verificar cualquiera, y termina en los canales de la Contraloría (R-LEG-02).
  - Tá de Pé muestra el camino: un convenio con el órgano de control (F1).
- **eyeWitness confirma R2:** ante los tribunales no basta la huella. Funciona por la cadena de custodia y un equipo legal detrás.
- **Elefantes Blancos advierte sobre la sostenibilidad:** una app de un gobierno puede morir con él. Una ESAL con el código abierto sobrevive mejor (L1).
- **La evaluación del BID sobre INFObras** sirve para pedir financiación (sección 5): hay evidencia de que la vigilancia ciudadana de obras reduce los sobrecostos.
- **DevelopmentCheck mide lo que se resolvió, no lo que se reportó.** GovTrace mide las descargas del expediente (R-LEG-04), pero no si la entidad corrigió.

## Fuentes

- [Constitución Política, arts. 15 y 20 (Secretaría del Senado)](http://www.secretariasenado.gov.co/senado/basedoc/constitucion_politica_1991.html)
- [Ley 527 de 1999, mensajes de datos (Secretaría del Senado)](http://www.secretariasenado.gov.co/senado/basedoc/ley_0527_1999.html)
- [Ley 599 de 2000, Código Penal, arts. 220 a 222 (Secretaría del Senado)](http://www.secretariasenado.gov.co/senado/basedoc/ley_0599_2000.html)
- [Ley 1564 de 2012, Código General del Proceso (Secretaría del Senado)](http://www.secretariasenado.gov.co/senado/basedoc/ley_1564_2012.html)
- [Ley 1581 de 2012, protección de datos personales (Secretaría del Senado)](http://www.secretariasenado.gov.co/senado/basedoc/ley_1581_2012.html)
- [Ley 1757 de 2015, texto completo (Rama Judicial)](https://sidn.ramajudicial.gov.co/SIDN/NORMATIVA/TEXTOS_COMPLETOS/7_LEYES/LEYES%202015/Ley%201757%20de%202015.pdf)
- [Sentencia SU-420 de 2019, el buen nombre en las redes sociales (Corte Constitucional)](https://www.corteconstitucional.gov.co/relatoria/2019/su420-19.htm)
- [Circular Externa 005 de 2017 de la SIC, países con nivel adecuado (resumen de Holland & Knight)](https://www.hklaw.com/en/insights/publications/2017/08/cambios-en-la-transferencia-de-datos-personales-a)
- [Resolución 1519 de 2020 del MinTIC, accesibilidad y datos abiertos (CERLATAM)](https://www.cerlatam.com/normatividad/normas-nacionales-de-colombia/mintic-resolucion-1519-de-2020/)
- [Concepto C21-130537 del Banco de la República, sobre los criptoactivos](https://banrep.gov.co/es/banco/junta-directiva/conceptos/c21-130537)
- [Recomendaciones de Transparencia por Colombia al Proyecto de Ley 476 de 2025C, protección a denunciantes (abril de 2026)](https://transparenciacolombia.org.co/wp-content/uploads/2026/04/ComentariosTPC_PLProteccion_Abril2026.pdf)
- [Stellar Community Fund](https://communityfund.stellar.org/) y [su evolución hacia los proyectos en la red principal (The Defiant)](https://thedefiant.io/education/tutorials/the-stellar-community-fund-evolves-to-bring-more-projects-to-mainnet)
- Las normas del control social (Ley 850 de 2003, Ley 2020 de 2020): en las fuentes de [`proceso-actual.md`](proceso-actual.md).

**De la sección 8:**
- [El Geo Portal de la Contraloría (Blu Radio)](https://www.bluradio.com/tecnologia/plataforma-permitira-consultar-vigilar-y-denunciar-irregularidades-de-obras-publicas-rs15)
- [El ecosistema digital de la Contraloría, agosto de 2025 (Asuntos Legales)](https://www.asuntoslegales.com.co/eventos/contraloria-general-lanzara-nuevo-ecosistema-digital-de-participacion-ciudadana-4208473)
- [ControlApp (Valora Analitik)](https://www.valoraanalitik.com/?p=136700)
- [Elefantes Blancos (Open Contracting Partnership)](https://sostenibilidad.open-contracting.org/casos-de-estudio/engaging-citizens-to-monitor-corruption-in-public-construction-projects-in-colombia)
- [El impacto de INFObras y el control ciudadano (BID)](https://publications.iadb.org/es/gobierno-digital-y-corrupcion-el-impacto-de-infobras-y-el-control-ciudadano-en-la-eficiencia-de-la)
- [Tá de Pé (Transparência Brasil)](https://www.transparencia.org.br/projetos/ta-de-pe-works)
- [DevelopmentCheck (Civic Tech Field Guide)](https://civictech.guide/projects/development-check)
- [eyeWitness ante tribunales de Ucrania (International Bar Association)](https://www.ibanet.org/Ukrainian-court-cases-rely-footage-captured-IBA-founded-eyeWitness-to-Atrocities-app)
- [ProofMode (Starling Lab)](https://starling.stanford.edu/prototypes/proofmode-authentication/)
- [El piloto de blockchain en Colombia (Foro Económico Mundial)](https://www.weforum.org/stories/emerging-technologies/heres-how-blockchain-stopped-corrupt-officials-stealing-school-dinners/) y [sus conclusiones (Global Government Forum)](https://www.globalgovernmentforum.com/colombian-blockchain-trial-cause-for-cautious-optimism-says-wef)
