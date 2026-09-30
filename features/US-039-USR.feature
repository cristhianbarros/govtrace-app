# language: es
@story_id:US-039-USR @origin:analisis_completitud @priority:2 @epic:EPIC-006
Característica: Restablecer la contraseña por correo
  Como usuario registrado
  quiero restablecer mi contraseña yo mismo con un enlace que recibo por correo
  para recuperar el acceso sin depender de un administrador

  @complexity:low
  Escenario: Restablecimiento exitoso
    Dado que "carlos@correo.co" es un usuario activo
    Cuando solicito restablecer la contraseña de "carlos@correo.co"
    Y abro el enlace recibido 10 minutos después
    Y defino la nueva contraseña "Nueva#2026x"
    Entonces puedo iniciar sesión con "Nueva#2026x"

  @complexity:low @negative
  Escenario: La respuesta es neutra ante un correo no registrado
    Cuando solicito restablecer la contraseña de "nadie@correo.co"
    Entonces veo el mensaje "Si el correo existe, recibirás un enlace"
    Y no se envía ningún correo

  @complexity:low @negative
  Esquema del escenario: Enlace vencido o ya usado
    Dado que solicité restablecer la contraseña de "carlos@correo.co"
    Y el enlace <situacion>
    Cuando abro el enlace
    Entonces no puedo cambiar la contraseña
    Y veo el mensaje "El enlace de restablecimiento de contraseña ha expirado o ya ha sido utilizado."

    Ejemplos:
      | situacion                   |
      | se emitió hace 61 minutos   |
      | ya fue utilizado            |

  @complexity:low @negative
  Escenario: La nueva contraseña cumple las reglas mínimas
    Dado que solicité restablecer la contraseña de "carlos@correo.co"
    Cuando abro el enlace y defino la nueva contraseña "corta1#"
    Entonces la contraseña es rechazada
    Y veo el mensaje "La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial."

  @complexity:low
  Escenario: Cambiar la contraseña con la sesión abierta
    Dado que inicié sesión con la contraseña "Veeduria#2026"
    Cuando abro "Cambiar contraseña" en el menú de mi cuenta y escribo la actual y la nueva dos veces
    Entonces veo "Su contraseña fue cambiada. La próxima vez entre con la nueva."
    Y si la actual no es correcta, veo "La contraseña actual no es correcta." y nada cambia
