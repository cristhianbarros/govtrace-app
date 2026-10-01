# language: es
@story_id:US-061-USR @origin:mapa_funcional @priority:1 @epic:EPIC-009
Característica: Varios administradores por organización
  Como veeduría
  quiero tener más de un Administrador
  para no quedar sin quién la gestione si uno pierde el acceso o se va

  Antecedentes:
    Dado que la organización "Veeduría Ciudadana Santa Marta" tiene a la Administradora activa "Marta Ospina"

  @complexity:low
  Escenario: El Super Administrador agrega otro Administrador
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando le agrego a la organización el Administrador "Ana Pérez" con el correo "ana@veeduria.org"
    Entonces "Ana Pérez" recibe la invitación para crear su contraseña
    Y la organización tiene dos administradores
    Y queda en el log de auditoría

  @complexity:low
  Escenario: Un Administrador invita a otro administrador
    Dado que estoy autenticado como "Marta Ospina"
    Cuando desde "Veedores" invito como administrador a "Ana Pérez" con el correo "ana@veeduria.org"
    Entonces "Ana Pérez" recibe la invitación para crear su contraseña
    Y queda en el log de auditoría quién la invitó

  @complexity:medium
  Escenario: El Super Administrador desactiva a un Administrador que se fue
    Dado que la organización también tiene al Administrador activo "Ana Pérez"
    Y estoy autenticado como Super Administrador en el panel global
    Cuando desactivo a "Marta Ospina"
    Entonces "Marta Ospina" ya no puede entrar
    Y queda en el log de auditoría

  @complexity:low
  Escenario: El Super Administrador reactiva a un Administrador
    Dado que "Marta Ospina" está desactivada y "Ana Pérez" está activa
    Y estoy autenticado como Super Administrador en el panel global
    Cuando reactivo a "Marta Ospina"
    Entonces "Marta Ospina" puede entrar otra vez

  @complexity:low @negative
  Escenario: No se desactiva al único Administrador activo
    Dado que estoy autenticado como Super Administrador en el panel global
    Y "Marta Ospina" es la única Administradora activa, y hay una invitación pendiente para otro
    Cuando intento desactivar a "Marta Ospina"
    Entonces veo el mensaje "No se puede desactivar al único Administrador activo de la organización. Agregue otro y espere a que active su cuenta."

  @complexity:low @negative
  Escenario: Un Administrador no desactiva administradores
    Dado que la organización también tiene al Administrador activo "Ana Pérez"
    Y estoy autenticado como "Marta Ospina"
    Cuando intento desactivar a "Ana Pérez"
    Entonces la acción es rechazada

  @complexity:low @negative
  Escenario: Un veedor no invita administradores
    Dado que estoy autenticado como un veedor de la organización
    Cuando intento invitar como administrador a "ana@veeduria.org"
    Entonces la acción es rechazada

  @complexity:low @negative
  Escenario: El correo de un administrador nuevo no puede estar ya en la organización
    Dado que "carlos@correo.co" es veedor de la organización
    Cuando invito como administrador a "carlos@correo.co"
    Entonces veo el mensaje "El correo electrónico ya se encuentra registrado en el sistema."
