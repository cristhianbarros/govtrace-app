# language: es
@story_id:US-053-RPT @origin:analisis_completitud @priority:3 @epic:EPIC-009
Característica: Resumen de uso por organización
  Como Super Administrador
  quiero ver un resumen de uso por organización
  para seguir la adopción de la plataforma

  @complexity:low
  Escenario: Resumen de uso de una organización
    Dado que estoy autenticado como Super Administrador en el panel global
    Y "Veeduría Ciudadana Santa Marta" tiene 8 veedores activos y recibió 50 evidencias: 30 publicadas, 5 rechazadas y 2 retiradas
    Cuando abro el resumen de uso
    Entonces la fila de "Veeduría Ciudadana Santa Marta" muestra 8 veedores activos, 50 recibidas, 30 publicadas, 5 rechazadas y 2 retiradas

  @complexity:low @negative
  Escenario: Un Administrador de Organización no accede al resumen global
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir el resumen de uso por organización
    Entonces la acción es rechazada
