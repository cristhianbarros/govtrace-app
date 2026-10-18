# language: es
@story_id:US-059-LEG @origin:proceso_actual @priority:1 @epic:EPIC-008
Característica: Informar a la veeduría de lo que vi en una obra
  Como ciudadano que vio algo en una obra
  quiero informárselo a la veeduría con mi correo verificado y sin crear una cuenta
  para que la tenga en cuenta y me responda

  Antecedentes:
    Dado que la veeduría "veeduria-smr" vigila la obra "Pavimentación Calle 30"

  @complexity:high
  Escenario: El ciudadano informa a la veeduría con su correo verificado
    Dado que soy un visitante sin sesión en la obra
    Cuando pido un código para "vecina@correo.co" y autorizo el tratamiento de mis datos
    Entonces me llega un correo con un código de 6 dígitos
    Cuando envío el código, mi mensaje y una foto
    Entonces veo "Su informe llegó a la veeduría. Si lo atiende, le responde a su correo."
    Y me llega un correo con la referencia de mi informe

  @complexity:medium
  Escenario: El código y el acuse salen por la cola de correos, sin hacer esperar al ciudadano
    Cuando pido un código para "vecina@correo.co" y autorizo el tratamiento de mis datos
    Entonces la pantalla me responde sin esperar al servidor de correo
    Y el correo con el código queda en la cola de correos, cifrado
    Cuando envío el código y mi mensaje
    Entonces el correo con la referencia de mi informe también sale por la cola de correos

  @complexity:high @negative
  Escenario: Si el servidor de correo falla, el código se pide igual y el correo se reintenta
    Dado que el servidor de correo no responde
    Cuando pido un código para "vecina@correo.co" y autorizo el tratamiento de mis datos
    Entonces veo "Le enviamos un código de 6 dígitos a vecina@correo.co. Vence en 10 minutos."
    Y el correo se intenta de nuevo más tarde, hasta 5 veces
    Y ni el código ni mi correo quedan legibles en la cola

  @complexity:medium @negative
  Escenario: Sin un código válido no se recibe el informe
    Dado que pedí un código
    Cuando envío mi informe con un código equivocado, o después de 10 minutos
    Entonces veo "El código no es válido o ya venció. Pida uno nuevo."
    Y después de 5 intentos equivocados el código ya no sirve

  @complexity:low @negative
  Escenario: Sin autorizar el tratamiento de datos no se pide el código
    Cuando pido un código sin autorizar el tratamiento de mis datos
    Entonces veo "Para informar a la veeduría, autorice el tratamiento de sus datos personales."

  # It. 46h: de 1 a 3 fotos, con las mismas opciones del veedor.
  @complexity:medium
  Escenario: El ciudadano adjunta de 1 a 3 fotos a su informe
    Cuando envío mi informe con 3 fotos, cada una revisada y con los rostros difuminados
    Entonces la veeduría las ve las 3, en orden, y cada una se abre por separado

  @complexity:low @negative
  Escenario: Un informe con más de 3 fotos se rechaza
    Cuando intento adjuntar una 4.ª foto
    Entonces veo "Un informe admite máximo 3 fotos."

  @complexity:low
  Escenario: El ciudadano tiene las mismas opciones del veedor
    Cuando voy a adjuntar fotos a mi informe
    Entonces veo "Tomar foto", que abre la cámara trasera
    Y veo "Elegir de la galería"
    Y no se aceptan PDF ni videos

  # It. 46e (R-PRIV-05 reescrita).
  @complexity:medium
  Escenario: Los rostros de la foto del informe ciudadano se difuminan en el celular
    Dado que voy a informar a la veeduría con una foto donde se ve a una persona
    Cuando adjunto la foto
    Entonces la reviso, ya difuminada, antes de enviarla

  @complexity:medium @negative
  Escenario: La foto del informe llega sin metadatos
    Cuando envío mi informe con una foto que conserva su ubicación en los metadatos
    Entonces se rechaza con el motivo

  @complexity:medium @negative
  Escenario: Límites contra el spam
    Dado que ya envié 3 informes hoy con mi correo
    Cuando envío otro
    Entonces se rechaza con "Llegó al límite de 3 informes por día. Puede enviar más mañana."

  @complexity:medium
  Escenario: El informe ciudadano no se sella ni se publica
    Cuando envío un informe
    Entonces no se sella en la red Stellar
    Y no aparece en el mapa ni cambia el estado de la obra

  @complexity:medium
  Escenario: La veeduría recibe los informes sin ver el correo del ciudadano
    Dado que un ciudadano envió un informe
    Cuando el Administrador abre "Informes ciudadanos"
    Entonces ve la obra, la fecha, el mensaje y la foto
    Y no ve el correo de quien lo envió

  @complexity:medium
  Escenario: La veeduría responde al ciudadano
    Cuando el Administrador responde un informe
    Entonces al ciudadano le llega la respuesta a su correo
    Y el informe queda "Respondido" y en el log de auditoría

  @complexity:low
  Escenario: La veeduría descarta un informe
    Cuando el Administrador descarta un informe
    Entonces queda "Descartado", sin correo al ciudadano, y en el log de auditoría

  @complexity:low @negative
  Escenario: Una veeduría suspendida no recibe informes
    Dado que la organización está suspendida
    Cuando pido un código
    Entonces veo "Esta veeduría está suspendida y no recibe informes por ahora."

  @complexity:low @negative
  Escenario: Solo el Administrador ve los informes
    Cuando un veedor pide los informes ciudadanos
    Entonces se le niega

  @complexity:medium
  Escenario: Veo 20 informes y Ver 20 más trae los siguientes
    Dado que la veeduría ha recibido 45 informes de ciudadanos
    Cuando abro "Informes ciudadanos"
    Entonces veo 20 informes, los más recientes primero, y "45 informes"
    Cuando toco "Ver 20 más"
    Entonces veo 40 informes

  @complexity:low
  # It. 65 (US-068): el estado se elige con pestañas, no con una lista.
  Escenario: Filtro los informes por estado
    Dado que hay informes nuevos, respondidos y descartados
    Cuando toco la pestaña "Nuevos"
    Entonces veo solo los informes nuevos
    Cuando toco la pestaña "Todos"
    Entonces veo todos los informes

  # It. 58: también de una obra que ningún veedor ha visitado, y sin ubicación todavía.
  @complexity:medium
  Escenario: El ciudadano encuentra una obra sin ubicación y le informa a la veeduría
    Dado que el contrato "Construcción de la vía a Minca" del territorio no tiene ficha de obra
    Cuando abro la lista de obras
    Entonces lo veo en "Obras sin ubicación todavía"
    Cuando le informo a la veeduría de esa obra
    Entonces la veeduría recibe mi informe
    Y la obra queda con su ficha, sin ubicación y sin pin en el mapa

  @complexity:medium
  Escenario: El ciudadano propone dónde queda una obra sin ubicación
    Dado que la obra "Construcción de la vía a Minca" no tiene ubicación
    Cuando le informo a la veeduría y marco en el mapa dónde queda la obra
    Entonces la veeduría recibe mi informe con la ubicación propuesta
    Y la obra sigue sin pin en el mapa hasta que la veeduría la confirme

  @complexity:low @negative
  Escenario: Una ubicación propuesta lejos del municipio se rechaza
    Cuando propongo una ubicación a más de 30 km de la cabecera del municipio del contrato
    Entonces veo "Ese punto queda a más de 30 km de Santa Marta. Revise dónde marcó la obra, o envíe el informe sin la ubicación."
    Y el informe no se recibe, y mi código sigue sirviendo

  @complexity:low
  Escenario: Una obra que ya tiene ubicación no la pide
    Dado que la obra "Pavimentación Calle 30" ya tiene ubicación
    Cuando le informo a la veeduría
    Entonces no se me pregunta dónde queda la obra
    Y si el informe trae una ubicación, se recibe sin ella

  @complexity:high
  Escenario: La ficha de una obra se crea una sola vez
    Dado que el contrato "Construcción de la vía a Minca" no tiene ficha de obra
    Cuando un ciudadano le informa a la veeduría mientras un veedor envía el primer reporte de ese contrato
    Entonces la obra tiene una sola ficha, con el informe y el reporte

  @complexity:low @negative
  Escenario: No se informa de un contrato que la veeduría no vigila
    Cuando informo de un contrato fuera del territorio de la veeduría, o que ya no se puede reportar
    Entonces veo "Esa obra no está entre las que vigila esta veeduría."

  # It. 62d (2026-10-18, reportado por el usuario): la respuesta salía arriba, fuera de la pantalla.
  @complexity:low
  Escenario: La respuesta de la plataforma queda a la vista, sin mover la pantalla
    Cuando toco "Enviarme el código" o "Enviar a la veeduría"
    Entonces la pantalla se mueve sola hasta la respuesta: "Le enviamos un código…", "Su informe llegó a la veeduría…" o lo que hay que corregir

