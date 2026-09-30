# language: es
@story_id:US-058-LEG @origin:proceso_actual @priority:1 @epic:EPIC-006
Característica: La política de tratamiento de datos y la autorización
  Como persona cuyos datos trata GovTrace
  quiero conocer la política de tratamiento y autorizar el tratamiento de los míos al crear mi cuenta
  para que el tratamiento cumpla la Ley 1581 de 2012

  @complexity:medium
  Escenario: Cualquiera lee la política de tratamiento de datos
    Dado que soy un visitante sin sesión
    Cuando abro "/privacidad" en el dominio central o en el de una veeduría
    Entonces leo quién es el responsable, qué datos se tratan y para qué, mis derechos, a quién escribir, cómo y en qué plazos, y desde cuándo rige

  @complexity:low
  Escenario: La política dice qué queda en la red pública
    Cuando leo la política
    Entonces leo que en la red Stellar solo quedan huellas y no datos personales
    Y que la ubicación exacta del veedor no se publica

  @complexity:low @negative
  Escenario: Sin los datos del responsable la política es un borrador
    Dado que la configuración no tiene los datos del responsable del tratamiento
    Cuando abro la política
    Entonces veo que es un borrador y qué datos faltan

  @complexity:medium
  Escenario: Autorizo el tratamiento de mis datos al activar mi cuenta
    Dado que me invitaron a una veeduría
    Cuando abro el enlace de mi invitación
    Entonces veo el enlace a la política y la casilla "Autorizo el tratamiento de mis datos personales"
    Cuando la marco y creo mi contraseña
    Entonces mi autorización queda con su fecha, la versión de la política y el registro en el log de auditoría

  @complexity:low @negative
  Escenario: Sin la autorización no se activa la cuenta
    Dado que me invitaron a una veeduría
    Cuando creo mi contraseña sin autorizar el tratamiento de mis datos
    Entonces veo "Para crear su cuenta, autorice el tratamiento de sus datos personales."
    Y mi invitación sigue pendiente

  @complexity:low
  Escenario: La política está enlazada donde se entra
    Cuando abro el Inicio, el mapa de una veeduría o la pantalla de entrada
    Entonces encuentro el enlace "Política de tratamiento de datos"
