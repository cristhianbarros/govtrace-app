# language: es
@story_id:US-042-SEC @origin:analisis_completitud @priority:2 @epic:EPIC-006
Característica: Autorización explícita al Super Administrador para reportar en nombre de una organización
  Como Administrador de Organización
  quiero autorizar en el sistema al Super Administrador para crear reportes en nombre de mi organización
  para dar mi consentimiento de forma explícita y trazable

  @complexity:medium
  Escenario: Con autorización vigente el Super Administrador puede crear un reporte
    Dado que el Administrador de "Veeduría Ciudadana Santa Marta" autorizó al Super Administrador hace 10 días
    Cuando el Super Administrador crea un reporte en "Veeduría Ciudadana Santa Marta"
    Entonces el reporte es aceptado
    Y el log de auditoría registra la autorización y el reporte creado

  @complexity:low @negative
  Esquema del escenario: Sin autorización vigente el Super Administrador no puede reportar
    Dado que la autorización del Super Administrador en "Veeduría Ciudadana Santa Marta" <situacion>
    Cuando el Super Administrador intenta crear un reporte en "Veeduría Ciudadana Santa Marta"
    Entonces el reporte es rechazado
    Y ve el mensaje "No cuenta con una autorización activa de la organización para realizar esta acción."

    Ejemplos:
      | situacion                           |
      | nunca fue otorgada                  |
      | se otorgó hace 31 días              |
      | fue revocada por el Administrador   |

  @complexity:low @negative
  Escenario: Solo puede existir una autorización vigente a la vez
    Dado que el Administrador de "Veeduría Ciudadana Santa Marta" autorizó al Super Administrador hace 5 días
    Cuando intenta otorgar una segunda autorización
    Entonces se mantiene una sola autorización vigente
