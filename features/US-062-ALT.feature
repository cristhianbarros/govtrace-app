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
