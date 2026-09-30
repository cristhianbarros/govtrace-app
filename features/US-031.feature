# language: es
@story_id:US-031 @origin:discovery_inicial @priority:1 @epic:EPIC-006
Característica: Inicio de sesión por rol
  Como usuario registrado (Super Administrador, Administrador de Organización o Veedor)
  quiero iniciar sesión
  para acceder a las herramientas de mi rol

  @complexity:low
  Esquema del escenario: Inicio de sesión exitoso y redirección por rol
    Dado que existe el usuario activo "<correo>" con rol "<rol>" y contraseña "Veeduria#2026"
    Cuando inicio sesión en <entrada> con "<correo>" y "Veeduria#2026"
    Entonces llego al panel de "<rol>"

    Ejemplos:
      | correo                     | rol                           | entrada                      |
      | root@govtrace.app          | Super Administrador           | el panel global              |
      | ana.perez@veeduria-smr.org | Administrador de Organización | el subdominio "veeduria-smr" |
      | carlos@correo.co           | Veedor de Campo               | el subdominio "veeduria-smr" |

  @complexity:low @negative
  Escenario: Credenciales incorrectas
    Dado que existe el usuario activo "carlos@correo.co" con rol "Veedor de Campo" y contraseña "Veeduria#2026"
    Cuando inicio sesión en el subdominio "veeduria-smr" con "carlos@correo.co" y "Otra#2026"
    Entonces no inicio sesión
    Y veo el mensaje "Credenciales incorrectas. Verifique su correo electrónico y contraseña."

  @complexity:low @negative
  Esquema del escenario: Validaciones del formulario de acceso
    Cuando intento iniciar sesión con correo "<correo>" y contraseña "<contrasena>"
    Entonces no inicio sesión

    Ejemplos:
      | correo           | contrasena    |
      | carlos@          | Veeduria#2026 |
      | carlos@correo.co |               |

  @complexity:medium @negative
  Escenario: Bloqueo temporal tras 5 intentos fallidos
    Dado que existe el usuario activo "carlos@correo.co" con rol "Veedor de Campo" y contraseña "Veeduria#2026"
    Y ya fallé 4 intentos de inicio de sesión con "carlos@correo.co"
    Cuando fallo un quinto intento
    Entonces la cuenta queda bloqueada durante 15 minutos
    Y veo el mensaje "Demasiados intentos fallidos. Tu cuenta ha sido bloqueada temporalmente durante 15 minutos."
    Y aunque use la contraseña correcta dentro de esos 15 minutos no inicio sesión

  @complexity:low @negative
  Escenario: Cuenta desactivada
    Dado que "carlos@correo.co" es un veedor desactivado
    Cuando inicio sesión con "carlos@correo.co" y su contraseña correcta
    Entonces no inicio sesión
    Y veo el mensaje "Su cuenta se encuentra desactivada. Comuníquese con el administrador de su organización."

  @complexity:low
  Escenario: El Verificador Público no necesita iniciar sesión
    Dado que soy un visitante sin sesión
    Cuando abro el mapa público de "veeduria-smr" y el validador
    Entonces accedo a ambos sin que se me pida iniciar sesión

  @complexity:low
  Escenario: Cerrar sesión desde cualquier panel
    Dado que inicié sesión como Super Administrador, Administrador de Organización o Veedor de Campo
    Cuando abro el menú de mi cuenta en la cabecera y elijo "Salir"
    Entonces la app me pregunta "¿Cerrar la sesión?"
    Y al confirmar, mi sesión termina y vuelvo a la pantalla de inicio de sesión
