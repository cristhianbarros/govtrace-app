# language: es
@story_id:US-047-MNT @origin:analisis_completitud @priority:3 @epic:EPIC-003
Característica: Volver a encolar evidencias con falla de sellado
  Como Super Administrador
  quiero volver a encolar desde mi panel las evidencias en "Falla de Sellado"
  para recuperar sellos fallidos sin intervención técnica fuera del sistema

  @complexity:low
  Escenario: Re-encolar varias evidencias a la vez
    Dado que estoy autenticado como Super Administrador en el panel global
    Y hay 4 evidencias en "Falla de Sellado"
    Cuando selecciono 3 de ellas y las vuelvo a encolar
    Entonces esas 3 evidencias pasan a "En Cola"
    Y la cuarta sigue en "Falla de Sellado"

  @complexity:low @negative
  Escenario: Un Administrador de Organización no puede re-encolar
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento volver a encolar una evidencia en "Falla de Sellado"
    Entonces la acción es rechazada
