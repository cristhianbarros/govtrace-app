# language: es
@story_id:US-062-ALT @origin:mapa_funcional @priority:2 @epic:EPIC-009
Característica: Una veeduría pide su alta en GovTrace
  Como veeduría que quiere publicar en GovTrace
  quiero pedir mi alta desde el Inicio, sin cuenta
  para que el Super Administrador la apruebe o la rechace con un motivo

  @complexity:medium
  Escenario: Una veeduría pide su alta desde el Inicio
    Dado que estoy en el Inicio de GovTrace
    Cuando escribo el nombre "Veeduría Ciudadana de La Pradera", el correo "contacto@lapradera.org", la resolución "Resolución 045 de 2026" y la Personería "Personería de Medellín"
    Y adjunto la resolución en PDF
    Y autorizo el tratamiento de mis datos personales
    Y envío la solicitud
    Entonces veo "Recibimos su solicitud. El equipo de GovTrace la revisará y le escribirá a contacto@lapradera.org."
    Y la solicitud queda pendiente para el Super Administrador

  @complexity:low @negative
  Escenario: Sin autorizar el tratamiento de datos no se envía la solicitud
    Cuando envío la solicitud sin autorizar el tratamiento de mis datos personales
    Entonces veo el mensaje "Para enviar la solicitud, autorice el tratamiento de sus datos personales."
    Y no queda ninguna solicitud

  @complexity:low @negative
  Escenario: Una solicitud con datos inválidos se rechaza
    Cuando envío la solicitud con el correo "contacto@" y el nombre "VC"
    Entonces veo qué dato corregir
    Y no queda ninguna solicitud

  @complexity:low
  Escenario: El Super Administrador ve las solicitudes pendientes
    Dado que hay 2 solicitudes pendientes
    Cuando el Super Administrador abre "Solicitudes de alta"
    Entonces ve las 2, con su nombre, su correo, su resolución y su Personería
    Y el menú dice que hay 2

  @complexity:medium
  Escenario: El Super Administrador aprueba una solicitud
    Dado que "Veeduría Ciudadana de La Pradera" pidió su alta
    Cuando el Super Administrador la aprueba
    Entonces la Nueva organización viene con su nombre, su inscripción y su correo como Administrador inicial
    Y al registrarla, la solicitud queda aprobada y queda en el log de auditoría

  @complexity:low
  Escenario: El Super Administrador rechaza una solicitud con un motivo
    Dado que "Veeduría Ciudadana de La Pradera" pidió su alta
    Cuando el Super Administrador la rechaza con el motivo "La resolución no corresponde a una veeduría inscrita."
    Entonces al correo "contacto@lapradera.org" le llega el motivo
    Y la solicitud queda rechazada y queda en el log de auditoría

  @complexity:low @edge
  Escenario: Un robot que llena el campo oculto no deja solicitud
    Cuando un robot envía la solicitud con el campo oculto lleno
    Entonces recibe la misma respuesta
    Y no queda ninguna solicitud

  @complexity:low @edge
  Escenario: Una solicitud decidida se borra a los 30 días
    Dado que una solicitud se rechazó hace 31 días y otra hace 29
    Cuando corre la limpieza diaria
    Entonces la de hace 31 días se borra y la de hace 29 sigue

  @complexity:low @negative
  Escenario: La solicitud necesita el PDF de la resolución o del certificado de inscripción
    Cuando envío la solicitud sin el PDF, con un archivo que no es un PDF o con uno de más de 10 MB
    Entonces veo el mensaje "Adjunte la resolución o el certificado de inscripción en PDF, de hasta 10 MB."
    Y no queda ninguna solicitud

  @complexity:medium
  Escenario: El Super Administrador ve lo que dice el RUES de una veeduría inscrita en una cámara de comercio
    Dado que "Veeduría Ciudadana Paisaje Urbano" pidió su alta con la matrícula "2183031" de la "Cámara de Comercio de Medellín para Antioquia"
    Y en los datos abiertos del RUES esa matrícula es de "VEEDURIA CIUDADANA PAISAJE URBANO", de la cámara "MEDELLIN PARA ANTIOQUIA", activa
    Cuando reviso las solicitudes pendientes
    Entonces veo que el RUES la encontró, con su razón social, su cámara, su matrícula, su estado y la fecha de los datos

  @complexity:low
  Escenario: Una veeduría inscrita en una personería no está en los datos abiertos del RUES
    Dado que "Veeduría Ciudadana de La Pradera" pidió su alta con una resolución de la "Personería de Medellín"
    Cuando reviso las solicitudes pendientes
    Entonces veo "Inscrita en una personería: los datos abiertos del RUES no la traen. Revise el PDF de la resolución."
    Y puedo descargar el PDF que adjuntó

  @complexity:low @negative
  Escenario: Si el RUES no responde, la revisión sigue
    Dado que los datos abiertos del RUES no responden
    Cuando reviso las solicitudes pendientes
    Entonces veo "No se pudo consultar el RUES ahora. Puede decidir con el PDF, o volver a intentarlo más tarde."
    Y puedo aprobar o rechazar la solicitud

  @complexity:low
  Escenario: La decisión queda en la auditoría con lo que dijo el RUES
    Dado que el RUES encontró la veeduría de una solicitud
    Cuando la apruebo o la rechazo
    Entonces el log de auditoría guarda la decisión junto con lo que dijo el RUES en ese momento

  @complexity:low
  Escenario: El PDF de una solicitud aprobada queda con la organización
    Cuando apruebo una solicitud dando de alta la organización
    Entonces su PDF queda guardado con la organización
    Y el Super Administrador lo descarga desde "Organizaciones"

  @complexity:low
  Escenario: El PDF de una solicitud rechazada se borra con ella
    Dado que una solicitud se rechazó hace 31 días
    Cuando corre la limpieza diaria
    Entonces se borran la solicitud y su PDF

  @complexity:low @negative
  Escenario: Solo el Super Administrador descarga el PDF de una solicitud
    Dado que no tengo sesión en el panel global
    Cuando intento descargar el PDF de una solicitud
    Entonces la acción es rechazada
