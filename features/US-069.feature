# language: es
@story_id:US-069 @origin:usuario @priority:1 @epic:EPIC-008
Característica: Que lo que informan los ciudadanos tenga seguimiento y se vea
  Como ciudadano que informó de una obra
  quiero saber hasta cuándo me responde la veeduría, sumarme a lo que otro vecino informó y compartir la evidencia
  para que el problema se vea y no se quede en un informe

  Antecedentes:
    Dado que la veeduría "veeduria-smr" vigila la obra "Pavimentación Calle 30"

  # 66a — el plazo de respuesta

  @complexity:low
  Escenario: La veeduría fija en cuántos días hábiles responde, 15 por defecto
    Dado que la veeduría nunca cambió su plazo
    Cuando el Administrador abre "Organización"
    Entonces ve "Días hábiles para responder a los ciudadanos" con 15
    Cuando lo cambia a 10 y guarda
    Entonces el plazo de la veeduría es de 10 días hábiles
    Y queda en el log de auditoría

  @complexity:low @negative
  Escenario: Un plazo fuera de rango se rechaza
    Cuando el Administrador escribe 0 o 61 días
    Entonces ve "El plazo va de 1 a 60 días hábiles."

  @complexity:medium
  Escenario: Al enviar el informe, el ciudadano sabe hasta cuándo responde la veeduría
    Dado que la veeduría responde en 15 días hábiles
    Cuando un ciudadano envía su informe el viernes 30 de octubre de 2026
    Entonces ve "La veeduría se compromete a responderle antes del martes 24 de noviembre de 2026 (15 días hábiles)."
    Y el correo con la referencia de su informe dice la misma fecha

  @complexity:medium
  Escenario: Los días hábiles no cuentan fines de semana ni festivos
    Cuando se cuentan 15 días hábiles desde el viernes 30 de octubre de 2026
    Entonces no cuentan los sábados, los domingos, el lunes 2 de noviembre ni el lunes 16 de noviembre, festivos
    Y el plazo vence el martes 24 de noviembre de 2026

  @complexity:low
  Escenario: Cambiar el plazo no cambia la fecha de los informes que ya llegaron
    Dado que un informe llegó con el plazo de 15 días hábiles
    Cuando el Administrador cambia el plazo a 10 días
    Entonces ese informe conserva su fecha
    Y los informes que lleguen después usan 10 días

  @complexity:medium
  Escenario: El resumen diario avisa de los informes por vencer y vencidos
    Dado que un informe vence en 2 días y otro venció ayer, los dos sin respuesta
    Cuando llega el resumen de las 07:00
    Entonces dice "1 informe ciudadano vence en los próximos 3 días."
    Y dice "1 informe ciudadano ya venció sin respuesta."

  @complexity:high
  Escenario: Si vence sin respuesta, al ciudadano le llega el camino a la Contraloría
    Dado que un informe venció ayer sin una primera acción de la veeduría
    Cuando se revisan los plazos de la mañana
    Entonces al ciudadano le llega un correo: la veeduría aún no ha respondido
    Y el correo trae la obra, su contrato del SECOP, la entidad, lo que informó y los canales de la Contraloría
    Y le llega una sola vez

  @complexity:medium @negative
  Escenario: Una primera acción a tiempo detiene el plazo
    Dado que la veeduría respondió, pidió la visita o descartó un informe antes de que venciera
    Cuando se revisan los plazos después de la fecha
    Entonces al ciudadano no le llega el correo de la Contraloría
    Y el informe no cuenta como vencido, aunque la visita vuelva a "Nuevo"

  # 66b — "Yo también lo vi"

  @complexity:high
  Escenario: Otro ciudadano se suma a lo que ya se informó de una obra
    Dado que un ciudadano informó de la obra y la veeduría aún no lo documenta
    Cuando otro ciudadano abre la página de la obra
    Entonces ve "1 ciudadano informó sobre esta obra" y "¿Usted también lo vio?"
    Cuando confirma su correo con un código y toca "Yo también lo vi"
    Entonces ve "Quedó sumado: le avisamos a su correo cuando la veeduría actúe."
    Y la obra dice "2 ciudadanos informaron sobre esta obra"

  @complexity:medium @negative
  Escenario: Cada persona se suma una sola vez por obra
    Dado que un ciudadano ya informó de la obra o ya se sumó
    Cuando intenta sumarse otra vez con el mismo correo
    Entonces ve "Usted ya informó sobre esta obra: le avisamos a su correo cuando la veeduría actúe."

  @complexity:medium
  Escenario: Quien se suma recibe los mismos correos
    Dado que un ciudadano se sumó a un informe
    Cuando la veeduría le pide la visita a un veedor, y después publica la evidencia
    Entonces a quien se sumó le llegan los dos correos, como a quien informó

  @complexity:low
  Escenario: La veeduría ve cuántos ciudadanos lo vieron
    Dado que 2 ciudadanos se sumaron a un informe
    Cuando el Administrador abre "Informes ciudadanos"
    Entonces el informe dice "3 ciudadanos lo vieron"

  @complexity:medium @privacy
  Escenario: El número público es solo eso: sin mensajes, fotos ni nombres
    Dado que 3 ciudadanos informaron sobre la obra y un veedor la está documentando
    Cuando cualquiera abre la obra o su tarjeta en el mapa
    Entonces ve "3 ciudadanos informaron sobre esta obra · Un veedor la está documentando"
    Y no ve sus mensajes, sus fotos, sus correos ni sus nombres

  @complexity:low @negative
  Escenario: Un informe descartado no cuenta en público
    Dado que la veeduría descartó el único informe de la obra
    Cuando cualquiera abre la obra
    Entonces no ve cuántos ciudadanos informaron

  # 66c — compartir

  @complexity:medium
  Escenario: La obra se comparte con una vista previa
    Dado que la obra tiene una evidencia publicada
    Cuando alguien comparte el enlace de la obra en WhatsApp o en redes
    Entonces la vista previa muestra el nombre de la obra con su estado, el valor del contrato y la foto publicada
    Y la página tiene un botón "Compartir"

  @complexity:low @negative
  Escenario: Una obra sin evidencias publicadas se comparte sin foto
    Dado que la obra no tiene evidencias publicadas
    Cuando alguien comparte el enlace de la obra
    Entonces la vista previa muestra el nombre y el estado, sin foto
