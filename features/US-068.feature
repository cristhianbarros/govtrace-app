# language: es
@story_id:US-068 @origin:usuario @priority:1 @epic:EPIC-008
Característica: Del informe ciudadano a una evidencia: la veeduría manda a un veedor
  Como Administrador de la veeduría
  quiero enterarme de los informes ciudadanos y pedirle a un veedor que documente uno
  para que lo que vio el ciudadano se vuelva una evidencia sellada

  Antecedentes:
    Dado que la veeduría "veeduria-smr" vigila la obra "Pavimentación Calle 30"
    Y que un ciudadano informó "La obra lleva dos semanas sin trabajadores", con 2 fotos

  @complexity:low
  Escenario: La veeduría ve cuántos informes ciudadanos esperan, como en la Bandeja
    Cuando el Administrador entra a su panel
    Entonces "Informes ciudadanos" muestra un 1 en rojo
    Y el lector de pantalla dice "Informes ciudadanos, 1 por atender"
    Cuando responde, asigna o descarta el informe
    Entonces el número desaparece

  @complexity:medium
  Escenario: Los informes se ven por pestañas, una por estado
    Dado que hay informes nuevos, con visita asignada, documentados, respondidos y descartados
    Cuando el Administrador abre "Informes ciudadanos"
    Entonces ve las pestañas "Nuevos", "Visita asignada", "Documentados", "Respondidos", "Descartados" y "Todos"
    Y está en "Nuevos", con el número de los que esperan
    Cuando toca "Visita asignada"
    Entonces la pantalla trae solo esos informes, sin recargar la página

  @complexity:medium
  Escenario: Filtro los informes por obra y por veedor, como en la Bandeja
    Dado que hay informes de dos obras, y visitas asignadas a dos veedores
    Cuando elijo la obra "Pavimentación Calle 30"
    Entonces veo solo los informes de esa obra, en la pestaña en la que estoy
    Cuando elijo además al veedor "Carlos Rojas"
    Entonces veo solo los informes de esa obra con visita de Carlos

  @complexity:low
  Escenario: El resumen diario cuenta los informes ciudadanos que esperan
    Dado que hay 2 evidencias por revisar
    Cuando llega el resumen de las 07:00
    Entonces dice "2 evidencias de sus veedores esperan su revisión."
    Y dice "1 informe ciudadano espera respuesta."

  @complexity:low
  Escenario: Con informes ciudadanos y sin evidencias por revisar, el resumen llega igual
    Dado que no hay evidencias por revisar
    Cuando llega el resumen de las 07:00
    Entonces dice "1 informe ciudadano espera respuesta."

  @complexity:high
  Escenario: El Administrador pide a un veedor que documente un informe
    Dado que "Carlos Rojas" fue el último veedor que reportó la obra
    Cuando el Administrador toca "Pedir a un veedor que lo documente"
    Entonces "Carlos Rojas" aparece ya elegido
    Cuando confirma
    Entonces el informe queda "Visita asignada a Carlos Rojas"
    Y al ciudadano le llega un correo: un veedor de la veeduría va a visitar la obra
    Y queda en el log de auditoría

  @complexity:medium
  Escenario: Si nadie ha reportado la obra, el Administrador elige al veedor
    Dado que ningún veedor ha reportado la obra
    Cuando el Administrador toca "Pedir a un veedor que lo documente"
    Entonces ningún veedor aparece elegido
    Y no puede confirmar sin elegir uno

  @complexity:medium @negative
  Escenario: Solo un veedor activo que ya declaró sus impedimentos se puede elegir
    Dado que la veeduría tiene un veedor desactivado y una veedora que no ha declarado sus impedimentos
    Cuando el Administrador elige a quién pedirle la visita
    Entonces ninguno de los dos está en la lista

  @complexity:high
  Escenario: El veedor ve su visita pendiente, con el mensaje y las fotos del ciudadano
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando Carlos abre "Nuevo reporte"
    Entonces arriba ve "Visitas pendientes" con la obra, el mensaje del ciudadano y sus 2 fotos
    Y nunca ve el correo del ciudadano
    Cuando toca la visita
    Entonces el reporte se abre con esa obra elegida y lo que informó el ciudadano

  @complexity:high
  Escenario: El reporte del veedor sobre esa obra queda ligado al informe
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando Carlos envía un reporte de esa obra, desde la visita o desde la lista de obras
    Entonces el reporte queda ligado al informe del ciudadano
    Y se sella como cualquier reporte
    Y la visita sale de sus "Visitas pendientes"

  @complexity:medium @negative
  Escenario: El reporte de otro veedor sobre la obra no se liga al informe
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando otro veedor envía un reporte de esa obra
    Entonces el reporte no queda ligado al informe
    Y la visita sigue pendiente para Carlos

  @complexity:medium
  Escenario: El veedor no puede ir y la visita vuelve al Administrador
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando Carlos toca "No puedo ir" y lo confirma
    Entonces la visita sale de sus "Visitas pendientes"
    Y el informe vuelve a "Nuevo", con la nota "Carlos Rojas no pudo ir", y cuenta de nuevo en el número rojo
    Y queda en el log de auditoría

  @complexity:medium
  Escenario: El Administrador le pasa la visita a otro veedor
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando la cambia a "Lucía Méndez"
    Entonces la visita sale de las de Carlos y aparece en las de Lucía
    Y al ciudadano no le llega otro correo

  @complexity:medium
  Escenario: La Bandeja dice a qué informe responde la evidencia
    Dado que el reporte de Carlos quedó ligado al informe
    Cuando el Administrador abre la evidencia en la Bandeja
    Entonces ve "Responde al informe ciudadano" con su referencia y el mensaje del ciudadano

  @complexity:high
  Escenario: Al publicar la evidencia, el informe queda documentado y el ciudadano recibe el enlace
    Dado que el reporte de Carlos quedó ligado al informe
    Cuando el Administrador publica la evidencia
    Entonces el informe queda "Documentado"
    Y al ciudadano le llega un correo con el enlace a la evidencia publicada
    Y el correo no lleva el nombre del veedor

  @complexity:medium @negative
  Escenario: Si la Bandeja rechaza la evidencia, la visita sigue pendiente
    Dado que el reporte de Carlos quedó ligado al informe
    Cuando el Administrador rechaza la evidencia
    Entonces el informe sigue "Visita asignada a Carlos Rojas"
    Y la visita vuelve a sus "Visitas pendientes"
    Y al ciudadano no le llega ningún correo

  @complexity:medium
  Escenario: Una obra sin ubicación: la visita muestra el punto que propuso el ciudadano
    Dado que la obra no tiene ubicación y el ciudadano propuso dónde queda
    Cuando Carlos abre la visita
    Entonces ve el punto que propuso el ciudadano, como pista para encontrarla
    Y su primer reporte fija la ubicación con las reglas de siempre (R-GEO-01)

  @complexity:medium @negative
  Escenario: Un veedor solo ve sus propias visitas
    Dado que el Administrador le pidió la visita a "Carlos Rojas"
    Cuando otro veedor abre "Nuevo reporte"
    Entonces no ve esa visita
    Y no puede abrir las fotos del informe
