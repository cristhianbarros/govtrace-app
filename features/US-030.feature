# language: es
@story_id:US-030 @origin:discovery_inicial @priority:1 @epic:EPIC-006
Característica: Aceptar la invitación y crear la contraseña
  Como Veedor de Campo
  quiero aceptar la invitación y crear mi contraseña
  para activar mi cuenta de acceso

  Antecedentes:
    Dado que "carlos@correo.co" recibió una invitación de "Veeduría Ciudadana Santa Marta" hace 2 horas

  @complexity:low
  Escenario: Activación exitosa de la cuenta
    Cuando abro el enlace de la invitación
    Y defino y confirmo la contraseña "Veeduria#2026"
    Entonces mi cuenta queda activa
    Y entro de inmediato a la aplicación

  @complexity:low @negative
  Escenario: Enlace de invitación vencido
    Dado que la invitación se emitió hace 49 horas
    Cuando abro el enlace de la invitación
    Entonces no puedo crear la contraseña
    Y veo el mensaje "El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador."

  @complexity:low @negative
  Esquema del escenario: La contraseña debe cumplir las reglas mínimas
    Cuando abro el enlace de la invitación
    Y defino y confirmo la contraseña "<contrasena>"
    Entonces la contraseña es rechazada
    Y veo el mensaje "La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial."

    Ejemplos:
      | contrasena    | falla                 |
      | Ve#2026       | menos de 8 caracteres |
      | veeduria#2026 | sin mayúscula         |
      | VEEDURIA#2026 | sin minúscula         |
      | Veeduria#abc  | sin número            |
      | Veeduria2026  | sin símbolo           |
