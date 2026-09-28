# language: es
@story_id:US-054-RPT @origin:analisis_completitud @priority:3 @epic:EPIC-009
Característica: Alerta de organizaciones sin actividad
  Como Super Administrador
  quiero recibir una alerta cuando una organización lleve 30 días sin actividad
  para detectar a tiempo organizaciones que abandonan la plataforma

  @complexity:low
  Escenario: Organización sin recibir ni publicar evidencias durante 30 días
    Dado que "Veeduría Ciénaga" no recibe ni publica evidencias desde hace 30 días
    Cuando se revisa la actividad de las organizaciones
    Entonces el Super Administrador recibe una alerta por Email sobre "Veeduría Ciénaga"

  @complexity:low @negative
  Esquema del escenario: Solo recibir o publicar evidencias cuenta como actividad
    Dado que "Veeduría Ciénaga" no recibe ni publica evidencias desde hace 30 días
    Pero hace 2 días "<accion>"
    Cuando se revisa la actividad de las organizaciones
    Entonces la alerta "<resultado>"

    Ejemplos:
      | accion                                  | resultado    |
      | su administrador inició sesión          | se envía     |
      | recibió una evidencia                   | no se envía  |
      | su administrador publicó una evidencia  | no se envía  |
