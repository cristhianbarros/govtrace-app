# language: es
@story_id:US-055-LEG @origin:proceso_actual @priority:1 @epic:EPIC-008
Característica: Qué significa el estado de una obra y cómo avisar a la Contraloría
  Como Verificador Público
  quiero saber que el estado de una obra es una alerta de GovTrace y tener a mano los canales de la Contraloría
  para no confundir "En riesgo" con una obra inconclusa y poder denunciar por mi cuenta

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"

  @complexity:low
  Escenario: El estado de una obra es una alerta de GovTrace
    Cuando abro una obra
    Entonces bajo su estado leo "Es una alerta de GovTrace, calculada con los datos de SECOP II y las evidencias publicadas. No es la decisión de una autoridad."

  @complexity:low
  Escenario: En riesgo no es lo mismo que obra inconclusa
    Dado que una obra está "En riesgo"
    Cuando la abro
    Entonces leo que "En riesgo" no es lo mismo que "obra inconclusa", con la definición de la Ley 2020 de 2020
    Y el enlace "¿Qué puede hacer?" me lleva a los canales de la Contraloría

  @complexity:low
  Escenario: Cualquier ciudadano puede avisar a la Contraloría
    Cuando abro una obra
    Entonces veo "¿Sabe de un problema en esta obra?"
    Y puedo llamar a la Línea gratuita 199 o al 01 8000 910060, denunciar en línea en SIPAR o ver todos los canales de la Contraloría
    Y leo que no hace falta ser veedor ni tener cuenta en GovTrace

  @complexity:low @negative
  Escenario: Con recursos locales puede ser competente la contraloría territorial
    Cuando abro una obra
    Entonces leo que la Contraloría General atiende los recursos nacionales, y que si la obra se paga con recursos del departamento o del municipio puede ser competente la contraloría de ese territorio

  @complexity:low
  Escenario: Los colores del mapa son alertas de GovTrace
    Cuando abro "¿Cómo funciona?" en el mapa
    Entonces leo que los colores son alertas de GovTrace, no decisiones de una autoridad

  @complexity:low @negative
  Escenario: Las estadísticas no llaman inconclusas a las obras en riesgo
    Cuando abro las estadísticas del territorio
    Entonces leo que las obras en riesgo son alertas de GovTrace, no obras inconclusas en el sentido de la Ley 2020 de 2020
