# language: es
@story_id:US-065-SEC @origin:pregunta_del_usuario @priority:1 @epic:EPIC-006
Característica: Verificación en dos pasos del Super Administrador
  Como plataforma
  quiero que el Super Administrador entre con su contraseña y un código de su app autenticadora
  para que una contraseña robada no baste para gobernar todas las organizaciones

  @complexity:low
  Escenario: Mientras la verificación en dos pasos está inactiva, el Super Administrador entra solo con su contraseña
    Dado que el operador no activó la verificación en dos pasos
    Cuando el Super Administrador escribe su correo y su contraseña
    Entonces entra al panel global, como siempre

  @complexity:high
  Escenario: La primera vez, el Super Administrador configura su app autenticadora
    Dado que la verificación en dos pasos está activa
    Y que "Ana Directora" aún no la configuró
    Cuando escribe su correo y su contraseña
    Entonces ve un código QR y la clave para su app
    Cuando escribe el código de 6 dígitos que muestra su app
    Entonces entra al panel global
    Y ve 8 códigos de recuperación, por única vez
    Y queda en el log de auditoría

  @complexity:medium
  Escenario: El Super Administrador entra con su contraseña y el código de su app
    Dado que la verificación en dos pasos está activa y "Ana Directora" ya la configuró
    Cuando escribe su contraseña y el código que muestra su app
    Entonces entra al panel global

  @complexity:high @negative
  Escenario: La contraseña sola no abre el panel
    Dado que la verificación en dos pasos está activa
    Cuando "Ana Directora" escribe su contraseña pero no el código
    Entonces ninguna pantalla del panel global le responde

  @complexity:medium @negative
  Escenario: Un código equivocado, vencido o ya usado no deja entrar
    Dado que la verificación en dos pasos está activa y "Ana Directora" ya la configuró
    Cuando escribe un código equivocado, uno vencido o uno que ya usó
    Entonces ve "El código no es válido o ya venció. Escriba el de 6 dígitos que muestra su app ahora."
    Y con 5 equivocados queda bloqueada 15 minutos

  @complexity:medium
  Escenario: Sin su teléfono, entra con un código de recuperación, que sirve una sola vez
    Dado que la verificación en dos pasos está activa y "Ana Directora" ya la configuró
    Cuando escribe su contraseña y uno de sus códigos de recuperación
    Entonces entra al panel global
    Y ese código ya no sirve otra vez
    Y queda en el log de auditoría

  @complexity:medium @edge
  Escenario: Al activarla, las sesiones abiertas sin el segundo paso se cierran
    Dado que "Ana Directora" entró al panel antes de que se activara la verificación en dos pasos
    Cuando el operador la activa
    Entonces su sesión se cierra en su siguiente petición, con el motivo

  @complexity:medium
  Escenario: Un Super Administrador invitado configura su app al activar su cuenta
    Dado que la verificación en dos pasos está activa
    Y que "Luis Gómez" tiene una invitación de Super Administrador
    Cuando activa su cuenta con su contraseña
    Entonces configura su app autenticadora antes de entrar al panel global

  @complexity:low
  Escenario: Desde la consola del servidor se restablece la verificación de quien perdió su teléfono
    Dado que "Ana Directora" perdió su teléfono y sus códigos de recuperación
    Cuando el operador la restablece desde la consola del servidor
    Entonces al entrar la vuelve a configurar
    Y queda en el log de auditoría
