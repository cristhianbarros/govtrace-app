# language: es
@story_id:US-050-RPT @origin:analisis_completitud @priority:3 @epic:EPIC-004
Característica: Exportar obras y evidencias en CSV
  Como Administrador de Organización
  quiero exportar las obras y evidencias de mi organización en CSV
  para analizarlas con otras herramientas

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Exportación con las columnas definidas
    Cuando exporto a CSV
    Entonces el archivo tiene las columnas obra, contrato, municipio, fecha, clasificación, estado editorial, hash de la evidencia, comentario y seudónimo del veedor

  @complexity:low @negative
  Escenario: La exportación solo incluye la propia organización
    Dado que "Veeduría Ciénaga" tiene 12 evidencias
    Cuando exporto a CSV
    Entonces no aparece ninguna evidencia de "Veeduría Ciénaga"

  @complexity:low @negative
  Escenario: El veedor aparece con seudónimo
    Cuando exporto a CSV
    Entonces ninguna fila contiene el nombre ni el correo del veedor
