# language: es
@story_id:US-002 @origin:discovery_inicial @priority:1 @epic:EPIC-009
Característica: Asignación del Administrador inicial de una organización
  Como Super Administrador
  quiero asignar el usuario Administrador inicial de una organización aprobada
  para transferirle la autonomía en la gestión de sus propios veedores

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y existe la organización activa "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Asignación exitosa del Administrador inicial
    Cuando asigno como Administrador inicial a "Ana Pérez" con correo "ana.perez@veeduria-smr.org"
    Entonces se crea el usuario "ana.perez@veeduria-smr.org" con rol "Administrador de Organización"
    Y se envía a "ana.perez@veeduria-smr.org" un correo de bienvenida con un enlace para establecer su contraseña

  @complexity:low @negative
  Esquema del escenario: El correo del Administrador inicial debe tener formato válido
    Cuando asigno como Administrador inicial a "Ana Pérez" con correo "<correo>"
    Entonces la asignación es rechazada
    Y no se envía ningún correo

    Ejemplos:
      | correo                 |
      | ana.perez              |
      | ana.perez@             |
      | @veeduria-smr.org      |
      | ana perez@veeduria.org |

  @complexity:low @negative
  Escenario: El correo del Administrador inicial no puede pertenecer a otro usuario activo del sistema
    Dado que "ana.perez@veeduria-smr.org" ya es un usuario activo del sistema
    Cuando asigno como Administrador inicial a "Ana Pérez" con correo "ana.perez@veeduria-smr.org"
    Entonces la asignación es rechazada
    Y veo el mensaje "El correo electrónico ya se encuentra registrado en el sistema."
