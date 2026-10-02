# language: es
@story_id:US-063-USR @origin:pregunta_del_usuario @priority:1 @epic:EPIC-009
Característica: Varios Super Administradores, y nunca ninguno
  Como plataforma
  quiero tener más de un Super Administrador
  para no depender de una sola persona para dar de alta veedurías y atender las alertas

  Antecedentes:
    Dado que "Ana Directora" es Super Administradora activa

  @complexity:low
  Escenario: Un Super Administrador invita a otro
    Dado que estoy autenticado como "Ana Directora"
    Cuando invito como Super Administrador a "Luis Gómez" con el correo "luis@govtrace.org"
    Entonces "Luis Gómez" recibe la invitación para crear su contraseña
    Y aparece como "Invitación pendiente"
    Y queda en el log de auditoría

  @complexity:medium
  Escenario: El invitado activa su cuenta y entra al panel global
    Dado que "Luis Gómez" tiene una invitación pendiente
    Cuando abre el enlace, escribe su contraseña y autoriza el tratamiento de sus datos
    Entonces entra al panel global
    Y aparece como "Activo"

  @complexity:medium
  Escenario: Un Super Administrador desactiva a otro que se fue
    Dado que "Luis Gómez" también es Super Administrador activo
    Y estoy autenticado como "Ana Directora"
    Cuando desactivo a "Luis Gómez"
    Entonces "Luis Gómez" ya no puede entrar
    Y su sesión abierta se cierra en su siguiente petición
    Y queda en el log de auditoría

  @complexity:low
  Escenario: Un Super Administrador reactiva a otro
    Dado que "Luis Gómez" está desactivado
    Y estoy autenticado como "Ana Directora"
    Cuando reactivo a "Luis Gómez"
    Entonces "Luis Gómez" puede entrar otra vez

  @complexity:low
  Escenario: Reenviar y revocar una invitación pendiente de Super Administrador
    Dado que "Luis Gómez" tiene una invitación pendiente
    Y estoy autenticado como "Ana Directora"
    Cuando reenvío su invitación
    Entonces el enlace anterior deja de servir y le llega uno nuevo
    Y cuando la revoco, el enlace deja de servir

  @complexity:low @negative
  Escenario: Un Super Administrador no se desactiva a sí mismo
    Dado que estoy autenticado como "Ana Directora"
    Cuando intento desactivar mi propia cuenta
    Entonces veo el mensaje "No puede desactivar su propia cuenta. Pídale a otro Super Administrador que lo haga."

  @complexity:high @negative
  Escenario: Nunca quedan cero Super Administradores activos
    Dado que "Ana Directora" y "Luis Gómez" son los únicos Super Administradores activos
    Y "Marta Ruiz" tiene una invitación pendiente
    Cuando cada uno intenta desactivar al otro al mismo tiempo
    Entonces solo una desactivación se cumple
    Y la otra ve el mensaje "No se puede desactivar al único Super Administrador activo. Invite a otro y espere a que active su cuenta."

  @complexity:low
  Escenario: El panel avisa cuando queda un solo Super Administrador activo
    Dado que "Ana Directora" es la única activa
    Cuando entro al panel global
    Entonces veo "Solo hay un Super Administrador activo. Si pierde el acceso, nadie podrá dar de alta veedurías ni atender las alertas. Invite a otro."

  @complexity:low
  Escenario: Al quedar un solo Super Administrador activo llega una alerta
    Dado que "Ana Directora" y "Luis Gómez" son los únicos Super Administradores activos
    Cuando "Ana Directora" desactiva a "Luis Gómez"
    Entonces a los Super Administradores activos y al webhook les llega la alerta "GovTrace: queda un solo Super Administrador activo"

  @complexity:low
  Escenario: Las alertas les llegan solo a los Super Administradores activos
    Dado que "Luis Gómez" está desactivado y "Marta Ruiz" tiene una invitación pendiente
    Cuando el saldo de la cuenta patrocinadora queda bajo su umbral
    Entonces la alerta le llega a "Ana Directora"
    Y no les llega a "Luis Gómez" ni a "Marta Ruiz"

  @complexity:low @negative
  Escenario: El correo de un Super Administrador nuevo no puede estar registrado
    Dado que "luis@govtrace.org" ya es Super Administrador
    Cuando invito como Super Administrador a "luis@govtrace.org"
    Entonces veo el mensaje "El correo electrónico ya se encuentra registrado en el sistema."

  @complexity:low @negative
  Escenario: Solo un Super Administrador gestiona Super Administradores
    Dado que no tengo sesión en el panel global
    Cuando intento invitar a un Super Administrador
    Entonces la acción es rechazada
