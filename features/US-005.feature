# language: es
@story_id:US-005 @origin:discovery_inicial @priority:1 @epic:EPIC-006
Característica: Invitar veedores por correo
  Como Administrador de Organización
  quiero invitar nuevos usuarios por correo asignándoles el rol de veedor de campo
  para conformar el equipo de supervisión local

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Invitación exitosa
    Cuando invito a "carlos@correo.co" como veedor de campo
    Entonces se genera una invitación con un enlace seguro que vence en 48 horas
    Y se envía a "carlos@correo.co" un correo con el enlace de acceso

  @complexity:low @negative
  Esquema del escenario: La invitación vence estrictamente a las 48 horas
    Dado que invité a "carlos@correo.co" hace <tiempo>
    Cuando "carlos@correo.co" abre el enlace de invitación
    Entonces el enlace es "<resultado>"

    Ejemplos:
      | tiempo                 | resultado |
      | 47 horas y 59 minutos  | válido    |
      | 48 horas y 1 minuto    | inválido  |

  @complexity:low @negative
  Esquema del escenario: No se puede invitar un correo ya registrado o con invitación pendiente en la organización
    Dado que "carlos@correo.co" <situacion> en mi organización
    Cuando invito a "carlos@correo.co" como veedor de campo
    Entonces la invitación es rechazada
    Y veo el mensaje "Ya existe un usuario registrado o una invitación pendiente con este correo electrónico en la organización."

    Ejemplos:
      | situacion                        |
      | ya es un veedor activo           |
      | tiene una invitación pendiente   |

  @complexity:medium @edge
  Escenario: El mismo correo puede ser veedor en otra organización
    Dado que "carlos@correo.co" ya es veedor de la organización "Veeduría Ciénaga"
    Cuando invito a "carlos@correo.co" como veedor de campo
    Entonces la invitación se envía
    Y al aceptarla "carlos@correo.co" tendrá una cuenta independiente en cada organización

  @complexity:low @negative
  Escenario: El Administrador de Organización no puede dar de alta otras organizaciones
    Cuando intento registrar la organización "Veeduría Nueva" con NIT "901234567-7"
    Entonces la acción es rechazada
