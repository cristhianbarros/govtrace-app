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
    Y el informe queda "Atendido" y en el log de auditoría

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
