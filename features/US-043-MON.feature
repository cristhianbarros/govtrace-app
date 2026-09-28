# language: es
@story_id:US-043-MON @origin:analisis_completitud @priority:2 @epic:EPIC-009
Característica: Consulta del log de auditoría
  Como Super Administrador o Administrador de Organización
  quiero consultar el log de auditoría según mi alcance
  para saber quién hizo qué, cuándo y qué cambió

  Antecedentes:
    Dado que el log tiene 3 entradas de "Veeduría Ciudadana Santa Marta" y 2 de "Veeduría Ciénaga"

  @complexity:low
  Escenario: El Super Administrador ve todo el log
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando abro el log de auditoría
    Entonces veo las 5 entradas
    Y cada entrada muestra quién, cuándo, la acción, el valor anterior y el nuevo

  @complexity:low
  Escenario: El Administrador de Organización ve solo lo de su organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando abro el log de auditoría
    Entonces veo solo las 3 entradas de mi organización

  @complexity:low @negative
  Escenario: Un Administrador de Organización no accede a entradas de otra organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir una entrada del log de "Veeduría Ciénaga"
    Entonces la acción es rechazada
