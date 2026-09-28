# language: es
@story_id:US-051-RPT @origin:analisis_completitud @priority:3 @epic:EPIC-004
Característica: Estadísticas públicas del territorio
  Como Verificador Público
  quiero ver estadísticas públicas del territorio
  para dimensionar el problema sin revisar obra por obra

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"

  @complexity:low
  Escenario: Estadísticas del mapa público
    Dado que la organización tiene 3 obras en riesgo, evidencias publicadas en julio, agosto y septiembre, y 2 contratos anulados con evidencias
    Cuando abro las estadísticas del territorio
    Entonces veo 3 obras en riesgo, las evidencias publicadas por mes y 2 contratos anulados con evidencias

  @complexity:low @negative
  Escenario: Solo cuentan las evidencias publicadas
    Dado que la organización tiene 10 evidencias publicadas y 4 ocultas en septiembre
    Cuando abro las estadísticas del territorio
    Entonces septiembre muestra 10 evidencias
