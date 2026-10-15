# language: es
@story_id:US-066 @origin:usuario @priority:1 @epic:EPIC-008
Característica: Buscar la obra en el Inicio y que GovTrace sugiera a qué veeduría informar
  Como ciudadano que quiere ayudar y no sabe a qué veeduría escribirle
  quiero buscar la obra en el Inicio y que GovTrace me sugiera qué veeduría puede atenderla
  para informar sin tener que adivinar

  Antecedentes:
    Dado que "Veeduría Santa Marta" vigila el municipio de Santa Marta
    Y "Veeduría del Magdalena" vigila todo el departamento del Magdalena
    Y el contrato "Construcción de la vía a Minca" es de Santa Marta

  @complexity:medium
  Escenario: El ciudadano busca una obra en el Inicio
    Cuando busco "via a minca" en "¿Qué obra quiere reportar?"
    Entonces encuentro "Construcción de la vía a Minca", con su municipio y su entidad
    Y puedo buscar también por municipio, y ver 20 obras a la vez

  @complexity:high
  Escenario: GovTrace sugiere la veeduría que ya sigue la obra
    Dado que "Veeduría del Magdalena" ya sigue la obra y publicó 1 evidencia
    Cuando elijo "Construcción de la vía a Minca"
    Entonces veo "Le sugerimos" "Veeduría del Magdalena", con "Ya sigue esta obra: 1 evidencia publicada"
    Y debajo, en "También cubren esta obra", "Veeduría Santa Marta", con "Vigila Santa Marta"

  @complexity:medium
  Escenario: Si ninguna la sigue, se sugiere la de territorio más específico
    Cuando elijo "Construcción de la vía a Minca"
    Entonces veo "Le sugerimos" "Veeduría Santa Marta", con "Vigila Santa Marta"
    Y debajo "Veeduría del Magdalena", con "Vigila todo Magdalena"

  @complexity:medium
  Escenario: Entre iguales, se sugiere la que publicó en los últimos 90 días
    Dado que "Veeduría Ciénaga y Santa Marta" también vigila Santa Marta y publicó una evidencia hace 10 días
    Y "Veeduría Santa Marta" publicó su última evidencia hace 100 días
    Cuando elijo "Construcción de la vía a Minca"
    Entonces veo "Le sugerimos" "Veeduría Ciénaga y Santa Marta", con "Publicó evidencias en los últimos 90 días"

  @complexity:low
  Escenario: En un empate, las veedurías se muestran como iguales
    Dado que "Veeduría Bonda" también vigila Santa Marta, y ninguna de las dos sigue la obra ni publicó evidencias
    Cuando elijo "Construcción de la vía a Minca"
    Entonces no veo "Le sugerimos"
    Y veo las dos como iguales, en orden alfabético

  @complexity:low @negative
  Escenario: Una veeduría suspendida no se sugiere
    Dado que "Veeduría Suspendida" vigila Santa Marta y está suspendida
    Cuando elijo "Construcción de la vía a Minca"
    Entonces no la veo

  @complexity:medium @negative
  Escenario: Si ninguna veeduría cubre la obra
    Dado que el contrato "Pavimentación del barrio Rebolo" es de Barranquilla, y ninguna veeduría activa lo cubre
    Cuando lo elijo
    Entonces veo "Ninguna veeduría de GovTrace cubre esta obra todavía."
    Y veo tres caminos: la Personería de Barranquilla, la Contraloría y pedir el alta de una veeduría

  @complexity:medium
  Escenario: Un toque lleva al formulario de esa veeduría
    Cuando elijo "Construcción de la vía a Minca" y toco "Informar a esta veeduría" en la sugerida
    Entonces llego a esa obra en el sitio de esa veeduría, en "Informar a esta veeduría"

  @complexity:medium
  Escenario: Usar mi ubicación propone el municipio, sin enviarla
    Cuando toco "Usar mi ubicación" cerca de Santa Marta
    Entonces se me pregunta "¿Está en Santa Marta (Magdalena)?"
    Y ninguna petición al servidor lleva mi ubicación

  @complexity:high
  Escenario: El resumen central sigue a las fichas y a las evidencias
    Cuando una veeduría crea la ficha de una obra, publica o retira una evidencia, o agrupa contratos
    Entonces el resumen central dice qué veedurías siguen ese contrato y cuántas evidencias publicaron
    Y recalcularlo entero da lo mismo

  @complexity:low
  Escenario: La búsqueda no abre una sesión ni guarda la obra en la dirección
    Cuando busco una obra y elijo una
    Entonces ninguna respuesta pone cookies
    Y la dirección del Inicio no cambia
